<?php
require_once 'config.php';
require_once 'db_connect.php';

/**
 * Formate une date pour l'affichage
 * @param string $date Date à formater
 * @param bool $showTime Afficher l'heure
 * @return string Date formatée
 */
function formatDate($date, $showTime = false) {
    if (empty($date) || $date === '0000-00-00') {
        return 'N/A';
    }
    
    $format = $showTime ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($date));
}

/**
 * Formate une date avec l'heure
 * @param string $date Date à formater
 * @return string Date formatée avec heure
 */
function formatDateTime($date) {
    return formatDate($date, true);
}

/**
 * Formate un montant en devise
 * @param float $amount Montant à formater
 * @return string Montant formaté
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

/**
 * Récupère le nom d'une province par son ID
 * @param int $provinceId ID de la province
 * @return string Nom de la province
 */
function getProvinceName($provinceId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT nom FROM provinces WHERE id = ?");
    $stmt->execute([$provinceId]);
    $province = $stmt->fetch();
    
    return $province ? $province['nom'] : 'Inconnue';
}

/**
 * Récupère toutes les provinces
 * @return array Tableau des provinces [id => nom]
 */
function getProvinces() {
    $db = getDB();
    $stmt = $db->query("SELECT id, nom FROM provinces ORDER BY nom");
    $provinces = $stmt->fetchAll();
    
    $result = [];
    foreach ($provinces as $province) {
        $result[$province['id']] = $province['nom'];
    }
    
    return $result;
}

/**
 * Récupère le texte du statut d'un transporteur
 * @param string $status Statut
 * @return string Texte du statut
 */
function getTransporterStatusText($status) {
    $statuses = [
        'en_attente' => 'En attente',
        'approuve' => 'Approuvé',
        'rejete' => 'Rejeté',
        'banni' => 'Banni'
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * Récupère le texte du statut d'une livraison
 * @param string $status Statut
 * @return string Texte du statut
 */
function getShipmentStatusText($status) {
    $statuses = [
        'en_attente' => 'En attente',
        'en_preparation' => 'En préparation',
        'en_route' => 'En route',
        'livre' => 'Livré',
        'retarde' => 'Retardé',
        'annule' => 'Annulé'
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * Met à jour le statut d'un transporteur
 * @param int $transporterId ID du transporteur
 * @param string $status Nouveau statut
 * @return bool Succès de l'opération
 */
function updateTransporterStatus($transporterId, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE transporteurs SET statut = ? WHERE id = ?");
    return $stmt->execute([$status, $transporterId]);
}

/**
 * Met à jour le statut d'une livraison
 * @param int $shipmentId ID de la livraison
 * @param string $status Nouveau statut
 * @param string $notes Notes supplémentaires
 * @return bool Succès de l'opération
 */
function updateShipmentStatus($shipmentId, $status, $notes = '') {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour le statut de la marchandise
        $stmt = $db->prepare("UPDATE marchandises SET statut = ? WHERE id = ?");
        $stmt->execute([$status, $shipmentId]);
        
        // Mettre à jour le statut de l'affectation
        $stmt = $db->prepare("UPDATE affectations_transport SET statut = ? WHERE marchandise_id = ?");
        $stmt->execute([$status, $shipmentId]);
        
        // Si la livraison est marquée comme livrée, mettre à jour la date de livraison
        if ($status === 'livre') {
            $stmt = $db->prepare("UPDATE affectations_transport SET date_livraison = NOW() WHERE marchandise_id = ?");
            $stmt->execute([$shipmentId]);
        }
        
        // Ajouter une note si fournie
        if (!empty($notes)) {
            $stmt = $db->prepare("INSERT INTO notes_livraison (marchandise_id, note, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$shipmentId, $notes]);
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        logError("Failed to update shipment status: " . $e->getMessage());
        return false;
    }
}

/**
 * Résout un litige
 * @param int $disputeId ID du litige
 * @param string $resolution Décision de résolution
 * @param string $notes Notes supplémentaires
 * @return bool Succès de l'opération
 */
function resolveDispute($disputeId, $resolution, $notes = '') {
    $db = getDB();
    $stmt = $db->prepare("UPDATE litiges SET statut = 'resolu', resolution = ?, resolution_notes = ?, resolved_at = NOW() WHERE id = ?");
    return $stmt->execute([$resolution, $notes, $disputeId]);
}

/**
 * Récupère les statistiques pour le tableau de bord
 * @return array Tableau de statistiques
 */
function getDashboardStats() {
    $db = getDB();
    
    $stats = [
        'total_transporters' => 0,
        'pending_transporters' => 0,
        'active_shipments' => 0,
        'completed_shipments' => 0,
        'revenue' => 0,
        'disputes' => 0
    ];
    
    // Total des transporteurs
    $stmt = $db->query("SELECT COUNT(*) FROM transporteurs");
    $stats['total_transporters'] = $stmt->fetchColumn();
    
    // Transporteurs en attente
    $stmt = $db->query("SELECT COUNT(*) FROM transporteurs WHERE statut = 'en_attente'");
    $stats['pending_transporters'] = $stmt->fetchColumn();
    
    // Livraisons actives
    $stmt = $db->query("SELECT COUNT(*) FROM affectations_transport WHERE statut IN ('en_preparation', 'en_route')");
    $stats['active_shipments'] = $stmt->fetchColumn();
    
    // Livraisons complétées ce mois
    $stmt = $db->query("SELECT COUNT(*) FROM affectations_transport 
                        WHERE statut = 'livre' AND MONTH(date_livraison) = MONTH(CURRENT_DATE())");
    $stats['completed_shipments'] = $stmt->fetchColumn();
    
    // Revenus ce mois
    $stmt = $db->query("SELECT SUM(prix * " . PLATFORM_FEE . ") FROM offres_transport 
                        WHERE statut = 'accepte' AND MONTH(created_at) = MONTH(CURRENT_DATE())");
    $stats['revenue'] = $stmt->fetchColumn() ?? 0;
    
    // Litiges non résolus
    $stmt = $db->query("SELECT COUNT(*) FROM litiges WHERE statut = 'open'");
    $stats['disputes'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Récupère les activités récentes
 * @param int $limit Nombre maximum d'activités à retourner
 * @return array Tableau d'activités
 */
function getRecentActivities($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM activites ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Log une activité
 * @param string $action Description de l'action
 * @param string $details Détails supplémentaires
 * @return bool Succès de l'opération
 */
function logActivity($action, $details = '') {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO activites (user_id, action, details) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $action, $details]);
}

/**
 * Récupère les transporteurs selon un filtre
 * @param string $filter Filtre (all, pending, approved, banned)
 * @return array Tableau de transporteurs
 */
function getTransporters($filter = 'all') {
    $db = getDB();
    
    $query = "SELECT t.*, u.prenom, u.nom, u.email, u.telephone, 
              GROUP_CONCAT(p.nom SEPARATOR ',') AS provinces
              FROM transporteurs t
              JOIN users u ON t.user_id = u.id
              LEFT JOIN routes_transporteur rt ON rt.transporteur_id = t.id
              LEFT JOIN provinces p ON p.id = rt.province_depart_id
              WHERE 1=1";
    
    switch ($filter) {
        case 'pending':
            $query .= " AND t.statut = 'en_attente'";
            break;
        case 'approved':
            $query .= " AND t.statut = 'approuve'";
            break;
        case 'banned':
            $query .= " AND t.statut = 'banni'";
            break;
    }
    
    $query .= " GROUP BY t.id
                ORDER BY t.created_at DESC";
    
    $stmt = $db->query($query);
    return $stmt->fetchAll();
}

/**
 * Récupère les livraisons selon des filtres
 * @param string $filter Filtre de statut
 * @param string $province Filtre de province
 * @param int|null $transporterId Filtre de transporteur
 * @param string $search Terme de recherche
 * @return array Tableau de livraisons
 */
function getShipments($filter = 'all', $province = 'all', $transporterId = null, $search = '') {
    $db = getDB();
    
    $query = "SELECT m.id, m.nom AS nom_marchandise, m.poids_kg, m.dimensions, 
              m.adresse_ramassage, m.adresse_livraison, m.date_limite, m.statut,
              p.nom AS province_depart, 
              t.id AS transporteur_id, CONCAT(u.prenom, ' ', u.nom) AS transporteur_nom, 
              t.type_vehicule, 
              at.date_depart, at.date_livraison, 
              ot.prix
              FROM marchandises m
              JOIN provinces p ON m.province_depart_id = p.id
              LEFT JOIN affectations_transport at ON at.marchandise_id = m.id
              LEFT JOIN offres_transport ot ON ot.id = at.offre_id
              LEFT JOIN transporteurs t ON t.id = ot.transporteur_id
              LEFT JOIN users u ON u.id = t.user_id
              WHERE 1=1";
    
    // Filtre par statut
    switch ($filter) {
        case 'active':
            $query .= " AND at.statut IN ('en_preparation', 'en_route')";
            break;
        case 'completed':
            $query .= " AND at.statut = 'livre'";
            break;
        case 'pending':
            $query .= " AND m.statut = 'en_attente'";
            break;
        case 'preparation':
            $query .= " AND at.statut = 'en_preparation'";
            break;
        case 'transit':
            $query .= " AND at.statut = 'en_route'";
            break;
        case 'delivered':
            $query .= " AND at.statut = 'livre'";
            break;
        case 'delayed':
            $query .= " AND at.statut = 'retarde'";
            break;
        case 'canceled':
            $query .= " AND at.statut = 'annule'";
            break;
    }
    
    // Filtre par province
    if ($province !== 'all') {
        $query .= " AND m.province_depart_id = " . intval($province);
    }
    
    // Filtre par transporteur
    if ($transporterId !== null) {
        $query .= " AND t.id = " . intval($transporterId);
    }
    
    // Filtre par recherche
    if (!empty($search)) {
        $search = $db->quote('%' . $search . '%');
        $query .= " AND (m.nom LIKE $search OR CONCAT(u.prenom, ' ', u.nom) LIKE $search)";
    }
    
    $query .= " ORDER BY m.date_limite ASC";
    
    $stmt = $db->query($query);
    return $stmt->fetchAll();
}

/**
 * Récupère les litiges
 * @return array Tableau de litiges
 */
function getDisputes() {
    $db = getDB();
    
    $query = "SELECT d.id, d.shipment_id, d.reason, d.details, d.status, 
              d.created_at, d.resolved_at, d.resolution, d.resolution_notes,
              m.nom AS product_name,
              u.id AS user_id, CONCAT(u.prenom, ' ', u.nom) AS user_name, u.role AS user_role
              FROM litiges d
              JOIN marchandises m ON m.id = d.shipment_id
              JOIN users u ON u.id = d.user_id
              ORDER BY d.created_at DESC";
    
    $stmt = $db->query($query);
    return $stmt->fetchAll();
}

/**
 * Récupère les données pour les rapports
 * @param string $reportType Type de rapport
 * @param string $startDate Date de début
 * @param string $endDate Date de fin
 * @param string $province Filtre de province
 * @return array Données du rapport
 */
function getReportData($reportType, $startDate, $endDate, $province = 'all') {
    $db = getDB();
    $data = [
        'total_shipments' => 0,
        'completed_shipments' => 0,
        'total_revenue' => 0,
        'platform_earnings' => 0,
        'active_transporters' => 0,
        'new_transporters' => 0,
        'chart_labels' => [],
        'total_data' => [],
        'completed_data' => [],
        'province_labels' => [],
        'province_data' => [],
        'top_transporters' => [],
        'shipment_details' => []
    ];
    
    // Dates de début et fin
    $startDate = $db->quote($startDate);
    $endDate = $db->quote($endDate);
    
    // Total des livraisons
    $query = "SELECT COUNT(*) FROM marchandises 
              WHERE created_at BETWEEN $startDate AND $endDate";
    $data['total_shipments'] = $db->query($query)->fetchColumn();
    
    // Livraisons complétées
    $query = "SELECT COUNT(*) FROM affectations_transport 
              WHERE statut = 'livre' AND date_livraison BETWEEN $startDate AND $endDate";
    $data['completed_shipments'] = $db->query($query)->fetchColumn();
    
    // Revenus totaux
    $query = "SELECT SUM(prix) FROM offres_transport 
              WHERE statut = 'accepte' AND created_at BETWEEN $startDate AND $endDate";
    $data['total_revenue'] = $db->query($query)->fetchColumn() ?? 0;
    
    // Gains de la plateforme
    $data['platform_earnings'] = $data['total_revenue'] * PLATFORM_FEE;
    
    // Transporteurs actifs
    $query = "SELECT COUNT(DISTINCT transporteur_id) FROM affectations_transport 
              WHERE date_depart BETWEEN $startDate AND $endDate";
    $data['active_transporters'] = $db->query($query)->fetchColumn();
    
    // Nouveaux transporteurs
    $query = "SELECT COUNT(*) FROM transporteurs 
              WHERE created_at BETWEEN $startDate AND $endDate";
    $data['new_transporters'] = $db->query($query)->fetchColumn();
    
    // Données pour le graphique selon le type de rapport
    switch ($reportType) {
        case 'weekly':
            // Données hebdomadaires
            $query = "SELECT 
                      YEARWEEK(created_at) AS week,
                      COUNT(*) AS total,
                      SUM(CASE WHEN m.statut = 'livre' THEN 1 ELSE 0 END) AS completed
                      FROM marchandises m
                      WHERE m.created_at BETWEEN $startDate AND $endDate
                      GROUP BY YEARWEEK(created_at)
                      ORDER BY YEARWEEK(created_at)";
            
            $stmt = $db->query($query);
            $weeklyData = $stmt->fetchAll();
            
            foreach ($weeklyData as $week) {
                $data['chart_labels'][] = 'S' . substr($week['week'], 4);
                $data['total_data'][] = $week['total'];
                $data['completed_data'][] = $week['completed'];
            }
            break;
            
        case 'daily':
            // Données quotidiennes
            $query = "SELECT 
                      DATE(created_at) AS day,
                      COUNT(*) AS total,
                      SUM(CASE WHEN m.statut = 'livre' THEN 1 ELSE 0 END) AS completed
                      FROM marchandises m
                      WHERE m.created_at BETWEEN $startDate AND $endDate
                      GROUP BY DATE(created_at)
                      ORDER BY DATE(created_at)";
            
            $stmt = $db->query($query);
            $dailyData = $stmt->fetchAll();
            
            foreach ($dailyData as $day) {
                $data['chart_labels'][] = date('d/m', strtotime($day['day']));
                $data['total_data'][] = $day['total'];
                $data['completed_data'][] = $day['completed'];
            }
            break;
            
        default:
            // Données mensuelles par défaut
            $query = "SELECT 
                      DATE_FORMAT(created_at, '%Y-%m') AS month,
                      COUNT(*) AS total,
                      SUM(CASE WHEN m.statut = 'livre' THEN 1 ELSE 0 END) AS completed
                      FROM marchandises m
                      WHERE m.created_at BETWEEN $startDate AND $endDate
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY DATE_FORMAT(created_at, '%Y-%m')";
            
            $stmt = $db->query($query);
            $monthlyData = $stmt->fetchAll();
            
            foreach ($monthlyData as $month) {
                $data['chart_labels'][] = date('M Y', strtotime($month['month'] . '-01'));
                $data['total_data'][] = $month['total'];
                $data['completed_data'][] = $month['completed'];
            }
    }
    
    // Répartition par province
    $query = "SELECT p.nom, COUNT(*) AS count 
              FROM marchandises m
              JOIN provinces p ON p.id = m.province_depart_id
              WHERE m.created_at BETWEEN $startDate AND $endDate
              GROUP BY p.nom
              ORDER BY count DESC";
    
    $stmt = $db->query($query);
    $provinceData = $stmt->fetchAll();
    
    foreach ($provinceData as $province) {
        $data['province_labels'][] = $province['nom'];
        $data['province_data'][] = $province['count'];
    }
    
    // Top transporteurs
    $query = "SELECT 
              t.id, CONCAT(u.prenom, ' ', u.nom) AS name,
              COUNT(*) AS shipments,
              SUM(ot.prix) AS earnings,
              AVG(r.rating) AS rating
              FROM affectations_transport at
              JOIN transporteurs t ON t.id = at.transporteur_id
              JOIN users u ON u.id = t.user_id
              JOIN offres_transport ot ON ot.id = at.offre_id
              LEFT JOIN evaluations r ON r.transporteur_id = t.id
              WHERE at.date_depart BETWEEN $startDate AND $endDate
              GROUP BY t.id, u.prenom, u.nom
              ORDER BY shipments DESC
              LIMIT 5";
    
    $stmt = $db->query($query);
    $data['top_transporters'] = $stmt->fetchAll();
    
    // Détails des livraisons
    $query = "SELECT 
              m.id, DATE(m.created_at) AS date, m.nom AS product_name,
              p.nom AS province, 
              CONCAT(u.prenom, ' ', u.nom) AS transporter_name,
              at.statut AS status, ot.prix AS price
              FROM marchandises m
              JOIN provinces p ON p.id = m.province_depart_id
              LEFT JOIN affectations_transport at ON at.marchandise_id = m.id
              LEFT JOIN offres_transport ot ON ot.id = at.offre_id
              LEFT JOIN transporteurs t ON t.id = ot.transporteur_id
              LEFT JOIN users u ON u.id = t.user_id
              WHERE m.created_at BETWEEN $startDate AND $endDate
              ORDER BY m.created_at DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    $data['shipment_details'] = $stmt->fetchAll();
    
    return $data;
}

/**
 * Récupère les paramètres de la plateforme
 * @return array Tableau de paramètres
 */
function getSettings() {
    return [
        'platform_fee' => PLATFORM_FEE,
        'min_transporter_fee' => MIN_TRANSPORTER_FEE,
        'max_shipment_weight' => MAX_SHIPMENT_WEIGHT,
        'default_deadline_days' => DEFAULT_DEADLINE_DAYS,
        'notify_new_shipments' => true,
        'notify_disputes' => true,
        'admin_email' => ADMIN_EMAIL,
        'support_phone' => SUPPORT_PHONE
    ];
}

/**
 * Met à jour les paramètres de la plateforme
 * @param array $settings Nouveaux paramètres
 * @return bool Succès de l'opération
 */
function updateSettings($settings) {
    // Dans une vraie application, on sauvegarderait ces paramètres en base de données
    // Pour cet exemple, on simule juste la mise à jour
    return true;
}

/**
 * Enregistre un nouvel utilisateur
 * @param array $data Données de l'utilisateur
 * @return bool|string True en cas de succès, message d'erreur sinon
 */
function registerUser($data) {
    $db = getDB();
    
    // Validation des données
    if (empty($data['prenom']) {
        return "Le prénom est requis";
    }
    
    if (empty($data['nom'])) {
        return "Le nom est requis";
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return "Email invalide";
    }
    
    if (empty($data['telephone'])) {
        return "Le téléphone est requis";
    }
    
    if (empty($data['password']) || strlen($data['password']) < 6) {
        return "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return "Les mots de passe ne correspondent pas";
    }
    
    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    
    if ($stmt->fetch()) {
        return "Cet email est déjà utilisé";
    }
    
    try {
        $db->beginTransaction();
        
        // Créer l'utilisateur
        $stmt = $db->prepare("INSERT INTO users (prenom, nom, email, telephone, password, role) 
                             VALUES (?, ?, ?, ?, ?, 'client')");
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $data['prenom'],
            $data['nom'],
            $data['email'],
            $data['telephone'],
            $hashedPassword
        ]);
        
        $userId = $db->lastInsertId();
        
        // Si c'est un transporteur, créer le profil transporteur
        if (isset($data['type_vehicule'])) {
            $stmt = $db->prepare("INSERT INTO transporteurs 
                                 (user_id, type_vehicule, capacite_kg, plaque_immatriculation, permis_numero) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $data['type_vehicule'],
                $data['capacite_kg'],
                $data['plaque_immatriculation'],
                $data['permis_numero']
            ]);
            
            // Ajouter les provinces desservies
            if (!empty($data['provinces'])) {
                $transporteurId = $db->lastInsertId();
                
                foreach ($data['provinces'] as $provinceId) {
                    $stmt = $db->prepare("INSERT INTO routes_transporteur 
                                         (transporteur_id, province_depart_id, province_arrivee_id) 
                                         VALUES (?, ?, (SELECT id FROM provinces WHERE nom = 'Estuaire'))");
                    $stmt->execute([$transporteurId, $provinceId]);
                }
            }
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        logError("User registration failed: " . $e->getMessage());
        return "Une erreur est survenue lors de l'inscription";
    }
}

/**
 * Enregistre un nouveau transporteur
 * @param array $data Données du transporteur
 * @return bool|string True en cas de succès, message d'erreur sinon
 */
function registerTransporter($data) {
    return registerUser($data);
}

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si connecté, false sinon
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est un administrateur
 * @return bool True si admin, false sinon
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur est un transporteur
 * @return bool True si transporteur, false sinon
 */
function isTransporter() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'transporteur';
}

/**
 * Vérifie si l'utilisateur est un client
 * @return bool True si client, false sinon
 */
function isClient() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client';
}

/**
 * Redirige l'utilisateur s'il n'est pas connecté
 * @param string $redirectUrl URL de redirection
 */
function requireLogin($redirectUrl = '/connexion.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Redirige l'utilisateur s'il n'est pas admin
 * @param string $redirectUrl URL de redirection
 */
function requireAdmin($redirectUrl = '/connexion.php') {
    requireLogin($redirectUrl);
    
    if (!isAdmin()) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Redirige l'utilisateur s'il n'est pas transporteur
 * @param string $redirectUrl URL de redirection
 */
function requireTransporter($redirectUrl = '/connexion.php') {
    requireLogin($redirectUrl);
    
    if (!isTransporter()) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Redirige l'utilisateur s'il n'est pas client
 * @param string $redirectUrl URL de redirection
 */
function requireClient($redirectUrl = '/connexion.php') {
    requireLogin($redirectUrl);
    
    if (!isClient()) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Nettoie les données utilisateur
 * @param string $data Donnée à nettoyer
 * @return string Donnée nettoyée
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>