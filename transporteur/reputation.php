<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTransporter();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get transporter info
$stmt = $db->prepare("SELECT t.note_moyenne, t.livraisons_completees, 
    u.prenom, u.nom
    FROM transporteurs t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?");
$stmt->execute([$user_id]);
$transporter = $stmt->fetch(PDO::FETCH_ASSOC);

// Get reviews
$stmt = $db->prepare("SELECT e.*, u.prenom, u.nom, m.nom as marchandise_nom
    FROM evaluations e
    JOIN affectations_transport a ON e.affectation_id = a.id
    JOIN marchandises m ON a.marchandise_id = m.id
    JOIN users u ON e.evaluateur_id = u.id
    WHERE a.transporteur_id = ?
    ORDER BY e.created_at DESC");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Réputation - TPG</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Ma Réputation</h1>
                </div>
                
                <!-- Reputation Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-star me-2"></i>Note moyenne</h4>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-2 text-warning mb-3">
                                    <?php echo number_format($transporter['note_moyenne'], 1); ?>/5
                                </div>
                                <div class="star-rating mb-3">
                                    <?php
                                    $full_stars = floor($transporter['note_moyenne']);
                                    $half_star = ($transporter['note_moyenne'] - $full_stars) >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $full_stars) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($half_star && $i == $full_stars + 1) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <p class="text-muted">
                                    Basée sur <?php echo $transporter['livraisons_completees']; ?> livraisons
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-chart-bar me-2"></i>Répartition des notes</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="ratingsChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Reviews -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-comments me-2"></i>Avis récents</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reviews)): ?>
                            <div class="alert alert-info">
                                Vous n'avez encore reçu aucun avis.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($review['prenom'] . ' ' . htmlspecialchars($review['nom'])); ?></h5>
                                            <div class="star-rating text-warning">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $review['note'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <p class="mb-1">
                                            <strong>Livraison:</strong> <?php echo htmlspecialchars($review['marchandise_nom']); ?>
                                        </p>
                                        <p class="mb-1"><?php echo htmlspecialchars($review['commentaire']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Ratings distribution chart
        const ctx = document.getElementById('ratingsChart').getContext('2d');
        const ratingsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1 étoile', '2 étoiles', '3 étoiles', '4 étoiles', '5 étoiles'],
                datasets: [{
                    label: 'Nombre d\'avis',
                    data: [
                        <?php 
                        $counts = [0, 0, 0, 0, 0];
                        foreach ($reviews as $review) {
                            $counts[$review['note'] - 1]++;
                        }
                        echo implode(', ', $counts);
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(255, 205, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(54, 162, 235, 0.7)'
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(255, 159, 64)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(54, 162, 235)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>