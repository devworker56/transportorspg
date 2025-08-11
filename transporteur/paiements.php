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

// Get payment history
$stmt = $db->prepare("SELECT p.*, m.nom as marchandise_nom
    FROM paiements p
    JOIN affectations_transport a ON p.affectation_id = a.id
    JOIN marchandises m ON a.marchandise_id = m.id
    WHERE a.transporteur_id = ?
    ORDER BY p.date_paiement DESC");
$stmt->execute([$transporter_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending payments
$stmt = $db->prepare("SELECT a.id, a.prix, m.nom as marchandise_nom, a.date_livraison
    FROM affectations_transport a
    JOIN marchandises m ON a.marchandise_id = m.id
    WHERE a.transporteur_id = ? AND a.statut = 'livre' 
    AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.affectation_id = a.id)
    ORDER BY a.date_livraison DESC");
$stmt->execute([$transporter_id]);
$pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_earned = array_sum(array_column($payments, 'montant'));
$pending_amount = array_sum(array_column($pending_payments, 'prix'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Paiements - TPG</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mes Paiements</h1>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total gagné</h5>
                                <p class="card-text display-4"><?php echo number_format($total_earned, 0); ?> XAF</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">En attente</h5>
                                <p class="card-text display-4"><?php echo number_format($pending_amount, 0); ?> XAF</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Livraisons payées</h5>
                                <p class="card-text display-4"><?php echo count($payments); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Payments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-clock me-2"></i>Paiements en attente</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_payments)): ?>
                            <div class="alert alert-info">
                                Aucun paiement en attente.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Livraison</th>
                                            <th>Marchandise</th>
                                            <th>Date livraison</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_payments as $payment): ?>
                                            <tr>
                                                <td>#<?php echo $payment['id']; ?></td>
                                                <td><?php echo htmlspecialchars($payment['marchandise_nom']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($payment['date_livraison'])); ?></td>
                                                <td><?php echo number_format($payment['prix'], 0); ?> XAF</td>
                                                <td>
                                                    <span class="badge bg-warning">En attente</span>
                                                    <small class="text-muted">(Traitement sous 48h)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment History -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-history me-2"></i>Historique des paiements</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="alert alert-info">
                                Aucun historique de paiement.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Livraison</th>
                                            <th>Marchandise</th>
                                            <th>Montant</th>
                                            <th>Méthode</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($payment['date_paiement'])); ?></td>
                                                <td>#<?php echo $payment['affectation_id']; ?></td>
                                                <td><?php echo htmlspecialchars($payment['marchandise_nom']); ?></td>
                                                <td><?php echo number_format($payment['montant'], 0); ?> XAF</td>
                                                <td><?php echo ucfirst($payment['methode']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $payment['statut'] === 'complete' ? 'success' : 'warning'; ?>">
                                                        <?php echo $payment['statut'] === 'complete' ? 'Complété' : 'En traitement'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>