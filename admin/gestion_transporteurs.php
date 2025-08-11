<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Handle actions
if (isset($_GET['action'])) {
    $transporterId = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'approve':
            updateTransporterStatus($transporterId, 'approuve');
            logActivity("Transporteur #$transporterId approuvé");
            break;
            
        case 'reject':
            updateTransporterStatus($transporterId, 'rejete');
            logActivity("Transporteur #$transporterId rejeté");
            break;
            
        case 'ban':
            updateTransporterStatus($transporterId, 'banni');
            logActivity("Transporteur #$transporterId banni");
            break;
            
        case 'unban':
            updateTransporterStatus($transporterId, 'approuve');
            logActivity("Transporteur #$transporterId réactivé");
            break;
    }
    
    header("Location: gestion_transporteurs.php");
    exit();
}

// Get transporters based on filter
$filter = $_GET['filter'] ?? 'all';
$transporters = getTransporters($filter);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestion des transporteurs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Tous</a>
                        <a href="?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">En attente</a>
                        <a href="?filter=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-outline-secondary' ?>">Approuvés</a>
                        <a href="?filter=banned" class="btn btn-sm <?= $filter === 'banned' ? 'btn-primary' : 'btn-outline-secondary' ?>">Bannis</a>
                    </div>
                </div>
            </div>

            <!-- Transporters Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email/Téléphone</th>
                                    <th>Véhicule</th>
                                    <th>Provinces</th>
                                    <th>Statut</th>
                                    <th>Note</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transporters as $transporter): ?>
                                <tr>
                                    <td><?= $transporter['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($transporter['prenom'] . ' ' . $transporter['nom']) ?></strong>
                                        <br>
                                        <small class="text-muted">Inscrit le: <?= formatDate($transporter['created_at']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($transporter['email']) ?>
                                        <br>
                                        <?= htmlspecialchars($transporter['telephone']) ?>
                                    </td>
                                    <td>
                                        <?= ucfirst($transporter['type_vehicule']) ?>
                                        <br>
                                        <small class="text-muted">Capacité: <?= $transporter['capacite_kg'] ?>kg</small>
                                    </td>
                                    <td>
                                        <?php 
                                        $provinces = explode(',', $transporter['provinces']);
                                        foreach ($provinces as $province) {
                                            echo '<span class="badge bg-secondary me-1">' . $province . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = [
                                            'en_attente' => 'warning',
                                            'approuve' => 'success',
                                            'rejete' => 'danger',
                                            'banni' => 'dark'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $statusClass[$transporter['statut']] ?>">
                                            <?= getTransporterStatusText($transporter['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i > $transporter['note_moyenne'] ? '-empty' : '' ?>"></i>
                                            <?php endfor; ?>
                                            <small>(<?= $transporter['total_livraisons'] ?>)</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewTransporterModal" 
                                                    data-id="<?= $transporter['id'] ?>"
                                                    data-name="<?= htmlspecialchars($transporter['prenom'] . ' ' . htmlspecialchars($transporter['nom']) ?>"
                                                    data-email="<?= htmlspecialchars($transporter['email']) ?>"
                                                    data-phone="<?= htmlspecialchars($transporter['telephone']) ?>"
                                                    data-vehicle="<?= ucfirst($transporter['type_vehicule']) ?>"
                                                    data-capacity="<?= $transporter['capacite_kg'] ?>kg"
                                                    data-plate="<?= htmlspecialchars($transporter['plaque_immatriculation']) ?>"
                                                    data-license="<?= htmlspecialchars($transporter['permis_numero']) ?>"
                                                    data-provinces="<?= htmlspecialchars($transporter['provinces']) ?>"
                                                    data-status="<?= $transporter['statut'] ?>"
                                                    data-rating="<?= $transporter['note_moyenne'] ?>"
                                                    data-deliveries="<?= $transporter['total_livraisons'] ?>">
                                                    <i class="fas fa-eye me-1"></i> Voir détails
                                                </a></li>
                                                
                                                <?php if ($transporter['statut'] === 'en_attente'): ?>
                                                    <li><a class="dropdown-item text-success" href="?action=approve&id=<?= $transporter['id'] ?>">
                                                        <i class="fas fa-check me-1"></i> Approuver
                                                    </a></li>
                                                    <li><a class="dropdown-item text-danger" href="?action=reject&id=<?= $transporter['id'] ?>">
                                                        <i class="fas fa-times me-1"></i> Rejeter
                                                    </a></li>
                                                <?php elseif ($transporter['statut'] === 'approuve'): ?>
                                                    <li><a class="dropdown-item text-dark" href="?action=ban&id=<?= $transporter['id'] ?>">
                                                        <i class="fas fa-ban me-1"></i> Bannir
                                                    </a></li>
                                                <?php elseif ($transporter['statut'] === 'banni'): ?>
                                                    <li><a class="dropdown-item text-success" href="?action=unban&id=<?= $transporter['id'] ?>">
                                                        <i class="fas fa-check-circle me-1"></i> Réactiver
                                                    </a></li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-primary" href="../admin/gestion_commandes.php?transporter=<?= $transporter['id'] ?>">
                                                    <i class="fas fa-truck me-1"></i> Voir livraisons
                                                </a></li>
                                            </ul>
                                        </div>
                                    </td>
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

<!-- View Transporter Modal -->
<div class="modal fade" id="viewTransporterModal" tabindex="-1" aria-labelledby="viewTransporterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTransporterModalLabel">Détails du transporteur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informations personnelles</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Nom complet:</th>
                                <td id="modal-name"></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td id="modal-email"></td>
                            </tr>
                            <tr>
                                <th>Téléphone:</th>
                                <td id="modal-phone"></td>
                            </tr>
                            <tr>
                                <th>Statut:</th>
                                <td id="modal-status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Informations véhicule</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Type:</th>
                                <td id="modal-vehicle"></td>
                            </tr>
                            <tr>
                                <th>Capacité:</th>
                                <td id="modal-capacity"></td>
                            </tr>
                            <tr>
                                <th>Plaque:</th>
                                <td id="modal-plate"></td>
                            </tr>
                            <tr>
                                <th>Permis:</th>
                                <td id="modal-license"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Provinces desservies</h6>
                        <div id="modal-provinces" class="d-flex flex-wrap gap-1"></div>
                    </div>
                    <div class="col-md-6">
                        <h6>Performance</h6>
                        <div class="rating mb-1">
                            <span id="modal-rating-stars"></span>
                            <small id="modal-deliveries"></small>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100">85% livraisons réussies</div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Documents</h6>
                        <div class="d-flex gap-2">
                            <div class="card document-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-id-card fa-3x mb-2"></i>
                                    <p class="mb-0">Carte d'identité</p>
                                </div>
                            </div>
                            <div class="card document-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-car fa-3x mb-2"></i>
                                    <p class="mb-0">Carte grise</p>
                                </div>
                            </div>
                            <div class="card document-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-id-badge fa-3x mb-2"></i>
                                    <p class="mb-0">Permis de conduire</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary">Voir tous les détails</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// View transporter modal
document.getElementById('viewTransporterModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Update modal content
    document.getElementById('viewTransporterModalLabel').textContent = button.getAttribute('data-name');
    document.getElementById('modal-name').textContent = button.getAttribute('data-name');
    document.getElementById('modal-email').textContent = button.getAttribute('data-email');
    document.getElementById('modal-phone').textContent = button.getAttribute('data-phone');
    document.getElementById('modal-vehicle').textContent = button.getAttribute('data-vehicle');
    document.getElementById('modal-capacity').textContent = button.getAttribute('data-capacity');
    document.getElementById('modal-plate').textContent = button.getAttribute('data-plate');
    document.getElementById('modal-license').textContent = button.getAttribute('data-license');
    
    // Status with badge
    const statusText = {
        'en_attente': 'En attente',
        'approuve': 'Approuvé',
        'rejete': 'Rejeté',
        'banni': 'Banni'
    };
    const statusClass = {
        'en_attente': 'warning',
        'approuve': 'success',
        'rejete': 'danger',
        'banni': 'dark'
    };
    const status = button.getAttribute('data-status');
    document.getElementById('modal-status').innerHTML = 
        `<span class="badge bg-${statusClass[status]}">${statusText[status]}</span>`;
    
    // Provinces
    const provincesContainer = document.getElementById('modal-provinces');
    provincesContainer.innerHTML = '';
    button.getAttribute('data-provinces').split(',').forEach(province => {
        const badge = document.createElement('span');
        badge.className = 'badge bg-secondary';
        badge.textContent = province;
        provincesContainer.appendChild(badge);
    });
    
    // Rating
    const rating = parseFloat(button.getAttribute('data-rating'));
    const ratingContainer = document.getElementById('modal-rating-stars');
    ratingContainer.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('i');
        star.className = i <= rating ? 'fas fa-star' : 'fas fa-star-empty';
        ratingContainer.appendChild(star);
    }
    
    // Deliveries
    document.getElementById('modal-deliveries').textContent = `(${button.getAttribute('data-deliveries')} livraisons)`;
});
</script>