<?php
require_once 'config.php';
require_once 'db_connect.php';

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Traite un paiement pour une livraison
     * @param int $shipmentId ID de la livraison
     * @param float $amount Montant du paiement
     * @param string $method Méthode de paiement
     * @param string $transactionId ID de transaction
     * @return bool Succès de l'opération
     */
    public function processPayment($shipmentId, $amount, $method = 'mobile_money', $transactionId = null) {
        try {
            $this->db->beginTransaction();
            
            // Vérifier que la livraison existe et est payable
            $stmt = $this->db->prepare("SELECT m.id, ot.prix, ot.transporteur_id 
                                      FROM marchandises m
                                      JOIN offres_transport ot ON ot.marchandise_id = m.id
                                      WHERE m.id = ? AND m.statut = 'livre' AND ot.statut = 'accepte'");
            $stmt->execute([$shipmentId]);
            $shipment = $stmt->fetch();
            
            if (!$shipment) {
                throw new Exception("Livraison non trouvée ou non payable");
            }
            
            // Calculer les montants
            $platformFee = $shipment['prix'] * PLATFORM_FEE;
            $transporterAmount = $shipment['prix'] - $platformFee;
            
            // Enregistrer le paiement
            $stmt = $this->db->prepare("INSERT INTO paiements 
                                      (marchandise_id, montant_total, frais_plateforme, montant_transporteur, 
                                      methode, transaction_id, statut, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([
                $shipmentId,
                $shipment['prix'],
                $platformFee,
                $transporterAmount,
                $method,
                $transactionId
            ]);
            
            // Créditer le transporteur
            $stmt = $this->db->prepare("UPDATE transporteurs SET solde = solde + ? WHERE id = ?");
            $stmt->execute([$transporterAmount, $shipment['transporteur_id']]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            logError("Payment processing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initie un paiement mobile money
     * @param string $phone Numéro de téléphone
     * @param float $amount Montant
     * @param string $operator Opérateur (moov, airtel, etc.)
     * @return array|false Réponse de l'API ou false en cas d'erreur
     */
    public function initiateMobileMoneyPayment($phone, $amount, $operator = 'moov') {
        // En production, utiliser une API réelle comme Orange Money, MTN Mobile Money, etc.
        // Ceci est une simulation
        
        // Valider le numéro de téléphone
        if (!preg_match('/^(?:\+241|0)[0-9]{8}$/', $phone)) {
            return false;
        }
        
        // Simuler une réponse d'API
        return [
            'success' => true,
            'transaction_id' => 'MM' . uniqid(),
            'amount' => $amount,
            'phone' => $phone,
            'operator' => $operator,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Vérifie le statut d'un paiement mobile money
     * @param string $transactionId ID de transaction
     * @return array|false Statut du paiement ou false en cas d'erreur
     */
    public function checkMobileMoneyPayment($transactionId) {
        // Simuler une vérification
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'amount' => 10000, // Montant simulé
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Effectue un retrait pour un transporteur
     * @param int $transporterId ID du transporteur
     * @param float $amount Montant à retirer
     * @param string $method Méthode de retrait (mobile_money, bank)
     * @param string $account Compte de destination
     * @return bool Succès de l'opération
     */
    public function processWithdrawal($transporterId, $amount, $method = 'mobile_money', $account = '') {
        try {
            $this->db->beginTransaction();
            
            // Vérifier le solde du transporteur
            $stmt = $this->db->prepare("SELECT solde FROM transporteurs WHERE id = ?");
            $stmt->execute([$transporterId]);
            $transporter = $stmt->fetch();
            
            if (!$transporter || $transporter['solde'] < $amount) {
                throw new Exception("Solde insuffisant");
            }
            
            // Enregistrer le retrait
            $stmt = $this->db->prepare("INSERT INTO retraits 
                                      (transporteur_id, montant, methode, compte_destination, statut, created_at) 
                                      VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $transporterId,
                $amount,
                $method,
                $account
            ]);
            
            $withdrawalId = $this->db->lastInsertId();
            
            // Simuler le traitement du retrait (en production, appeler une API de paiement)
            sleep(2); // Simuler un délai de traitement
            
            // Mettre à jour le statut et déduire le solde
            $stmt = $this->db->prepare("UPDATE retraits SET statut = 'completed', processed_at = NOW() WHERE id = ?");
            $stmt->execute([$withdrawalId]);
            
            $stmt = $this->db->prepare("UPDATE transporteurs SET solde = solde - ? WHERE id = ?");
            $stmt->execute([$amount, $transporterId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            logError("Withdrawal failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère l'historique des paiements d'un transporteur
     * @param int $transporterId ID du transporteur
     * @param int $limit Nombre maximum de résultats
     * @return array Historique des paiements
     */
    public function getPaymentHistory($transporterId, $limit = 10) {
        $stmt = $this->db->prepare("SELECT p.*, m.nom AS marchandise_nom 
                                   FROM paiements p
                                   JOIN marchandises m ON m.id = p.marchandise_id
                                   JOIN offres_transport ot ON ot.marchandise_id = m.id
                                   WHERE ot.transporteur_id = ?
                                   ORDER BY p.created_at DESC
                                   LIMIT ?");
        $stmt->bindValue(1, $transporterId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère l'historique des retraits d'un transporteur
     * @param int $transporterId ID du transporteur
     * @param int $limit Nombre maximum de résultats
     * @return array Historique des retraits
     */
    public function getWithdrawalHistory($transporterId, $limit = 10) {
        $stmt = $this->db->prepare("SELECT * FROM retraits 
                                   WHERE transporteur_id = ?
                                   ORDER BY created_at DESC
                                   LIMIT ?");
        $stmt->bindValue(1, $transporterId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>