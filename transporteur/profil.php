<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTransporter();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get transporter info
$stmt = $db->prepare("SELECT t.*, u.prenom, u.nom, u.email, u.telephone 
    FROM transporteurs t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$transporter = $stmt->fetch(PDO::FETCH_ASSOC);

// Get provinces served
$stmt = $db->prepare("SELECT p.id, p.nom 
    FROM routes_transporteur r
    JOIN provinces p ON r.province_depart_id = p.id
    WHERE r.transporteur_id = ?");
$stmt->execute([$transporter['id']]);
$provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Update user info
        $stmt = $db->prepare("UPDATE users SET 
            prenom = ?, nom = ?, telephone = ?
            WHERE id = ?");
        $stmt->execute([
            $_POST['prenom'],
            $_POST['nom'],
            $_POST['telephone'],
            $user_id
        ]);
        
        // Update transporter info
        $stmt = $db->prepare("UPDATE transporteurs SET
            type_vehicule = ?, capacite_kg = ?,
            plaque_immatriculation = ?, permis_numero = ?
            WHERE user_id = ?");
        $stmt->execute([
            $_POST['type_vehicule'],
            $_POST['capacite_kg'],
            $_POST['plaque_immatriculation'],
            $_POST['permis_numero'],
            $user_id
        ]);
        
        // Update provinces if changed
        if (isset($_POST['provinces'])) {
            // Delete old provinces
            $stmt = $db->prepare("DELETE FROM routes_transporteur WHERE transporteur_id = ?");
            $stmt->execute([$transporter['id']]);
            
            // Add new provinces
            $stmt = $db->prepare("INSERT INTO routes_transporteur 
                (transporteur_id, province_depart_id, province_arrivee_id)
                VALUES (?, ?, 1)"); // 1 = Estuaire (Libreville)
            
            foreach ($_POST['provinces'] as $province_id) {
                $stmt->execute([$transporter['id'], $province_id]);
            }
        }
        
        $db->commit();
        $_SESSION['flash'] = "Profil mis à jour avec succès";
        header("Location: profil.php");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Erreur de mise à jour: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - TPG</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mon Profil</h1>
                </div>
                
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4><i class="fas fa-id-card me-2"></i>Informations personnelles</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Prénom</label>
                                        <input type="text" name="prenom" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['prenom']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nom</label>
                                        <input type="text" name="nom" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['nom']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['email']); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" name="telephone" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['telephone']); ?>" required>
                                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4><i class="fas fa-truck me-2"></i>Informations du transporteur</h4>
                            </div>
                            <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Type de véhicule</label>
                                        <select name="type_vehicule" class="form-select" required>
                                            <option value="voiture" <?php echo $transporter['type_vehicule'] === 'voiture' ? 'selected' : ''; ?>>Voiture</option>
                                            <option value="camionnette" <?php echo $transporter['type_vehicule'] === 'camionnette' ? 'selected' : ''; ?>>Camionnette</option>
                                            <option value="camion" <?php echo $transporter['type_vehicule'] === 'camion' ? 'selected' : ''; ?>>Camion</option>
                                            <option value="moto" <?php echo $transporter['type_vehicule'] === 'moto' ? 'selected' : ''; ?>>Moto</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Capacité (kg)</label>
                                        <input type="number" name="capacite_kg" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['capacite_kg']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Plaque d'immatriculation</label>
                                        <input type="text" name="plaque_immatriculation" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['plaque_immatriculation']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Numéro de permis</label>
                                        <input type="text" name="permis_numero" class="form-control" 
                                            value="<?php echo htmlspecialchars($transporter['permis_numero']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Provinces desservies</label>
                                        <select name="provinces[]" class="form-select" multiple required>
                                            <?php foreach (getProvinces() as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" 
                                                    <?php echo in_array($id, array_column($provinces, 'id')) ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Maintenez Ctrl pour sélectionner plusieurs provinces</small>
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Enregistrer les modifications
                    </button>
                </div>
                </form>
                
                <!-- Account Status -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-shield-alt me-2"></i>Statut du compte</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?php 
                            echo $transporter['statut'] === 'approuve' ? 'success' : 
                                ($transporter['statut'] === 'en_attente' ? 'warning' : 'danger'); 
                        ?>">
                            <h5 class="alert-heading">
                                <?php 
                                echo $transporter['statut'] === 'approuve' ? 'Compte approuvé' : 
                                    ($transporter['statut'] === 'en_attente' ? 'En attente d\'approbation' : 'Compte rejeté/banni');
                                ?>
                            </h5>
                            <p>
                                <?php if ($transporter['statut'] === 'approuve'): ?>
                                    Votre compte transporteur est actif et vous pouvez faire des offres.
                                <?php elseif ($transporter['statut'] === 'en_attente'): ?>
                                    Votre compte est en cours de vérification par nos administrateurs.
                                <?php else: ?>
                                    Votre compte a été désactivé. Contactez le support pour plus d'informations.
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($transporter['statut'] === 'approuve'): ?>
                            <div class="alert alert-info">
                                <h5 class="alert-heading">Réputation: <?php echo number_format($transporter['note_moyenne'], 1); ?>/5</h5>
                                <p>
                                    Basée sur <?php echo $transporter['livraisons_completees'] ?? 0; ?> livraisons complétées.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>