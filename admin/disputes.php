<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Handle dispute resolution
if (isset($_POST['resolve_dispute'])) {
    $disputeId = intval($_POST['dispute_id']);
    $resolution = $_POST['resolution'];
    $notes = $_POST['notes'] ?? '';
    
    if (resolveDispute($disputeId, $resolution, $notes)) {
        logActivity("Litige #$disputeId résolu: $resolution");
        $_SESSION['success_message'] = "Litige résolu avec succès.";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la résolution du litige.";
    }
    
    header("Location: disputes.php");
    exit();
}

// Get disputes
$disputes = getDisputes();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestion des litiges</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="?filter=all" class="btn btn-sm btn-outline-secondary">Tous</a>
                        <a href="?filter=open" class="btn btn-sm btn-primary">Non résolus</a>
                        <a href="?filter=resolved" class="btn btn-sm btn-outline-secondary">Résolus</a>
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

            <!-- Disputes Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Livraison</th>
                                    <th>Créé par</th>
                                    <th>Raison</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($disputes as $dispute): ?>
                                <tr>
                                    <td><?= $dispute['id'] ?></td>
                                    <td>
                                        <a href="gestion_commandes.php?id=<?= $dispute['shipment_id'] ?>">
                                            Livraison #<?= $dispute['shipment_id'] ?>
                                        </a>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($dispute['product_name']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($dispute['user_name']) ?>
                                        <br>
                                        <small class="text-muted"><?= $dispute['user_role'] === 'seller' ? 'Vendeur' : 'Transporteur' ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($dispute['reason']) ?>
                                    </td>
                                    <td>
                                        <?= formatDate($dispute['created_at']) ?>
                                    </td>
                                    <td>
                                        <?php if ($dispute['status'] === 'open'): ?>
                                            <span class="badge bg-danger">Non résolu</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Résolu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewDisputeModal" 
                                                    data-id="<?= $dispute['id'] ?>"
                                                    data-shipment="<?= $dispute['shipment_id'] ?>"
                                                    data-user="<?= htmlspecialchars($dispute['user_name']) ?>"
                                                    data-role="<?= $dispute['user_role'] === 'seller' ? 'Vendeur' : 'Transporteur' ?>"
                                                    data-reason="<?= htmlspecialchars($dispute['reason']) ?>"
                                                    data-details="<?= htmlspecialchars($dispute['details']) ?>"
                                                    data-status="<?= $dispute['status'] ?>"
                                                    data-created="<?= formatDateTime($dispute['created_at']) ?>"
                                                    data-resolved="<?= $dispute['resolved_at'] ? formatDateTime($dispute['resolved_at']) : 'Non résolu' ?>"
                                                    data-resolution="<?= htmlspecialchars($dispute['resolution']) ?>"
                                                    data-resolution-notes="<?= htmlspecialchars($dispute['resolution_notes']) ?>">
                                                    <i class="fas fa-eye me-1"></i> Voir détails
                                                </a></li>
                                                
                                                <?php if ($dispute['status'] === 'open'): ?>
                                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resolveDisputeModal" 
                                                        data-id="<?= $dispute['id'] ?>">
                                                        <i class="fas fa-gavel me-1"></i> Résoudre
                                                    </a></li>
                                                <?php endif; ?>
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

<!-- View Dispute Modal -->
<div class="modal fade" id="viewDisputeModal" tabindex="-1" aria-labelledby="viewDisputeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDisputeModalLabel">Détails du litige</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informations de base</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Livraison:</th>
                                <td id="modal-dispute-shipment"></td>
                            </tr>
                            <tr>
                                <th>Créé par:</th>
                                <td id="modal-dispute-user"></td>
                            </tr>
                            <tr>
                                <th>Rôle:</th>
                                <td id="modal-dispute-role"></td>
                            </tr>
                            <tr>
                                <th>Date création:</th>
                                <td id="modal-dispute-created"></td>
                            </tr>
                            <tr>
                                <th>Statut:</th>
                                <td id="modal-dispute-status"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Résolution</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Résolution:</th>
                                <td id="modal-dispute-resolution"></td>
                            </tr>
                            <tr>
                                <th>Date résolution:</th>
                                <td id="modal-dispute-resolved"></td>
                            </tr>
                            <tr>
                                <th>Notes:</th>
                                <td id="modal-dispute-resolution-notes"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Détails du litige</h6>
                        <div class="card">
                            <div class="card-header">
                                <strong>Raison:</strong> <span id="modal-dispute-reason"></span>
                            </div>
                            <div class="card-body">
                                <p id="modal-dispute-details"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Preuves</h6>
                        <div class="d-flex gap-2">
                            <div class="card document-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <p class="mb-0">Image 1</p>
                                </div>
                            </div>
                            <div class="card document-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x mb-2"></i>
                                    <p class="mb-0">Document</p>
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

<!-- Resolve Dispute Modal -->
<div class="modal fade" id="resolveDisputeModal" tabindex="-1" aria-labelledby="resolveDisputeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="disputes.php">
                <input type="hidden" name="dispute_id" id="resolve-dispute-id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resolveDisputeModalLabel">Résoudre le litige</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="resolution" class="form-label">Décision</label>
                        <select class="form-select" id="resolution" name="resolution" required>
                            <option value="">Sélectionner une décision</option>
                            <option value="seller_fault">Faute du vendeur</option>
                            <option value="transporter_fault">Faute du transporteur</option>
                            <option value="shared_responsibility">Responsabilité partagée</option>
                            <option value="no_fault">Aucune faute</option>
                            <option value="partial_refund">Remboursement partiel</option>
                            <option value="full_refund">Remboursement complet</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Actions à prendre</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_parties" name="notify_parties" checked>
                            <label class="form-check-label" for="notify_parties">
                                Notifier les parties concernées
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="adjust_rating" name="adjust_rating" checked>
                            <label class="form-check-label" for="adjust_rating">
                                Ajuster la note du transporteur si applicable
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="process_refund" name="process_refund">
                            <label class="form-check-label" for="process_refund">
                                Traiter le remboursement si applicable
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="resolve_dispute" class="btn btn-primary">Confirmer la résolution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// View dispute modal
document.getElementById('viewDisputeModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Update modal content
    document.getElementById('viewDisputeModalLabel').textContent = `Litige #${button.getAttribute('data-id')}`;
    document.getElementById('modal-dispute-shipment').textContent = `Livraison #${button.getAttribute('data-shipment')}`;
    document.getElementById('modal-dispute-user').textContent = button.getAttribute('data-user');
    document.getElementById('modal-dispute-role').textContent = button.getAttribute('data-role');
    document.getElementById('modal-dispute-reason').textContent = button.getAttribute('data-reason');
    document.getElementById('modal-dispute-details').textContent = button.getAttribute('data-details');
    document.getElementById('modal-dispute-created').textContent = button.getAttribute('data-created');
    document.getElementById('modal-dispute-resolved').textContent = button.getAttribute('data-resolved');
    document.getElementById('modal-dispute-resolution').textContent = button.getAttribute('data-resolution') || 'Non résolu';
    document.getElementById('modal-dispute-resolution-notes').textContent = button.getAttribute('data-resolution-notes') || 'Aucune note';
    
    // Status
    if (button.getAttribute('data-status') === 'open') {
        document.getElementById('modal-dispute-status').innerHTML = '<span class="badge bg-danger">Non résolu</span>';
    } else {
        document.getElementById('modal-dispute-status').innerHTML = '<span class="badge bg-success">Résolu</span>';
    }
});

// Resolve dispute modal
document.getElementById('resolveDisputeModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('resolve-dispute-id').value = button.getAttribute('data-id');
});
</script>