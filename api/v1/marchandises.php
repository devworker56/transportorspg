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
            // Get goods needing transport (from MPG)
            $query = "
                SELECT m.*, p.nom AS province, 
                (SELECT COUNT(*) FROM offres_transport WHERE marchandise_id = m.id) AS offer_count
                FROM marchandises m
                JOIN provinces p ON m.province_depart_id = p.id
                WHERE m.statut = 'en_attente'
            ";
            
            $params = [];
            
            // Apply filters
            if (isset($_GET['province'])) {
                $query .= " AND m.province_depart_id = ?";
                $params[] = $_GET['province'];
            }
            
            if (isset($_GET['max_weight'])) {
                $query .= " AND m.poids_kg <= ?";
                $params[] = $_GET['max_weight'];
            }
            
            if (isset($_GET['deadline'])) {
                $query .= " AND m.date_limite <= ?";
                $params[] = $_GET['deadline'];
            }
            
            $query .= " ORDER BY m.date_limite ASC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'POST':
            // Create new goods entry (from MPG integration)
            if (!isAdmin()) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $requiredFields = ['mpg_product_id', 'nom', 'poids_kg', 'province_depart_id', 
                             'adresse_ramassage', 'adresse_livraison', 'date_limite'];
            
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    $response = ['error' => "Le champ $field est requis"];
                    break 2;
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO marchandises 
                (mpg_product_id, nom, description, poids_kg, dimensions, 
                province_depart_id, adresse_ramassage, adresse_livraison, date_limite)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['mpg_product_id'],
                $data['nom'],
                $data['description'] ?? null,
                $data['poids_kg'],
                $data['dimensions'] ?? null,
                $data['province_depart_id'],
                $data['adresse_ramassage'],
                $data['adresse_livraison'],
                $data['date_limite']
            ]);
            
            $goodsId = $db->lastInsertId();
            
            http_response_code(201);
            $response = ['id' => $goodsId, 'message' => 'Marchandise créée'];
            break;
            
        case 'PUT':
            // Update goods status (admin only)
            if (!isAdmin()) {
                http_response_code(403);
                $response = ['error' => 'Forbidden'];
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $goodsId = $_GET['id'];
            
            if (empty($data['statut'])) {
                http_response_code(400);
                $response = ['error' => 'Statut manquant'];
                break;
            }
            
            $stmt = $db->prepare("UPDATE marchandises SET statut = ? WHERE id = ?");
            $stmt->execute([$data['statut'], $goodsId]);
            
            $response = ['message' => 'Statut de la marchandise mis à jour'];
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
?>