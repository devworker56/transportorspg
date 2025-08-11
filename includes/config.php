<!-- transporteur-provincial-gabonais/includes/config.php -->
<?php
// Configuration de base
define('SITE_NAME', 'Transporteur Provincial Gabonais');
define('SITE_URL', 'http://localhost/transporteur-provincial-gabonais');
define('ADMIN_EMAIL', 'admin@tpg.ga');

// Paramètres de base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'u834808878_db_tpg');
define('DB_USER', 'u834808878_transport');
define('DB_PASS', 'Ossouka@1968');

// Paramètres de session
define('SESSION_TIMEOUT', 3600); // 1 heure

// Configuration MPG
define('MPG_API_URL', 'https://marcheprovincial.ga/api/v1');//define('MPG_API_URL', 'https://marcheprovincial.ga/api/v1');
define('MPG_API_KEY', 'YOUR_MPG_API_KEY');//define('MPG_API_KEY', 'mpg_secure_api_key_12345');

// Autres configurations
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
?>