<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'gestion_transporteurs.php' ? 'active' : '' ?>" href="gestion_transporteurs.php">
                    <i class="fas fa-truck me-2"></i>
                    Transporteurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'gestion_commandes.php' ? 'active' : '' ?>" href="gestion_commandes.php">
                    <i class="fas fa-boxes me-2"></i>
                    Livraisons
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'disputes.php' ? 'active' : '' ?>" href="disputes.php">
                    <i class="fas fa-gavel me-2"></i>
                    Litiges
                    <span class="badge bg-danger rounded-pill float-end">3</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'rapports.php' ? 'active' : '' ?>" href="rapports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Rapports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'parametres.php' ? 'active' : '' ?>" href="parametres.php">
                    <i class="fas fa-cog me-2"></i>
                    Paramètres
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Actions rapides</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="gestion_transporteurs.php?filter=pending">
                    <i class="fas fa-user-clock me-2"></i>
                    Transporteurs en attente
                    <span class="badge bg-warning rounded-pill float-end">5</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="gestion_commandes.php?filter=active">
                    <i class="fas fa-shipping-fast me-2"></i>
                    Livraisons actives
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="disputes.php?filter=open">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Litiges non résolus
                </a>
            </li>
        </ul>
    </div>
</div>