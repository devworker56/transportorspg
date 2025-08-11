<!-- transporteur-provincial-gabonais/transporteur/offres.php -->
<?php
require_once '../includes/auth.php';
protectPage('transporteur');

$db = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer le profil du transporteur
$stmt = $db->prepare("SELECT t.id FROM transporteurs t WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$transporteur = $stmt->fetch(PDO::FETCH_ASSOC);

// Gérer la soumission d'offre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marchandise_id = (int)$_POST['marchandise_id'];
    $prix = (float)$_POST['prix'];
    $date_proposee = $_POST['date_proposee'];
    $commentaire = sanitize($_POST['commentaire']);
    
    try {
        $stmt = $db->prepare("INSERT INTO offres_transport 
            (transporteur_id, marchandise_id, prix, date_proposee, commentaire, statut)
            VALUES (?, ?, ?, ?, ?, 'en_attente')");
            
        $stmt->execute([
            $transporteur['id'],
            $marchandise_id,
            $prix,
            $date_proposee,
            $commentaire
        ]);
        
        $_SESSION['flash_message'] = "Votre offre a été soumise avec succès!";
        $_SESSION['flash_type'] = "success";
        header("Location: dashboard.php");
        exit();
        
    } catch(PDOException $e) {
        $error = "Erreur lors de la soumission de l'offre: " . $e->getMessage();
    }
}

// Récupérer les offres existantes
$stmt = $db->prepare("SELECT o.*, m.nom AS marchandise_nom 
                      FROM offres_transport o
                      JOIN marchandises m ON m.id = o.marchandise_id
                      WHERE o.transporteur_id = ?
                      ORDER BY o.created_at DESC");
$stmt->execute([$transporteur['id']]);
$offres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si marchandise_id est spécifié dans l'URL
$marchandise = null;
if (isset($_GET['marchandise_id'])) {
    $marchandise_id = (int)$_GET['marchandise_id'];
    
    // Récupérer les détails de la marchandise
    $stmt = $db->prepare("SELECT * FROM marchandises WHERE id = ?");
    $stmt->execute([$marchandise_id]);
    $marchandise = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des offres - TPG</title>
    <?php define('PAGE_TITLE', 'Mes offres'); ?>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <div class="container my-5">
        <div class="d-flex justify-content-between mb-4">
            <h1><i class="fas fa-hand-holding-usd me-2"></i> Mes offres de transport</h1>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Retour
            </a>
        </div>
        
        <?php if (isset($marchandise)): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Faire une offre pour: <?php echo $marchandise['nom']; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="marchandise_id" value="<?php echo $marchandise['id']; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Prix proposé (XAF)</label>
                                <input type="number" name="prix" class="form-control" min="1000" step="500" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date proposée</label>
                                <input type="date" name="date_proposee" class="form-control" required
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       max="<?php echo $marchandise['date_limite']; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Commentaire (optionnel)</label>
                            <textarea name="commentaire" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <a href="offres.php" class="btn btn-outline-secondary me-2">Annuler</a>
                            <button type="submit" class="btn btn-primary">Soumettre l'offre</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Historique des offres</h5>
            </div>
            <div class="card-body">
                <?php if (empty($offres)): ?>
                    <div class="alert alert-info">
                        Vous n'avez soumis aucune offre pour le moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Marchandise</th>
                                    <th>Prix proposé</th>
                                    <th>Date proposée</th>
                                    <th>Statut</th>
                                    <th>Date soumission</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($offres as $offre): ?>
                                    <tr>
                                        <td><?php echo $offre['marchandise_nom']; ?></td>
                                        <td><?php echo number_format($offre['prix'], 0, ',', ' '); ?> XAF</td>
                                        <td><?php echo date('d/m/Y', strtotime($offre['date_proposee'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($offre['statut'] === 'accepte') ? 'success' : 
                                                     (($offre['statut'] === 'rejete') ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php 
                                                echo ($offre['statut'] === 'accepte') ? 'Acceptée' : 
                                                     (($offre['statut'] === 'rejete') ? 'Rejetée' : 'En attente'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($offre['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>