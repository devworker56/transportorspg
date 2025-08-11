<?php
require_once 'config.php';
require_once 'db_connect.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Envoie une notification à un utilisateur
     * @param int $userId ID de l'utilisateur
     * @param string $title Titre de la notification
     * @param string $message Message de la notification
     * @param string $type Type de notification (info, success, warning, danger)
     * @param string $url URL associée (optionnelle)
     * @return bool Succès de l'opération
     */
    public function send($userId, $title, $message, $type = 'info', $url = null) {
        $stmt = $this->db->prepare("INSERT INTO notifications 
                                   (user_id, title, message, type, url, is_read, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 0, NOW())");
        return $stmt->execute([$userId, $title, $message, $type, $url]);
    }
    
    /**
     * Marque une notification comme lue
     * @param int $notificationId ID de la notification
     * @return bool Succès de l'opération
     */
    public function markAsRead($notificationId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notificationId]);
    }
    
    /**
     * Récupère les notifications d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @param int $limit Nombre maximum de notifications à retourner
     * @param bool $unreadOnly Ne retourner que les non lues
     * @return array Tableau de notifications
     */
    public function getForUser($userId, $limit = 10, $unreadOnly = false) {
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Envoie une notification par email
     * @param string $email Adresse email
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email
     * @return bool Succès de l'opération
     */
    public function sendEmail($email, $subject, $body) {
        // En production, utiliser une bibliothèque comme PHPMailer ou un service externe
        $headers = "From: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $body, $headers);
    }
    
    /**
     * Notifie un transporteur lorsqu'une nouvelle livraison est disponible dans sa province
     * @param int $transporterId ID du transporteur
     * @param int $shipmentId ID de la livraison
     * @return bool Succès de l'opération
     */
    public function notifyNewShipment($transporterId, $shipmentId) {
        $db = getDB();
        
        // Récupérer les détails du transporteur
        $stmt = $db->prepare("SELECT u.email FROM users u JOIN transporteurs t ON t.user_id = u.id WHERE t.id = ?");
        $stmt->execute([$transporterId]);
        $transporter = $stmt->fetch();
        
        if (!$transporter) {
            return false;
        }
        
        // Récupérer les détails de la livraison
        $stmt = $db->prepare("SELECT m.nom, p.nom AS province FROM marchandises m 
                             JOIN provinces p ON p.id = m.province_depart_id 
                             WHERE m.id = ?");
        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch();
        
        if (!$shipment) {
            return false;
        }
        
        $title = "Nouvelle livraison disponible";
        $message = "Une nouvelle livraison ({$shipment['nom']}) est disponible dans la province {$shipment['province']}.";
        $url = "/transporteur/offres.php?marchandise_id=$shipmentId";
        
        // Envoyer la notification dans l'application
        $this->send($transporterId, $title, $message, 'info', $url);
        
        // Envoyer un email
        $emailSubject = "TPG - $title";
        $emailBody = "<h1>$title</h1>
                     <p>$message</p>
                     <p><a href='" . SITE_URL . "$url'>Voir la livraison</a></p>
                     <p>Cordialement,<br>L'équipe TPG</p>";
        
        return $this->sendEmail($transporter['email'], $emailSubject, $emailBody);
    }
    
    /**
     * Notifie un vendeur lorsque sa livraison a été acceptée
     * @param int $sellerId ID du vendeur
     * @param int $shipmentId ID de la livraison
     * @param int $transporterId ID du transporteur
     * @return bool Succès de l'opération
     */
    public function notifyShipmentAccepted($sellerId, $shipmentId, $transporterId) {
        $db = getDB();
        
        // Récupérer les détails du vendeur
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$sellerId]);
        $seller = $stmt->fetch();
        
        if (!$seller) {
            return false;
        }
        
        // Récupérer les détails de la livraison
        $stmt = $db->prepare("SELECT nom FROM marchandises WHERE id = ?");
        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch();
        
        if (!$shipment) {
            return false;
        }
        
        // Récupérer les détails du transporteur
        $stmt = $db->prepare("SELECT CONCAT(u.prenom, ' ', u.nom) AS name FROM transporteurs t 
                             JOIN users u ON u.id = t.user_id WHERE t.id = ?");
        $stmt->execute([$transporterId]);
        $transporter = $stmt->fetch();
        
        $title = "Votre livraison a été acceptée";
        $message = "Votre livraison {$shipment['nom']} a été acceptée par le transporteur {$transporter['name']}.";
        $url = "/mes-commandes.php?id=$shipmentId";
        
        // Envoyer la notification dans l'application
        $this->send($sellerId, $title, $message, 'success', $url);
        
        // Envoyer un email
        $emailSubject = "TPG - $title";
        $emailBody = "<h1>$title</h1>
                     <p>$message</p>
                     <p><a href='" . SITE_URL . "$url'>Voir la livraison</a></p>
                     <p>Cordialement,<br>L'équipe TPG</p>";
        
        return $this->sendEmail($seller['email'], $emailSubject, $emailBody);
    }
    
    /**
     * Notifie un transporteur lorsque son offre a été acceptée
     * @param int $transporterId ID du transporteur
     * @param int $shipmentId ID de la livraison
     * @return bool Succès de l'opération
     */
    public function notifyBidAccepted($transporterId, $shipmentId) {
        $db = getDB();
        
        // Récupérer les détails du transporteur
        $stmt = $db->prepare("SELECT u.email FROM users u JOIN transporteurs t ON t.user_id = u.id WHERE t.id = ?");
        $stmt->execute([$transporterId]);
        $transporter = $stmt->fetch();
        
        if (!$transporter) {
            return false;
        }
        
        // Récupérer les détails de la livraison
        $stmt = $db->prepare("SELECT nom FROM marchandises WHERE id = ?");
        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch();
        
        if (!$shipment) {
            return false;
        }
        
        $title = "Votre offre a été acceptée";
        $message = "Votre offre pour la livraison {$shipment['nom']} a été acceptée.";
        $url = "/transporteur/commandes.php?id=$shipmentId";
        
        // Envoyer la notification dans l'application
        $this->send($transporterId, $title, $message, 'success', $url);
        
        // Envoyer un email
        $emailSubject = "TPG - $title";
        $emailBody = "<h1>$title</h1>
                     <p>$message</p>
                     <p><a href='" . SITE_URL . "$url'>Voir la livraison</a></p>
                     <p>Cordialement,<br>L'équipe TPG</p>";
        
        return $this->sendEmail($transporter['email'], $emailSubject, $emailBody);
    }
    
    /**
     * Notifie un utilisateur lorsqu'un litige est créé
     * @param int $userId ID de l'utilisateur
     * @param int $disputeId ID du litige
     * @return bool Succès de l'opération
     */
    public function notifyDisputeCreated($userId, $disputeId) {
        $db = getDB();
        
        // Récupérer les détails de l'utilisateur
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Récupérer les détails du litige
        $stmt = $db->prepare("SELECT reason FROM litiges WHERE id = ?");
        $stmt->execute([$disputeId]);
        $dispute = $stmt->fetch();
        
        $title = "Nouveau litige créé";
        $message = "Un litige a été créé pour la raison: {$dispute['reason']}.";
        $url = "/litiges.php?id=$disputeId";
        
        // Envoyer la notification dans l'application
        $this->send($userId, $title, $message, 'warning', $url);
        
        // Envoyer un email
        $emailSubject = "TPG - $title";
        $emailBody = "<h1>$title</h1>
                     <p>$message</p>
                     <p><a href='" . SITE_URL . "$url'>Voir le litige</a></p>
                     <p>Cordialement,<br>L'équipe TPG</p>";
        
        return $this->sendEmail($user['email'], $emailSubject, $emailBody);
    }
}
?>