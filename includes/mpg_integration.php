<?php
require_once 'config.php';
require_once 'db_connect.php';

class MPGIntegration {
    private $mpg_api_url;
    private $api_key;
    
    public function __construct() {
        $this->mpg_api_url = MPG_API_URL;
        $this->api_key = MPG_API_KEY;
    }
    
    /**
     * Récupère les produits nécessitant un transport depuis MPG
     * @return array Tableau de produits
     */
    public function getProductsNeedingTransport() {
        try {
            $db = getDB();
            
            // D'abord vérifier notre base de données locale pour les produits en cache
            $stmt = $db->prepare("SELECT * FROM marchandises WHERE statut = 'en_attente'");
            $stmt->execute();
            $local_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($local_products) > 0) {
                return $local_products;
            }
            
            // Si aucun en local, récupérer depuis l'API MPG
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url.'/products/needing-transport');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer '.$this->api_key,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code !== 200) {
                logError("MPG API Error: HTTP $http_code - $response");
                return [];
            }
            
            $products = json_decode($response, true);
            
            // Stocker en base de données locale
            foreach ($products as $product) {
                $stmt = $db->prepare("INSERT INTO marchandises 
                    (mpg_product_id, nom, description, poids_kg, dimensions, province_depart_id, 
                    adresse_ramassage, adresse_livraison, date_limite, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')");
                
                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    $product['description'],
                    $product['weight_kg'],
                    $product['dimensions'],
                    $product['province_id'],
                    $product['pickup_address'],
                    'Entrepôt MPG, Libreville', // Livraison par défaut à l'entrepôt MPG
                    $product['transport_deadline']
                ]);
            }
            
            return $products;
            
        } catch (PDOException $e) {
            logError("Database error: ".$e->getMessage());
            return [];
        } catch (Exception $e) {
            logError("MPG integration error: ".$e->getMessage());
            return [];
        }
    }
    
    /**
     * Met à jour le statut de transport d'un produit dans MPG
     * @param int $mpg_product_id ID du produit dans MPG
     * @param string $status Nouveau statut
     * @return bool Succès de l'opération
     */
    public function updateTransportStatus($mpg_product_id, $status) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url."/products/$mpg_product_id/transport-status");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer '.$this->api_key,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => $status]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            return $http_code === 200;
            
        } catch (Exception $e) {
            logError("MPG status update error: ".$e->getMessage());
            return false;
        }
    }
    
    /**
     * Synchronise les données de transport avec MPG
     * @return bool Succès de l'opération
     */
    public function syncTransportData() {
        try {
            $db = getDB();
            
            // Récupérer les livraisons qui doivent être synchronisées
            $stmt = $db->prepare("SELECT m.mpg_product_id, at.statut 
                                FROM marchandises m
                                JOIN affectations_transport at ON at.marchandise_id = m.id
                                WHERE m.mpg_product_id IS NOT NULL AND at.synced_with_mpg = 0");
            $stmt->execute();
            $shipments = $stmt->fetchAll();
            
            foreach ($shipments as $shipment) {
                $success = $this->updateTransportStatus($shipment['mpg_product_id'], $shipment['statut']);
                
                if ($success) {
                    // Marquer comme synchronisé
                    $stmt = $db->prepare("UPDATE affectations_transport SET synced_with_mpg = 1 WHERE marchandise_id = 
                                        (SELECT id FROM marchandises WHERE mpg_product_id = ?)");
                    $stmt->execute([$shipment['mpg_product_id']]);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            logError("Sync error: ".$e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère les détails d'un produit depuis MPG
     * @param int $mpg_product_id ID du produit dans MPG
     * @return array|false Détails du produit ou false en cas d'erreur
     */
    public function getProductDetails($mpg_product_id) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url."/products/$mpg_product_id");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer '.$this->api_key,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code !== 200) {
                logError("MPG API Error: HTTP $http_code - $response");
                return false;
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            logError("MPG product details error: ".$e->getMessage());
            return false;
        }
    }
}
?>