<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin access
if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Get statistics for dashboard
$stats = [
    'total_transporters' => getTotalTransporters(),
    'pending_transporters' => getPendingTransporters(),
    'active_shipments' => getActiveShipments(),
    'completed_shipments' => getCompletedShipmentsThisMonth(),
    'revenue' => getMonthlyRevenue(),
    'disputes' => getOpenDisputes()
];

// Get recent activities
$activities = getRecentActivities(10);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Tableau de bord administrateur</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Exporter</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                        <span data-feather="calendar"></span>
                        Ce mois
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title">Transporteurs</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= $stats['total_transporters'] ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-primary">+<?= $stats['pending_transporters'] ?> en attente</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="gestion_transporteurs.php" class="text-white">Voir tous <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success h-100">
                        <div class="card-body">
                            <h5 class="card-title">Livraisons</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= $stats['active_shipments'] ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-success"><?= $stats['completed_shipments'] ?> ce mois</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="gestion_commandes.php" class="text-white">Voir toutes <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-warning h-100">
                        <div class="card-body">
                            <h5 class="card-title">Revenus & Litiges</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= formatCurrency($stats['revenue']) ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-warning"><?= $stats['disputes'] ?> litiges</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="disputes.php" class="text-white">Résoudre <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Activités récentes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?= formatDateTime($activity['created_at']) ?></td>
                                    <td><?= htmlspecialchars($activity['user_name']) ?></td>
                                    <td><?= htmlspecialchars($activity['action']) ?></td>
                                    <td><?= htmlspecialchars($activity['details']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Shipment Status Chart -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Statut des livraisons</h5>
                </div>
                <div class="card-body">
                    <canvas id="shipmentChart" width="100%" height="30"></canvas>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="../assets/js/chart.min.js"></script>
<script>
// Shipment status chart
const ctx = document.getElementById('shipmentChart').getContext('2d');
const shipmentChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['En attente', 'En préparation', 'En route', 'Livrées', 'Retardées', 'Annulées'],
        datasets: [{
            data: [12, 5, 8, 24, 3, 2],
            backgroundColor: [
                '#6c757d',
                '#17a2b8',
                '#007bff',
                '#28a745',
                '#ffc107',
                '#dc3545'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});
</script>