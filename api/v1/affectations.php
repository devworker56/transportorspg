<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';

// Authenticate the user
if (!authenticateRequest()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$response = [];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            // Get transport assignments
            if (isset($_GET['id'])) {
                // Get specific assignment
                $query = "
                    SELECT a.*, 
                    m.nom AS marchandise_nom, m.poids_kg, m.dimensions, m.adresse_ramassage, m.adresse_livraison,
                    p.nom AS province,
                    CONCAT(u.prenom, ' ', u.nom) AS transporteur_nom, u.telephone AS transporteur_telephone,
                    o.prix, o.date_proposee
                    FROM affectations_transport a
                    JOIN marchandises m ON a.marchandise_id = m.id
                    JOIN provinces p ON m.province_depart_id = p.id
                    JOIN transporteurs t ON a.transporteur_id = t.id
                    JOIN users u ON t.user_id = u.id
                    JOIN offres_transport o ON a.offre_id = o.id
                    WHERE a.id = ?
                ";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$_GET['id']]);
                $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($assignment) {
                    // Add tracking history if available
                    $stmt = $db->prepare("
                        SELECT * FROM suivi_livraison 
                        WHERE affectation_id = ?
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$_GET['id']]);
                    $assignment['suivi'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response = $assignment;
                } else {
                    http_response_code(404);
                    $response = ['error' => 'Affectation non trouvée'];
                }
            } else {
                // List assignments with filters
                $query = "
                    SELECT a.id, a.statut, a.date_depart, a.date_livraison,
                    m.nom AS marchandise_nom, m.poids_kg,
                    p.nom AS province,
                    CONCAT(u.prenom, ' ', u.nom) AS transporteur_nom
                    FROM affectations_transport a
                    JOIN marchandises m ON a.marchandise_id = m.id
                    JOIN provinces p ON m.province_depart_id = p.id
                    JOIN transporteurs t ON a.transporteur_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Apply role-based filters
                if (isTransporter()) {
                    $transporterId = getTransporterId($_SESSION['user_id']);
                    $query .= " AND a.transporteur_id = ?";
                    $params[] = $transporterId;
                } elseif (!isAdmin()) {
                    // Client view - only their goods
                    $query .= " AND m.client_id = ?";
                    $params[] = $_SESSION['user_id'];
                }
                
                // Apply status filter
                if (isset($_GET['status'])) {
                    $query .= " AND a.statut = ?";
                    $params[] = $_GET['status'];
                }
                
                // Apply province filter
                if (isset($_GET['province'])) {
                    $query .= " AND m.province_depart_id = ?";
                    $params[] = $_GET['province'];
                }
                
                $query .= " ORDER BY 
                    CASE WHEN a.statut IN ('en_preparation', 'en_route') THEN 0 ELSE 1 END,
                    a.date_livraison DESC";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'POST':
            // Update assignment status (transporter or admin)
            $data = json_decode(file_get_contents('php://input'), true);
            $assignmentId = $_GET['id'];
            
            // Get assignment details
            $stmt = $db->prepare("
                SELECT a.*, t.user_id 
                FROM affectations_transport a
                JOIN transporteurs t ON a.transporteur_id = t.id
                WHERE a.id = ?
            ");
            $stmt->execute([$assignmentId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assignment) {
                http_response_code(404);
                $response = ['error' => 'Affectation non trouvée'];
                break;
            }
            
            // Check permissions
            $isTransporterOwner = ($assignment['user_id'] == $_SESSION['user_id']);
            
            if (!isAdmin() && !$isTransporterOwner) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            // Validate action
            if (empty($data['action'])) {
                http_response_code(400);
                $response = ['error' => 'Action manquante'];
                break;
            }
            
            // Process action
            switch ($data['action']) {
                case 'start':
                    if ($assignment['statut'] !== 'en_preparation') {
                        http_response_code(400);
                        $response = ['error' => 'Action non valide pour le statut actuel'];
                        break 2;
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE affectations_transport 
                        SET statut = 'en_route', date_depart = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$assignmentId]);
                    
                    // Add tracking entry
                    $stmt = $db->prepare("
                        INSERT INTO suivi_livraison 
                        (affectation_id, statut, localisation, notes)
                        VALUES (?, 'en_route', ?, ?)
                    ");
                    $stmt->execute([
                        $assignmentId,
                        $data['localisation'] ?? 'Départ',
                        $data['notes'] ?? 'Livraison démarrée'
                    ]);
                    break;
                    
                case 'update':
                    if ($assignment['statut'] !== 'en_route') {
                        http_response_code(400);
                        $response = ['error' => 'Action non valide pour le statut actuel'];
                        break 2;
                    }
                    
                    // Add tracking entry
                    $stmt = $db->prepare("
                        INSERT INTO suivi_livraison 
                        (affectation_id, statut, localisation, notes)
                        VALUES (?, 'en_route', ?, ?)
                    ");
                    $stmt->execute([
                        $assignmentId,
                        $data['localisation'] ?? null,
                        $data['notes'] ?? 'Mise à jour du statut'
                    ]);
                    break;
                    
                case 'complete':
                    if ($assignment['statut'] !== 'en_route') {
                        http_response_code(400);
                        $response = ['error' => 'Action non valide pour le statut actuel'];
                        break 2;
                    }
                    
                    $db->beginTransaction();
                    
                    try {
                        // Update assignment status
                        $stmt = $db->prepare("
                            UPDATE affectations_transport 
                            SET statut = 'livre', date_livraison = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$assignmentId]);
                        
                        // Update goods status
                        $stmt = $db->prepare("
                            UPDATE marchandises 
                            SET statut = 'livre' 
                            WHERE id = ?
                        ");
                        $stmt->execute([$assignment['marchandise_id']]);
                        
                        // Add tracking entry
                        $stmt = $db->prepare("
                            INSERT INTO suivi_livraison 
                            (affectation_id, statut, localisation, notes)
                            VALUES (?, 'livre', 'Livré', ?)
                        ");
                        $stmt->execute([
                            $assignmentId,
                            $data['notes'] ?? 'Livraison complétée'
                        ]);
                        
                        $db->commit();
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'delay':
                    // Update assignment status
                    $stmt = $db->prepare("
                        UPDATE affectations_transport 
                        SET statut = 'retarde' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$assignmentId]);
                    
                    // Add tracking entry
                    $stmt = $db->prepare("
                        INSERT INTO suivi_livraison 
                        (affectation_id, statut, localisation, notes)
                        VALUES (?, 'retarde', ?, ?)
                    ");
                    $stmt->execute([
                        $assignmentId,
                        $data['localisation'] ?? null,
                        $data['notes'] ?? 'Livraison retardée'
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    $response = ['error' => 'Action non reconnue'];
                    break 2;
            }
            
            $response = ['message' => 'Statut mis à jour'];
            break;
            
        default:
            http_response_code(405);
            $response = ['error' => 'Méthode non autorisée'];
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $response = ['error' => 'Erreur de base de données: ' . $e->getMessage()];
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);

// Helper function to get transporter ID from user ID
function getTransporterId($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}
?>