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
            // Get transporter details
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("
                    SELECT t.*, u.prenom, u.nom, u.email, u.telephone, 
                    GROUP_CONCAT(p.nom SEPARATOR ', ') AS provinces
                    FROM transporteurs t
                    JOIN users u ON t.user_id = u.id
                    LEFT JOIN routes_transporteur rt ON rt.transporteur_id = t.id
                    LEFT JOIN provinces p ON p.id = rt.province_depart_id
                    WHERE t.id = ?
                    GROUP BY t.id
                ");
                $stmt->execute([$_GET['id']]);
                $transporter = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($transporter) {
                    $response = $transporter;
                } else {
                    http_response_code(404);
                    $response = ['error' => 'Transporteur non trouvé'];
                }
            } else {
                // List transporters with filters
                $query = "
                    SELECT t.id, t.type_vehicule, t.capacite_kg, t.statut, t.note_moyenne,
                    u.prenom, u.nom, u.telephone,
                    GROUP_CONCAT(DISTINCT p.nom SEPARATOR ', ') AS provinces
                    FROM transporteurs t
                    JOIN users u ON t.user_id = u.id
                    LEFT JOIN routes_transporteur rt ON rt.transporteur_id = t.id
                    LEFT JOIN provinces p ON p.id = rt.province_depart_id
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Apply filters
                if (isset($_GET['province'])) {
                    $query .= " AND rt.province_depart_id = ?";
                    $params[] = $_GET['province'];
                }
                
                if (isset($_GET['status'])) {
                    $query .= " AND t.statut = ?";
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['vehicle_type'])) {
                    $query .= " AND t.type_vehicule = ?";
                    $params[] = $_GET['vehicle_type'];
                }
                
                $query .= " GROUP BY t.id ORDER BY t.note_moyenne DESC";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'POST':
            // Create new transporter (admin only)
            if (!isAdmin()) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            if (empty($data['user_id']) || empty($data['type_vehicule']) || empty($data['capacite_kg'])) {
                http_response_code(400);
                $response = ['error' => 'Données manquantes'];
                break;
            }
            
            $stmt = $db->prepare("
                INSERT INTO transporteurs 
                (user_id, type_vehicule, capacite_kg, plaque_immatriculation, permis_numero, statut)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['type_vehicule'],
                $data['capacite_kg'],
                $data['plaque_immatriculation'] ?? null,
                $data['permis_numero'] ?? null,
                $data['statut'] ?? 'en_attente'
            ]);
            
            $transporterId = $db->lastInsertId();
            
            // Add provinces served
            if (!empty($data['provinces'])) {
                $stmt = $db->prepare("
                    INSERT INTO routes_transporteur 
                    (transporteur_id, province_depart_id, province_arrivee_id)
                    VALUES (?, ?, 1)
                "); // 1 = Estuaire (Libreville)
                
                foreach ($data['provinces'] as $provinceId) {
                    $stmt->execute([$transporterId, $provinceId]);
                }
            }
            
            http_response_code(201);
            $response = ['id' => $transporterId, 'message' => 'Transporteur créé'];
            break;
            
        case 'PUT':
            // Update transporter (admin or self)
            $data = json_decode(file_get_contents('php://input'), true);
            $transporterId = $_GET['id'];
            
            // Verify permissions
            if (!isAdmin() && !isTransporterOwner($transporterId)) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $updates = [];
            $params = [];
            
            // Build dynamic update query
            if (isset($data['type_vehicule'])) {
                $updates[] = 'type_vehicule = ?';
                $params[] = $data['type_vehicule'];
            }
            
            if (isset($data['capacite_kg'])) {
                $updates[] = 'capacite_kg = ?';
                $params[] = $data['capacite_kg'];
            }
            
            if (isset($data['plaque_immatriculation'])) {
                $updates[] = 'plaque_immatriculation = ?';
                $params[] = $data['plaque_immatriculation'];
            }
            
            if (isset($data['permis_numero'])) {
                $updates[] = 'permis_numero = ?';
                $params[] = $data['permis_numero'];
            }
            
            if (isAdmin() && isset($data['statut'])) {
                $updates[] = 'statut = ?';
                $params[] = $data['statut'];
            }
            
            if (empty($updates)) {
                http_response_code(400);
                $response = ['error' => 'Aucune donnée à mettre à jour'];
                break;
            }
            
            $query = "UPDATE transporteurs SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $transporterId;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Update provinces if provided
            if (isset($data['provinces'])) {
                $db->beginTransaction();
                
                // Delete old provinces
                $stmt = $db->prepare("DELETE FROM routes_transporteur WHERE transporteur_id = ?");
                $stmt->execute([$transporterId]);
                
                // Add new provinces
                $stmt = $db->prepare("
                    INSERT INTO routes_transporteur 
                    (transporteur_id, province_depart_id, province_arrivee_id)
                    VALUES (?, ?, 1)
                ");
                
                foreach ($data['provinces'] as $provinceId) {
                    $stmt->execute([$transporterId, $provinceId]);
                }
                
                $db->commit();
            }
            
            $response = ['message' => 'Transporteur mis à jour'];
            break;
            
        case 'DELETE':
            // Delete transporter (admin only)
            if (!isAdmin()) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $transporterId = $_GET['id'];
            
            // Check if transporter has active shipments
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM affectations_transport 
                WHERE transporteur_id = ? AND statut IN ('en_preparation', 'en_route')
            ");
            $stmt->execute([$transporterId]);
            
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                $response = ['error' => 'Le transporteur a des livraisons actives'];
                break;
            }
            
            // Soft delete (mark as banned)
            $stmt = $db->prepare("UPDATE transporteurs SET statut = 'banni' WHERE id = ?");
            $stmt->execute([$transporterId]);
            
            $response = ['message' => 'Transporteur désactivé'];
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

// Helper function to check if user owns the transporter profile
function isTransporterOwner($transporterId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id FROM transporteurs WHERE id = ?");
    $stmt->execute([$transporterId]);
    $userId = $stmt->fetchColumn();
    
    return $userId == $_SESSION['user_id'];
}
?>