<!-- transporteur-provincial-gabonais/inscription.php -->
<?php
require_once 'includes/functions.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registration logic
    $role = $_GET['role'] ?? 'client';
    
    if ($role === 'transporteur') {
        // Transporter registration
        $result = registerTransporter($_POST);
        if ($result === true) {
            header("Location: connexion.php?success=transporteur");
            exit();
        } else {
            $error = $result;
        }
    } else {
        // Client registration
        $result = registerUser($_POST);
        if ($result === true) {
            header("Location: connexion.php?success=client");
            exit();
        } else {
            $error = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - TPG</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <?php if ($_GET['role'] === 'transporteur'): ?>
                                <i class="fas fa-truck me-2"></i> Inscription Transporteur
                            <?php else: ?>
                                <i class="fas fa-user me-2"></i> Inscription Client
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom</label>
                                    <input type="text" name="prenom" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="nom" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="telephone" class="form-control" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Mot de passe</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmer mot de passe</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            
                            <?php if ($_GET['role'] === 'transporteur'): ?>
                                <hr>
                                <h5 class="mb-3"><i class="fas fa-truck me-2"></i> Informations du véhicule</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Type de véhicule</label>
                                        <select name="type_vehicule" class="form-select" required>
                                            <option value="">Sélectionner</option>
                                            <option value="voiture">Voiture</option>
                                            <option value="camionnette">Camionnette</option>
                                            <option value="camion">Camion</option>
                                            <option value="moto">Moto</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Capacité (kg)</label>
                                        <input type="number" name="capacite_kg" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Plaque d'immatriculation</label>
                                        <input type="text" name="plaque_immatriculation" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Numéro de permis</label>
                                        <input type="text" name="permis_numero" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Provinces desservies</label>
                                    <select name="provinces[]" class="form-select" multiple required>
                                        <?php foreach (getProvinces() as $id => $name): ?>
                                            <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Maintenez Ctrl pour sélectionner plusieurs provinces</small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">J'accepte les conditions d'utilisation</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-1"></i> S'inscrire
                            </button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        Déjà inscrit? <a href="connexion.php">Connectez-vous</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>