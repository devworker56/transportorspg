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
     * Get products needing transport from MPG
     * @return array Array of products
     */
    public function getProductsNeedingTransport() {
        try {
            $db = getDB();
            
            // First check local cache
            $stmt = $db->prepare("
                SELECT * FROM transport_offers 
                WHERE status = 'pending' 
                AND expires_at > NOW()
            ");
            $stmt->execute();
            $localOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($localOffers) > 0) {
                return $this->enrichWithProductDetails($localOffers, $db);
            }
            
            // If nothing in local cache, fetch from MPG API
            $products = $this->fetchFromMPG();
            
            if (empty($products)) {
                return [];
            }
            
            // Store in local database
            $this->storeTransportOffers($products, $db);
            
            return $products;
            
        } catch (PDOException $e) {
            logError("Database error in getProductsNeedingTransport: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            logError("MPG integration error: " . $e->getMessage());
            return [];
        }
    }
    
    private function fetchFromMPG() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url . '/products/needing-transport');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("MPG API returned HTTP $httpCode: $response");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !$data['success']) {
            throw new Exception("Invalid MPG API response");
        }
        
        return $data['data'];
    }
    
    private function storeTransportOffers($products, $db) {
        $db->beginTransaction();
        
        try {
            foreach ($products as $product) {
                $stmt = $db->prepare("
                    INSERT INTO transport_offers 
                    (mpg_product_id, product_name, province, weight_kg, dimensions, 
                    pickup_address, destination, deadline, status, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 2 DAY))
                ");
                
                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    $product['province_name'],
                    $product['weight_kg'],
                    $product['dimensions'],
                    $product['pickup_address'],
                    $product['destination'],
                    $product['transport_deadline']
                ]);
            }
            
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    private function enrichWithProductDetails($offers, $db) {
        $enriched = [];
        
        foreach ($offers as $offer) {
            $enriched[] = [
                'id' => $offer['id'],
                'mpg_product_id' => $offer['mpg_product_id'],
                'nom' => $offer['product_name'],
                'province_depart_id' => $offer['province'],
                'poids_kg' => $offer['weight_kg'],
                'dimensions' => $offer['dimensions'],
                'adresse_ramassage' => $offer['pickup_address'],
                'date_limite' => $offer['deadline'],
                'destination' => $offer['destination']
            ];
        }
        
        return $enriched;
    }
    
    /**
     * Update transport status in MPG
     * @param int $mpgProductId MPG product ID
     * @param string $status New status
     * @param int $transporterId TPG transporter ID
     * @return bool Success status
     */
    public function updateTransportStatus($mpgProductId, $status, $transporterId) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url . '/transport/update-status');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'product_id' => $mpgProductId,
                'status' => $status,
                'transporter_id' => $transporterId
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                logError("MPG status update failed: HTTP $httpCode - $response");
                return false;
            }
            
            $data = json_decode($response, true);
            return $data['success'] ?? false;
            
        } catch (Exception $e) {
            logError("MPG status update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get product details from MPG
     * @param int $mpgProductId MPG product ID
     * @return array|false Product details or false on error
     */
    public function getProductDetails($mpgProductId) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->mpg_api_url . "/products/$mpgProductId");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                return false;
            }
            
            $data = json_decode($response, true);
            return $data['data'] ?? false;
            
        } catch (Exception $e) {
            logError("MPG product details error: " . $e->getMessage());
            return false;
        }
    }
}