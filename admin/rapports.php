<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Get report parameters
$reportType = $_GET['report'] ?? 'monthly';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$province = $_GET['province'] ?? 'all';

// Get report data
$reportData = getReportData($reportType, $startDate, $endDate, $province);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Rapports et statistiques</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-file-export me-1"></i> Exporter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="rapports.php">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="report" class="form-label">Type de rapport</label>
                                <select class="form-select" id="report" name="report">
                                    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Mensuel</option>
                                    <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Hebdomadaire</option>
                                    <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Quotidien</option>
                                    <option value="transporters" <?= $reportType === 'transporters' ? 'selected' : '' ?>>Transporteurs</option>
                                    <option value="provinces" <?= $reportType === 'provinces' ? 'selected' : '' ?>>Par province</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="start_date" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="end_date" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="province" class="form-label">Province</label>
                                <select class="form-select" id="province" name="province">
                                    <option value="all">Toutes les provinces</option>
                                    <?php foreach (getProvinces() as $id => $name): ?>
                                        <option value="<?= $id ?>" <?= $province == $id ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Appliquer les filtres
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Summary -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title">Livraisons</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= $reportData['total_shipments'] ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-primary"><?= $reportData['completed_shipments'] ?> terminées</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success h-100">
                        <div class="card-body">
                            <h5 class="card-title">Revenus</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= formatCurrency($reportData['total_revenue']) ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-success"><?= formatCurrency($reportData['platform_earnings']) ?> plateforme</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-info h-100">
                        <div class="card-body">
                            <h5 class="card-title">Transporteurs</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="mb-0"><?= $reportData['active_transporters'] ?></h2>
                                <span class="h4 mb-0">
                                    <span class="badge bg-light text-info"><?= $reportData['new_transporters'] ?> nouveaux</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Report Content -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistiques des livraisons</h5>
                </div>
                <div class="card-body">
                    <canvas id="shipmentsChart" width="100%" height="40"></canvas>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Répartition par province</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="provincesChart" width="100%" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Top transporteurs</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Transporteur</th>
                                            <th>Livraisons</th>
                                            <th>Revenu</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['top_transporters'] as $transporter): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($transporter['name']) ?></td>
                                            <td><?= $transporter['shipments'] ?></td>
                                            <td><?= formatCurrency($transporter['earnings']) ?></td>
                                            <td>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i > $transporter['rating'] ? '-empty' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Détails des livraisons</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Livraison</th>
                                    <th>Province</th>
                                    <th>Transporteur</th>
                                    <th>Statut</th>
                                    <th>Prix</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['shipment_details'] as $shipment): ?>
                                <tr>
                                    <td><?= formatDate($shipment['date']) ?></td>
                                    <td>
                                        <a href="gestion_commandes.php?id=<?= $shipment['id'] ?>">
                                            #<?= $shipment['id'] ?>
                                        </a>
                                        <br>
                                        <small><?= htmlspecialchars($shipment['product_name']) ?></small>
                                    </td>
                                    <td><?= $shipment['province'] ?></td>
                                    <td><?= htmlspecialchars($shipment['transporter_name']) ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = [
                                            'livre' => 'success',
                                            'en_route' => 'primary',
                                            'retarde' => 'warning',
                                            'annule' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $statusClass[$shipment['status']] ?>">
                                            <?= getShipmentStatusText($shipment['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatCurrency($shipment['price']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Précédent</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Suivant</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="export_report.php">
                <input type="hidden" name="report_type" value="<?= $reportType ?>">
                <input type="hidden" name="start_date" value="<?= $startDate ?>">
                <input type="hidden" name="end_date" value="<?= $endDate ?>">
                <input type="hidden" name="province" value="<?= $province ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Exporter le rapport</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Format</label>
                        <select class="form-select" id="export_format" name="format" required>
                            <option value="csv">CSV (Excel)</option>
                            <option value="pdf">PDF</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_scope" class="form-label">Portée</label>
                        <select class="form-select" id="export_scope" name="scope" required>
                            <option value="summary">Résumé seulement</option>
                            <option value="detailed" selected>Détails des livraisons</option>
                            <option value="all">Toutes les données</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_email" class="form-label">Envoyer à (optionnel)</label>
                        <input type="email" class="form-control" id="export_email" name="email" placeholder="email@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Exporter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="../assets/js/chart.min.js"></script>
<script>
// Shipments chart
const shipmentsCtx = document.getElementById('shipmentsChart').getContext('2d');
const shipmentsChart = new Chart(shipmentsCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($reportData['chart_labels']) ?>,
        datasets: [
            {
                label: 'Livraisons complétées',
                data: <?= json_encode($reportData['completed_data']) ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.1,
                fill: true
            },
            {
                label: 'Livraisons totales',
                data: <?= json_encode($reportData['total_data']) ?>,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.1,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Livraisons par période'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Provinces chart
const provincesCtx = document.getElementById('provincesChart').getContext('2d');
const provincesChart = new Chart(provincesCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($reportData['province_labels']) ?>,
        datasets: [{
            data: <?= json_encode($reportData['province_data']) ?>,
            backgroundColor: [
                '#007bff',
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d',
                '#17a2b8',
                '#6610f2',
                '#fd7e14',
                '#20c997'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            },
            title: {
                display: true,
                text: 'Livraisons par province'
            }
        }
    }
});
</script>