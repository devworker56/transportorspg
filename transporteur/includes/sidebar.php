<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profil.php' ? 'active' : ''; ?>" 
                   href="profil.php">
                    <i class="fas fa-user me-2"></i>
                    Mon Profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'offres.php' ? 'active' : ''; ?>" 
                   href="offres.php">
                    <i class="fas fa-hand-holding-usd me-2"></i>
                    Mes Offres
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'commandes.php' ? 'active' : ''; ?>" 
                   href="commandes.php">
                    <i class="fas fa-truck-loading me-2"></i>
                    Mes Commandes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'paiements.php' ? 'active' : ''; ?>" 
                   href="paiements.php">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Paiements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reputation.php' ? 'active' : ''; ?>" 
                   href="reputation.php">
                    <i class="fas fa-star me-2"></i>
                    Réputation
                </a>
            </li>
        </ul>
        
        <hr>
        
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="../contact.php">
                    <i class="fas fa-question-circle me-2"></i>
                    Support
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="../includes/auth.php?action=logout">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Déconnexion
                </a>
            </li>
        </ul>
    </div>
</div>