<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header("Location: /connexion.php");
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'platform_fee' => floatval($_POST['platform_fee']),
        'min_transporter_fee' => floatval($_POST['min_transporter_fee']),
        'max_shipment_weight' => floatval($_POST['max_shipment_weight']),
        'default_deadline_days' => intval($_POST['default_deadline_days']),
        'notify_new_shipments' => isset($_POST['notify_new_shipments']) ? 1 : 0,
        'notify_disputes' => isset($_POST['notify_disputes']) ? 1 : 0,
        'admin_email' => $_POST['admin_email'],
        'support_phone' => $_POST['support_phone']
    ];
    
    if (updateSettings($settings)) {
        $_SESSION['success_message'] = "Paramètres mis à jour avec succès.";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la mise à jour des paramètres.";
    }
    
    header("Location: parametres.php");
    exit();
}

// Get current settings
$settings = getSettings();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Paramètres de la plateforme</h1>
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

            <form method="post" action="parametres.php">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Paramètres financiers</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="platform_fee" class="form-label">Frais de plateforme (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" min="0" max="50" class="form-control" id="platform_fee" 
                                        name="platform_fee" value="<?= $settings['platform_fee'] ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Pourcentage retenu par la plateforme sur chaque livraison</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="min_transporter_fee" class="form-label">Tarif minimum transporteur (FCFA)</label>
                                <div class="input-group">
                                    <span class="input-group-text">FCFA</span>
                                    <input type="number" step="100" min="0" class="form-control" id="min_transporter_fee" 
                                        name="min_transporter_fee" value="<?= $settings['min_transporter_fee'] ?>" required>
                                </div>
                                <small class="text-muted">Montant minimum qu'un transporteur peut proposer</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Paramètres de livraison</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="max_shipment_weight" class="form-label">Poids maximum (kg)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" min="0" class="form-control" id="max_shipment_weight" 
                                        name="max_shipment_weight" value="<?= $settings['max_shipment_weight'] ?>" required>
                                    <span class="input-group-text">kg</span>
                                </div>
                                <small class="text-muted">Poids maximum accepté pour une livraison</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="default_deadline_days" class="form-label">Délai par défaut (jours)</label>
                                <div class="input-group">
                                    <input type="number" min="1" max="30" class="form-control" id="default_deadline_days" 
                                        name="default_deadline_days" value="<?= $settings['default_deadline_days'] ?>" required>
                                    <span class="input-group-text">jours</span>
                                </div>
                                <small class="text-muted">Délai de livraison par défaut si non spécifié</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Notifications</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notify_new_shipments" 
                                name="notify_new_shipments" <?= $settings['notify_new_shipments'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_new_shipments">Notifier les transporteurs des nouvelles livraisons</label>
                        </div>
                        
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notify_disputes" 
                                name="notify_disputes" <?= $settings['notify_disputes'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_disputes">Notifier les administrateurs des nouveaux litiges</label>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Coordonnées</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="admin_email" class="form-label">Email administrateur</label>
                                <input type="email" class="form-control" id="admin_email" 
                                    name="admin_email" value="<?= htmlspecialchars($settings['admin_email']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="support_phone" class="form-label">Téléphone support</label>
                                <input type="tel" class="form-control" id="support_phone" 
                                    name="support_phone" value="<?= htmlspecialchars($settings['support_phone']) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>