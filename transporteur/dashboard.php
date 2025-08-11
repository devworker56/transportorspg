<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTransporter();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get transporter info
$stmt = $db->prepare("SELECT t.*, u.prenom, u.nom, u.email, u.telephone 
    FROM transporteurs t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$transporter = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active shipments
$stmt = $db->prepare("SELECT a.*, m.nom as marchandise_nom, m.poids_kg, p.nom as province
    FROM affectations_transport a
    JOIN marchandises m ON a.marchandise_id = m.id
    JOIN provinces p ON m.province_depart_id = p.id
    WHERE a.transporteur_id = ? AND a.statut IN ('en_preparation', 'en_route')");
$stmt->execute([$transporter['id']]);
$active_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending bids
$stmt = $db->prepare("SELECT o.*, m.nom as marchandise_nom, m.poids_kg, p.nom as province
    FROM offres_transport o
    JOIN marchandises m ON o.marchandise_id = m.id
    JOIN provinces p ON m.province_depart_id = p.id
    WHERE o.transporteur_id = ? AND o.statut = 'en_attente'");
$stmt->execute([$transporter['id']]);
$pending_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent deliveries
$stmt = $db->prepare("SELECT a.*, m.nom as marchandise_nom, m.poids_kg, p.nom as province
    FROM affectations_transport a
    JOIN marchandises m ON a.marchandise_id = m.id
    JOIN provinces p ON m.province_depart_id = p.id
    WHERE a.transporteur_id = ? AND a.statut = 'livre'
    ORDER BY a.date_livraison DESC LIMIT 5");
$stmt->execute([$transporter['id']]);
$recent_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - TPG</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Tableau de bord</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="offres.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Nouvelle offre
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Livraisons actives</h5>
                                <p class="card-text display-4"><?php echo count($active_shipments); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Offres en attente</h5>
                                <p class="card-text display-4"><?php echo count($pending_bids); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Note moyenne</h5>
                                <p class="card-text display-4"><?php echo number_format($transporter['note_moyenne'], 1); ?>/5</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Livraisons complétées</h5>
                                <p class="card-text display-4"><?php echo $transporter['livraisons_completees'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Shipments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-truck-moving me-2"></i>Livraisons en cours</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_shipments)): ?>
                            <div class="alert alert-info">
                                Vous n'avez aucune livraison en cours actuellement.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Marchandise</th>
                                            <th>Province</th>
                                            <th>Poids</th>
                                            <th>Statut</th>
                                            <th>Date départ</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_shipments as $shipment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($shipment['marchandise_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['province']); ?></td>
                                                <td><?php echo htmlspecialchars($shipment['poids_kg']); ?> kg</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $shipment['statut'] === 'en_route' ? 'warning' : 'info';
                                                    ?>">
                                                        <?php echo $shipment['statut'] === 'en_route' ? 'En transit' : 'En préparation'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $shipment['date_depart'] ? date('d/m/Y', strtotime($shipment['date_depart'])) : '--'; ?></td>
                                                <td>
                                                    <a href="commandes.php?id=<?php echo $shipment['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </a>
                                                    <?php if ($shipment['statut'] === 'en_preparation'): ?>
                                                        <a href="../api/v1/affectations.php?action=start&id=<?php echo $shipment['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="fas fa-play"></i> Démarrer
                                                        </a>
                                                    <?php elseif ($shipment['statut'] === 'en_route'): ?>
                                                        <a href="../api/v1/affectations.php?action=complete&id=<?php echo $shipment['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-check"></i> Compléter
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-clock me-2"></i>Offres récentes</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_bids)): ?>
                                    <div class="alert alert-info">
                                        Vous n'avez aucune offre en attente actuellement.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($pending_bids as $bid): ?>
                                            <a href="offres.php?id=<?php echo $bid['id']; ?>" 
                                               class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($bid['marchandise_nom']); ?></h5>
                                                    <small><?php echo number_format($bid['prix'], 0); ?> XAF</small>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($bid['province']); ?> → Libreville
                                                </p>
                                                <small>
                                                    <i class="fas fa-calendar-alt"></i> 
                                                    Livraison avant <?php echo date('d/m/Y', strtotime($bid['date_proposee'])); ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-check-circle me-2"></i>Livraisons récentes</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_deliveries)): ?>
                                    <div class="alert alert-info">
                                        Aucune livraison récente à afficher.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recent_deliveries as $delivery): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($delivery['marchandise_nom']); ?></h5>
                                                    <small class="text-success">Livré</small>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?php echo htmlspecialchars($delivery['province']); ?> → Libreville
                                                </p>
                                                <small>
                                                    <i class="fas fa-calendar-check"></i> 
                                                    Livré le <?php echo date('d/m/Y', strtotime($delivery['date_livraison'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>