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
            // Get bids - different views for different roles
            if (isAdmin()) {
                // Admin view - all bids
                $query = "
                    SELECT o.*, 
                    m.nom AS marchandise_nom, m.poids_kg, p.nom AS province,
                    t.id AS transporteur_id, CONCAT(u.prenom, ' ', u.nom) AS transporteur_nom
                    FROM offres_transport o
                    JOIN marchandises m ON o.marchandise_id = m.id
                    JOIN provinces p ON m.province_depart_id = p.id
                    JOIN transporteurs t ON o.transporteur_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Apply filters
                if (isset($_GET['status'])) {
                    $query .= " AND o.statut = ?";
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['province'])) {
                    $query .= " AND m.province_depart_id = ?";
                    $params[] = $_GET['province'];
                }
                
                if (isset($_GET['transporteur'])) {
                    $query .= " AND o.transporteur_id = ?";
                    $params[] = $_GET['transporteur'];
                }
                
                $query .= " ORDER BY o.created_at DESC";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif (isTransporter()) {
                // Transporter view - their own bids
                $stmt = $db->prepare("
                    SELECT o.*, m.nom AS marchandise_nom, m.poids_kg, p.nom AS province
                    FROM offres_transport o
                    JOIN marchandises m ON o.marchandise_id = m.id
                    JOIN provinces p ON m.province_depart_id = p.id
                    WHERE o.transporteur_id = ?
                    ORDER BY o.created_at DESC
                ");
                
                $transporterId = getTransporterId($_SESSION['user_id']);
                $stmt->execute([$transporterId]);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                // Client view - bids for their goods
                $stmt = $db->prepare("
                    SELECT o.*, 
                    CONCAT(u.prenom, ' ', u.nom) AS transporteur_nom,
                    t.type_vehicule, t.capacite_kg, t.note_moyenne
                    FROM offres_transport o
                    JOIN marchandises m ON o.marchandise_id = m.id
                    JOIN transporteurs t ON o.transporteur_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE m.client_id = ?
                    ORDER BY o.created_at DESC
                ");
                
                $stmt->execute([$_SESSION['user_id']]);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'POST':
            // Create new bid (transporters only)
            if (!isTransporter()) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $requiredFields = ['marchandise_id', 'prix', 'date_proposee'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    $response = ['error' => "Le champ $field est requis"];
                    break 2;
                }
            }
            
            // Check if goods exists and is available
            $stmt = $db->prepare("SELECT statut FROM marchandises WHERE id = ?");
            $stmt->execute([$data['marchandise_id']]);
            $status = $stmt->fetchColumn();
            
            if ($status !== 'en_attente') {
                http_response_code(400);
                $response = ['error' => 'La marchandise n\'est pas disponible'];
                break;
            }
            
            // Check if transporter already made an offer
            $transporterId = getTransporterId($_SESSION['user_id']);
            
            $stmt = $db->prepare("
                SELECT id FROM offres_transport 
                WHERE marchandise_id = ? AND transporteur_id = ?
            ");
            $stmt->execute([$data['marchandise_id'], $transporterId]);
            
            if ($stmt->fetch()) {
                http_response_code(400);
                $response = ['error' => 'Vous avez déjà fait une offre pour cette marchandise'];
                break;
            }
            
            // Create the offer
            $stmt = $db->prepare("
                INSERT INTO offres_transport 
                (transporteur_id, marchandise_id, prix, date_proposee, commentaire)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $transporterId,
                $data['marchandise_id'],
                $data['prix'],
                $data['date_proposee'],
                $data['commentaire'] ?? null
            ]);
            
            $offerId = $db->lastInsertId();
            
            http_response_code(201);
            $response = ['id' => $offerId, 'message' => 'Offre créée'];
            break;
            
        case 'PUT':
            // Update bid status (admin or client)
            $data = json_decode(file_get_contents('php://input'), true);
            $offerId = $_GET['id'];
            
            // Get offer details
            $stmt = $db->prepare("
                SELECT o.*, m.client_id 
                FROM offres_transport o
                JOIN marchandises m ON o.marchandise_id = m.id
                WHERE o.id = ?
            ");
            $stmt->execute([$offerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$offer) {
                http_response_code(404);
                $response = ['error' => 'Offre non trouvée'];
                break;
            }
            
            // Check permissions
            $isClientOwner = ($offer['client_id'] == $_SESSION['user_id']);
            
            if (!isAdmin() && !$isClientOwner) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            // Validate input
            if (empty($data['statut'])) {
                http_response_code(400);
                $response = ['error' => 'Statut manquant'];
                break;
            }
            
            // Update offer status
            $stmt = $db->prepare("UPDATE offres_transport SET statut = ? WHERE id = ?");
            $stmt->execute([$data['statut'], $offerId]);
            
            // If accepted, create transport assignment
            if ($data['statut'] === 'accepte') {
                $db->beginTransaction();
                
                try {
                    // Update goods status
                    $stmt = $db->prepare("UPDATE marchandises SET statut = 'affecte' WHERE id = ?");
                    $stmt->execute([$offer['marchandise_id']]);
                    
                    // Reject other offers for this goods
                    $stmt = $db->prepare("
                        UPDATE offres_transport 
                        SET statut = 'rejete' 
                        WHERE marchandise_id = ? AND id != ?
                    ");
                    $stmt->execute([$offer['marchandise_id'], $offerId]);
                    
                    // Create transport assignment
                    $stmt = $db->prepare("
                        INSERT INTO affectations_transport 
                        (marchandise_id, transporteur_id, offre_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $offer['marchandise_id'],
                        $offer['transporteur_id'],
                        $offerId
                    ]);
                    
                    $db->commit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            
            $response = ['message' => 'Statut de l\'offre mis à jour'];
            break;
            
        case 'DELETE':
            // Delete bid (transporter or admin)
            $offerId = $_GET['id'];
            
            // Get offer details
            $stmt = $db->prepare("
                SELECT o.*, t.user_id 
                FROM offres_transport o
                JOIN transporteurs t ON o.transporteur_id = t.id
                WHERE o.id = ?
            ");
            $stmt->execute([$offerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$offer) {
                http_response_code(404);
                $response = ['error' => 'Offre non trouvée'];
                break;
            }
            
            // Check permissions
            $isTransporterOwner = ($offer['user_id'] == $_SESSION['user_id']);
            
            if (!isAdmin() && !$isTransporterOwner) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            // Check if offer can be deleted
            if ($offer['statut'] !== 'en_attente') {
                http_response_code(400);
                $response = ['error' => 'Seules les offres en attente peuvent être supprimées'];
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM offres_transport WHERE id = ?");
            $stmt->execute([$offerId]);
            
            $response = ['message' => 'Offre supprimée'];
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