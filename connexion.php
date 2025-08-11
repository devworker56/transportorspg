<?php
require_once 'includes/header.php';

if (isLoggedIn()) {
    header("Location: /");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    $result = loginUser($email, $password);
    
    if ($result === true) {
        // Redirection après connexion
        if (isAdmin()) {
            header("Location: /admin/dashboard.php");
        } elseif (isTransporter()) {
            header("Location: /transporteur/dashboard.php");
        } else {
            header("Location: /");
        }
        exit();
    } else {
        $error_message = $result;
    }
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <img src="/assets/images/logo.png" alt="Logo TPG" width="80" class="mb-3">
                        <h2 class="fw-bold">Connexion</h2>
                        <p class="text-muted">Accédez à votre compte</p>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="text-end">
                                <a href="/forgot-password.php" class="small">Mot de passe oublié?</a>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Se connecter</button>
                        
                        <div class="text-center">
                            <p class="text-muted mb-0">Pas encore de compte? <a href="/inscription.php">S'inscrire</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>