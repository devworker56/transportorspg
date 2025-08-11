<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Handle shipment status updates
if (isset($_POST['update_status'])) {
    $shipmentId = intval($_POST['shipment_id']);
    $newStatus = $_POST['new_status'];
    $notes = $_POST['notes'] ?? '';
    
    if (updateShipmentStatus($shipmentId, $newStatus, $notes)) {
        logActivity("Statut de livraison #$shipmentId mis à jour: $newStatus");
        $_SESSION['success_message'] = "Statut de la livraison mis à jour avec succès.";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la mise à jour du statut.";
    }
    
    header("Location: gestion_commandes.php");
    exit();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$province = $_GET['province'] ?? 'all';
$transporterId = $_GET['transporter'] ?? null;
$search = $_GET['search'] ?? '';

// Get shipments based on filters
$shipments = getShipments($filter, $province, $transporterId, $search);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestion des livraisons</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="fas fa-filter me-1"></i> Filtres
                    </button>
                    <div class="btn-group me-2">
                        <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Toutes</a>
                        <a href="?filter=active" class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>">Actives</a>
                        <a href="?filter=completed" class="btn btn-sm <?= $filter === 'completed' ? 'btn-primary' : 'btn-outline-secondary' ?>">Terminées</a>
                    </div>
                </div>
            </div>

            <!-- Display messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Shipments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Marchandise</th>
                                    <th>Origine → Destination</th>
                                    <th>Dates</th>
                                    <th>Transporteur</th>
                                    <th>Statut</th>
                                    <th>Prix</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipments as $shipment): ?>
                                <tr>
                                    <td><?= $shipment['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($shipment['nom_marchandise']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= $shipment['poids_kg'] ?>kg, <?= $shipment['dimensions'] ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $shipment['province_depart'] ?></span> 
                                        <i class="fas fa-arrow-right mx-1"></i> 
                                        <span class="badge bg-primary">Libreville</span>
                                        <br>
                                        <small class="text-muted"><?= formatDate($shipment['date_limite']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($shipment['date_depart']): ?>
                                            Départ: <?= formatDate($shipment['date_depart']) ?>
                                            <br>
                                        <?php endif; ?>
                                        <?php if ($shipment['date_livraison']): ?>
                                            Livraison: <?= formatDate($shipment['date_livraison']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($shipment['transporteur_id']): ?>
                                            <a href="gestion_transporteurs.php?id=<?= $shipment['transporteur_id'] ?>">
                                                <?= htmlspecialchars($shipment['transporteur_nom']) ?>
                                            </a>
                                            <br>
                                            <small class="text-muted"><?= $shipment['type_vehicule'] ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Non assigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = [
                                            'en_attente' => 'secondary',
                                            'en_preparation' => 'info',
                                            'en_route' => 'primary',
                                            'livre' => 'success',
                                            'retarde' => 'warning',
                                            'annule' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $statusClass[$shipment['statut']] ?>">
                                            <?= getShipmentStatusText($shipment['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= formatCurrency($shipment['prix']) ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewShipmentModal" 
                                                    data-id="<?= $shipment['id'] ?>"
                                                    data-name="<?= htmlspecialchars($shipment['nom_marchandise']) ?>"
                                                    data-description="<?= htmlspecialchars($shipment['description']) ?>"
                                                    data-weight="<?= $shipment['poids_kg'] ?>"
                                                    data-dimensions="<?= htmlspecialchars($shipment['dimensions']) ?>"
                                                    data-origin="<?= $shipment['province_depart'] ?>"
                                                    data-pickup="<?= htmlspecialchars($shipment['adresse_ramassage']) ?>"
                                                    data-delivery="<?= htmlspecialchars($shipment['adresse_livraison']) ?>"
                                                    data-deadline="<?= formatDate($shipment['date_limite']) ?>"
                                                    data-transporter="<?= htmlspecialchars($shipment['transporteur_nom']) ?>"
                                                    data-price="<?= formatCurrency($shipment['prix']) ?>"
                                                    data-status="<?= $shipment['statut'] ?>"
                                                    data-departure="<?= $shipment['date_depart'] ? formatDate($shipment['date_depart']) : 'N/A' ?>"
                                                    data-delivery-date="<?= $shipment['date_livraison'] ? formatDate($shipment['date_livraison']) : 'N/A' ?>">
                                                    <i class="fas fa-eye me-1"></i> Voir détails
                                                </a></li>
                                                
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                    data-id="<?= $shipment['id'] ?>"
                                                    data-current-status="<?= $shipment['statut'] ?>">
                                                    <i class="fas fa-sync-alt me-1"></i> Modifier statut
                                                </a></li>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#cancelShipmentModal" 
                                                    data-id="<?= $shipment['id'] ?>">
                                                    <i class="fas fa-times-circle me-1"></i> Annuler
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

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="get" action="gestion_commandes.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filtrer les livraisons</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="filter" class="form-label">Statut</label>
                        <select class="form-select" id="filter" name="filter">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Toutes les livraisons</option>
                            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Livraisons actives</option>
                            <option value="completed" <?= $filter === 'completed' ? 'selected' : '' ?>>Livraisons terminées</option>
                            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="preparation" <?= $filter === 'preparation' ? 'selected' : '' ?>>En préparation</option>
                            <option value="transit" <?= $filter === 'transit' ? 'selected' : '' ?>>En transit</option>
                            <option value="delivered" <?= $filter === 'delivered' ? 'selected' : '' ?>>Livrées</option>
                            <option value="delayed" <?= $filter === 'delayed' ? 'selected' : '' ?>>Retardées</option>
                            <option value="canceled" <?= $filter === 'canceled' ? 'selected' : '' ?>>Annulées</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="province" class="form-label">Province d'origine</label>
                        <select class="form-select" id="province" name="province">
                            <option value="all">Toutes les provinces</option>
                            <?php foreach (getProvinces() as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $province == $id ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Nom marchandise, transporteur..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Shipment Modal -->
<div class="modal fade" id="viewShipmentModal" tabindex="-1" aria-labelledby="viewShipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewShipmentModalLabel">Détails de la livraison</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informations marchandise</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Nom:</th>
                                <td id="modal-shipment-name"></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td id="modal-shipment-description"></td>
                            </tr>
                            <tr>
                                <th>Poids:</th>
                                <td id="modal-shipment-weight"></td>
                            </tr>
                            <tr>
                                <th>Dimensions:</th>
                                <td id="modal-shipment-dimensions"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Informations livraison</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Origine:</th>
                                <td id="modal-shipment-origin"></td>
                            </tr>
                            <tr>
                                <th>Adresse ramassage:</th>
                                <td id="modal-shipment-pickup"></td>
                            </tr>
                            <tr>
                                <th>Adresse livraison:</th>
                                <td id="modal-shipment-delivery"></td>
                            </tr>
                            <tr>
                                <th>Date limite:</th>
                                <td id="modal-shipment-deadline"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Transporteur</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Nom:</th>
                                <td id="modal-shipment-transporter"></td>
                            </tr>
                            <tr>
                                <th>Prix:</th>
                                <td id="modal-shipment-price"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Statut</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Statut actuel:</th>
                                <td id="modal-shipment-status"></td>
                            </tr>
                            <tr>
                                <th>Date départ:</th>
                                <td id="modal-shipment-departure"></td>
                            </tr>
                            <tr>
                                <th>Date livraison:</th>
                                <td id="modal-shipment-delivery-date"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Historique des statuts</h6>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">Aujourd'hui, 14:32</small>
                                    <p class="mb-1">Livraison confirmée par le client</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">Aujourd'hui, 13:45</small>
                                    <p class="mb-1">Livraison effectuée</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">Aujourd'hui, 10:15</small>
                                    <p class="mb-1">En route vers Libreville</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">Hier, 08:30</small>
                                    <p class="mb-1">Marchandise récupérée</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <small class="text-muted">20/05/2023</small>
                                    <p class="mb-1">Transporteur assigné</p>
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="gestion_commandes.php">
                <input type="hidden" name="shipment_id" id="update-shipment-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Modifier le statut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="en_preparation">En préparation</option>
                            <option value="en_route">En route</option>
                            <option value="livre">Livré</option>
                            <option value="retarde">Retardé</option>
                            <option value="annule">Annulé</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Shipment Modal -->
<div class="modal fade" id="cancelShipmentModal" tabindex="-1" aria-labelledby="cancelShipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="gestion_commandes.php">
                <input type="hidden" name="shipment_id" id="cancel-shipment-id">
                <input type="hidden" name="new_status" value="annule">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelShipmentModalLabel">Annuler la livraison</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir annuler cette livraison? Cette action ne peut pas être annulée.</p>
                    
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Raison de l'annulation (requis)</label>
                        <textarea class="form-control" id="cancel_reason" name="notes" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ne pas annuler</button>
                    <button type="submit" name="update_status" class="btn btn-danger">Confirmer l'annulation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// View shipment modal
document.getElementById('viewShipmentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Update modal content
    document.getElementById('viewShipmentModalLabel').textContent = button.getAttribute('data-name');
    document.getElementById('modal-shipment-name').textContent = button.getAttribute('data-name');
    document.getElementById('modal-shipment-description').textContent = button.getAttribute('data-description');
    document.getElementById('modal-shipment-weight').textContent = button.getAttribute('data-weight') + ' kg';
    document.getElementById('modal-shipment-dimensions').textContent = button.getAttribute('data-dimensions');
    document.getElementById('modal-shipment-origin').textContent = button.getAttribute('data-origin');
    document.getElementById('modal-shipment-pickup').textContent = button.getAttribute('data-pickup');
    document.getElementById('modal-shipment-delivery').textContent = button.getAttribute('data-delivery');
    document.getElementById('modal-shipment-deadline').textContent = button.getAttribute('data-deadline');
    document.getElementById('modal-shipment-transporter').textContent = button.getAttribute('data-transporter') || 'Non assigné';
    document.getElementById('modal-shipment-price').textContent = button.getAttribute('data-price');
    document.getElementById('modal-shipment-departure').textContent = button.getAttribute('data-departure');
    document.getElementById('modal-shipment-delivery-date').textContent = button.getAttribute('data-delivery-date');
    
    // Status with badge
    const statusText = {
        'en_attente': 'En attente',
        'en_preparation': 'En préparation',
        'en_route': 'En route',
        'livre': 'Livré',
        'retarde': 'Retardé',
        'annule': 'Annulé'
    };
    const statusClass = {
        'en_attente': 'secondary',
        'en_preparation': 'info',
        'en_route': 'primary',
        'livre': 'success',
        'retarde': 'warning',
        'annule': 'danger'
    };
    const status = button.getAttribute('data-status');
    document.getElementById('modal-shipment-status').innerHTML = 
        `<span class="badge bg-${statusClass[status]}">${statusText[status]}</span>`;
});

// Update status modal
document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('update-shipment-id').value = button.getAttribute('data-id');
    
    // Set current status as selected
    const currentStatus = button.getAttribute('data-current-status');
    const statusSelect = document.getElementById('new_status');
    
    for (let i = 0; i < statusSelect.options.length; i++) {
        if (statusSelect.options[i].value === currentStatus) {
            statusSelect.options[i].selected = true;
            break;
        }
    }
});

// Cancel shipment modal
document.getElementById('cancelShipmentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('cancel-shipment-id').value = button.getAttribute('data-id');
});
</script>