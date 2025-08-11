<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTransporter();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get transporter ID
$stmt = $db->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
$stmt->execute([$user_id]);
$transporter_id = $stmt->fetchColumn();

// Get specific shipment if ID is provided
if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT a.*, m.*, p.nom as province, 
        u.prenom as client_prenom, u.nom as client_nom, u.telephone as client_telephone
        FROM affectations_transport a
        JOIN marchandises m ON a.marchandise_id = m.id
        JOIN provinces p ON m.province_depart_id = p.id
        JOIN users u ON m.client_id = u.id
        WHERE a.id = ? AND a.transporteur_id = ?");
    $stmt->execute([$_GET['id'], $transporter_id]);
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shipment) {
        header("HTTP/1.1 404 Not Found");
        exit("Livraison non trouvée");
    }
}

// Get all shipments for this transporter
$stmt = $db->prepare("SELECT a.id, a.statut, a.date_depart, a.date_livraison,
    m.nom as marchandise_nom, m.poids_kg, p.nom as province
    FROM affectations_transport a
    JOIN marchandises m ON a.marchandise_id = m.id
    JOIN provinces p ON m.province_depart_id = p.id
    WHERE a.transporteur_id = ?
    ORDER BY 
        CASE WHEN a.statut IN ('en_preparation', 'en_route') THEN 0 ELSE 1 END,
        a.date_livraison DESC");
$stmt->execute([$transporter_id]);
$shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'start':
                $stmt = $db->prepare("UPDATE affectations_transport SET 
                    statut = 'en_route', date_depart = NOW()
                    WHERE id = ? AND transporteur_id = ?");
                $stmt->execute([$_POST['shipment_id'], $transporter_id]);
                break;
                
            case 'complete':
                $stmt = $db->prepare("UPDATE affectations_transport SET 
                    statut = 'livre', date_livraison = NOW()
                    WHERE id = ? AND transporteur_id = ?");
                $stmt->execute([$_POST['shipment_id'], $transporter_id]);
                
                // Update transporter stats
                $stmt = $db->prepare("UPDATE transporteurs SET 
                    livraisons_completees = livraisons_completees + 1
                    WHERE id = ?");
                $stmt->execute([$transporter_id]);
                break;
                
            case 'delay':
                $stmt = $db->prepare("UPDATE affectations_transport SET 
                    statut = 'retarde'
                    WHERE id = ? AND transporteur_id = ?");
                $stmt->execute([$_POST['shipment_id'], $transporter_id]);
                break;
        }
        
        $_SESSION['flash'] = "Statut mis à jour avec succès";
        header("Location: commandes.php?id=".$_POST['shipment_id']);
        exit();
    } catch (PDOException $e) {
        $error = "Erreur de mise à jour: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - TPG</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mes Commandes</h1>
                </div>
                
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <!-- Shipment List -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4><i class="fas fa-list me-2"></i>Mes Livraisons</h4>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($shipments)): ?>
                                    <div class="alert alert-info m-3">
                                        Vous n'avez aucune livraison actuellement.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($shipments as $s): ?>
                                            <a href="commandes.php?id=<?php echo $s['id']; ?>" 
                                               class="list-group-item list-group-item-action <?php echo isset($shipment) && $shipment['id'] == $s['id'] ? 'active' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($s['marchandise_nom']); ?></h5>
                                                    <span class="badge bg-<?php 
                                                        echo $s['statut'] === 'en_route' ? 'warning' : 
                                                            ($s['statut'] === 'livre' ? 'success' : 
                                                            ($s['statut'] === 'retarde' ? 'danger' : 'info'));
                                                    ?>">
                                                        <?php 
                                                        echo $s['statut'] === 'en_route' ? 'En transit' : 
                                                            ($s['statut'] === 'livre' ? 'Livré' : 
                                                            ($s['statut'] === 'retarde' ? 'Retardé' : 'En préparation'));
                                                        ?>
                                                    </span>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($s['province']); ?> → Libreville
                                                </p>
                                                <small>
                                                    <?php if ($s['date_depart']): ?>
                                                        <i class="fas fa-calendar-alt"></i> 
                                                        Départ: <?php echo date('d/m/Y', strtotime($s['date_depart'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Shipment Details -->
                        <?php if (isset($shipment)): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4>
                                        <i class="fas fa-box me-2"></i>
                                        Détails de la livraison #<?php echo $shipment['id']; ?>
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h5>Marchandise</h5>
                                            <p><strong>Nom:</strong> <?php echo htmlspecialchars($shipment['nom']); ?></p>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($shipment['description']); ?></p>
                                            <p><strong>Poids:</strong> <?php echo htmlspecialchars($shipment['poids_kg']); ?> kg</p>
                                            <p><strong>Dimensions:</strong> <?php echo htmlspecialchars($shipment['dimensions']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Client</h5>
                                            <p><strong>Nom:</strong> <?php echo htmlspecialchars($shipment['client_prenom'] . ' ' . htmlspecialchars($shipment['client_nom'])); ?></p>
                                            <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($shipment['client_telephone']); ?></p>
                                            
                                            <h5 class="mt-4">Livraison</h5>
                                            <p><strong>Province:</strong> <?php echo htmlspecialchars($shipment['province']); ?></p>
                                            <p><strong>Ramassage:</strong> <?php echo htmlspecialchars($shipment['adresse_ramassage']); ?></p>
                                            <p><strong>Livraison:</strong> <?php echo htmlspecialchars($shipment['adresse_livraison']); ?></p>
                                            <p><strong>Date limite:</strong> <?php echo date('d/m/Y', strtotime($shipment['date_limite'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5>Statut</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="alert alert-<?php 
                                                        echo $shipment['statut'] === 'en_route' ? 'warning' : 
                                                            ($shipment['statut'] === 'livre' ? 'success' : 
                                                            ($shipment['statut'] === 'retarde' ? 'danger' : 'info'));
                                                    ?>">
                                                        <h5 class="alert-heading">
                                                            <?php 
                                                            echo $shipment['statut'] === 'en_route' ? 'En transit' : 
                                                                ($shipment['statut'] === 'livre' ? 'Livré' : 
                                                                ($shipment['statut'] === 'retarde' ? 'Retardé' : 'En préparation'));
                                                            ?>
                                                        </h5>
                                                        <?php if ($shipment['date_depart']): ?>
                                                            <p>
                                                                <i class="fas fa-calendar-alt"></i> 
                                                                Départ: <?php echo date('d/m/Y H:i', strtotime($shipment['date_depart'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if ($shipment['date_livraison']): ?>
                                                            <p>
                                                                <i class="fas fa-check-circle"></i> 
                                                                Livré: <?php echo date('d/m/Y H:i', strtotime($shipment['date_livraison'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($shipment['statut'] === 'en_preparation'): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="shipment_id" value="<?php echo $shipment['id']; ?>">
                                                            <input type="hidden" name="action" value="start">
                                                            <button type="submit" class="btn btn-success w-100">
                                                                <i class="fas fa-play me-1"></i> Démarrer la livraison
                                                            </button>
                                                        </form>
                                                    <?php elseif ($shipment['statut'] === 'en_route'): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="shipment_id" value="<?php echo $shipment['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                                                <i class="fas fa-check me-1"></i> Marquer comme livré
                                                            </button>
                                                        </form>
                                                        <form method="POST">
                                                            <input type="hidden" name="shipment_id" value="<?php echo $shipment['id']; ?>">
                                                            <input type="hidden" name="action" value="delay">
                                                            <button type="submit" class="btn btn-warning w-100">
                                                                <i class="fas fa-clock me-1"></i> Signaler un retard
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5>Informations de paiement</h5>
                                                </div>
                                                <div class="card-body">
                                                    <p><strong>Prix convenu:</strong> <?php echo number_format($shipment['prix'], 0); ?> XAF</p>
                                                    <p><strong>Statut paiement:</strong> 
                                                        <?php if ($shipment['statut_paiement'] === 'complete'): ?>
                                                            <span class="badge bg-success">Payé</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">En attente</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if ($shipment['statut'] === 'livre' && $shipment['statut_paiement'] !== 'complete'): ?>
                                                        <div class="alert alert-info mt-3">
                                                            <p>Le paiement sera traité dans les 24-48 heures.</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5 class="alert-heading">Sélectionnez une livraison</h5>
                                <p>Cliquez sur une livraison dans la liste à gauche pour voir les détails.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/commandes.js"></script>
</body>
</html>