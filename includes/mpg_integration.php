<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

class MPGIntegration {
    private $mpg_api_url;
    private $api_key;
    private $db;
    
    public function __construct() {
        if (!defined('MPG_API_URL') || !defined('MPG_API_KEY')) {
            throw new RuntimeException('MPG API configuration missing');
        }

        $this->mpg_api_url = rtrim(MPG_API_URL, '/');
        $this->api_key = MPG_API_KEY;
        $this->db = Database::getConnection();
    }
    
    /**
     * Get products needing transport
     * @return array Array of products with transport details
     */
    public function getProductsNeedingTransport() {
        try {
            // Check local cache first
            $localOffers = $this->getCachedOffers();
            if (!empty($localOffers)) {
                return $this->enrichWithProductDetails($localOffers);
            }

            // Fetch from API if no local offers
            $products = $this->fetchFromMPG();
            if (!empty($products)) {
                $this->storeTransportOffers($products);
                return $products;
            }

            return [];
        } catch (PDOException $e) {
            $this->logError("Database error in getProductsNeedingTransport: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            $this->logError("MPG integration error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update transport status in MPG system
     * @param int $mpgProductId MPG product ID
     * @param string $status New status (accepted|rejected|completed)
     * @param int $transporterId Local transporter ID
     * @param string|null $notes Additional notes
     * @return bool True on success
     */
    public function updateTransportStatus(
        int $mpgProductId, 
        string $status, 
        int $transporterId, 
        ?string $notes = null
    ): bool {
        $validStatuses = ['accepted', 'rejected', 'completed', 'in_progress'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }

        try {
            // Prepare payload
            $payload = [
                'product_id' => $mpgProductId,
                'status' => $status,
                'transporter_id' => $transporterId,
                'notes' => $notes,
                'timestamp' => date('c')
            ];

            // Update local database first
            $this->updateLocalOfferStatus($mpgProductId, $status);

            // Send to MPG API
            $response = $this->makeApiRequest(
                '/transport/update-status',
                'PUT',
                $payload
            );

            // Log the update
            $this->logStatusUpdate($mpgProductId, $status, $transporterId);

            return $response['success'] ?? false;

        } catch (Exception $e) {
            $this->logError("Status update failed for product $mpgProductId: " . $e->getMessage());
            return false;
        }
    }

    // ============ PRIVATE METHODS ============

    private function getCachedOffers(): array {
        $stmt = $this->db->prepare("
            SELECT * FROM transport_offers 
            WHERE status = 'pending' 
            AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchFromMPG(): array {
        $response = $this->makeApiRequest('/products/needing-transport');
        
        if (empty($response['data'])) {
            return [];
        }

        return array_map([$this, 'formatProduct'], $response['data']);
    }

    private function makeApiRequest(
        string $endpoint,
        string $method = 'GET',
        array $data = []
    ): array {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize CURL');
        }

        $url = $this->mpg_api_url . $endpoint;
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("CURL error: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("API request failed with HTTP $httpCode");
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON response');
        }

        return $result;
    }

    private function storeTransportOffers(array $products): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO transport_offers (
                    mpg_product_id, product_name, province, weight_kg, 
                    dimensions, pickup_address, destination, deadline, 
                    status, expires_at
                ) VALUES (
                    :product_id, :name, :province, :weight, 
                    :dimensions, :address, :destination, :deadline,
                    'pending', DATE_ADD(NOW(), INTERVAL 2 DAY)
                ON DUPLICATE KEY UPDATE
                    expires_at = VALUES(expires_at)
            ");

            foreach ($products as $product) {
                $stmt->execute([
                    ':product_id' => $product['id'],
                    ':name' => $product['name'],
                    ':province' => $product['province_name'],
                    ':weight' => $product['weight_kg'],
                    ':dimensions' => $product['dimensions'],
                    ':address' => $product['pickup_address'],
                    ':destination' => $product['destination'],
                    ':deadline' => $product['transport_deadline']
                ]);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function updateLocalOfferStatus(int $productId, string $status): void {
        $stmt = $this->db->prepare("
            UPDATE transport_offers 
            SET status = :status,
                updated_at = NOW()
            WHERE mpg_product_id = :product_id
        ");
        $stmt->execute([
            ':status' => $status,
            ':product_id' => $productId
        ]);
    }

    private function logStatusUpdate(int $productId, string $status, int $transporterId): void {
        $stmt = $this->db->prepare("
            INSERT INTO transport_logs (
                product_id, transporter_id, status, created_at
            ) VALUES (
                :product_id, :transporter_id, :status, NOW()
            )
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':transporter_id' => $transporterId,
            ':status' => $status
        ]);
    }

    private function formatProduct(array $product): array {
        return [
            'id' => $product['id'] ?? 0,
            'name' => htmlspecialchars($product['name'] ?? ''),
            'province_name' => htmlspecialchars($product['province_name'] ?? ''),
            'weight_kg' => (float)($product['weight_kg'] ?? 0),
            'dimensions' => htmlspecialchars($product['dimensions'] ?? ''),
            'pickup_address' => htmlspecialchars($product['pickup_address'] ?? ''),
            'destination' => htmlspecialchars($product['destination'] ?? ''),
            'transport_deadline' => $product['transport_deadline'] ?? date('Y-m-d H:i:s')
        ];
    }

    private function enrichWithProductDetails(array $offers): array {
        return array_map(function($offer) {
            return [
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
        }, $offers);
    }

    private function logError(string $message): void {
        $logFile = __DIR__ . '/../logs/mpg_errors.log';
        $message = sprintf(
            "[%s] %s\n",
            date('Y-m-d H:i:s'),
            $message
        );
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}