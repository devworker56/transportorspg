<?php
require_once 'includes/auth.php';
require_once 'includes/mpg_integration.php';

$mpg = new MPGIntegration();
$products = $mpg->getProductsNeedingTransport();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporteur Provincial Gabonais - Accueil</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><i class="fas fa-truck me-2"></i> Marchandises à transporter vers Libreville</h1>
                <p class="lead">Trouvez des marchandises à transporter depuis les provinces gabonaises</p>
            </div>
            <div class="col-md-4 text-end">
                <?php if (isLoggedIn() && isTransporter()): ?>
                    <a href="transporteur/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt me-1"></i> Tableau de bord
                    </a>
                <?php else: ?>
                    <a href="inscription.php?role=transporteur" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Devenir transporteur
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Aucune marchandise disponible pour le moment.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><?php echo htmlspecialchars($product['nom']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Province:</strong> <?php echo getProvinceName($product['province_depart_id']); ?></p>
                                <p><strong>Poids:</strong> <?php echo htmlspecialchars($product['poids_kg']); ?> kg</p>
                                <p><strong>Dimensions:</strong> <?php echo htmlspecialchars($product['dimensions']); ?></p>
                                <p><strong>À livrer avant:</strong> <?php echo date('d/m/Y', strtotime($product['date_limite'])); ?></p>
                                
                                <div class="mt-3">
                                    <p class="mb-1"><strong>Adresse de ramassage:</strong></p>
                                    <p class="small"><?php echo htmlspecialchars($product['adresse_ramassage']); ?></p>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <?php if (isLoggedIn() && isTransporter()): ?>
                                    <a href="transporteur/offres.php?marchandise_id=<?php echo $product['id']; ?>" 
                                       class="btn btn-success w-100">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Faire une offre
                                    </a>
                                <?php elseif (!isLoggedIn()): ?>
                                    <a href="connexion.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-sign-in-alt me-1"></i> Connectez-vous pour proposer un transport
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>