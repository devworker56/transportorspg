<?php
require_once 'functions.php';

session_start();

function authenticate($email, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        
        return true;
    }
    
    return false;
}

function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logout();
        header("Location: connexion.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: connexion.php");
        exit();
    }
    checkSessionTimeout();
}

function requireTransporter() {
    requireAuth();
    if (!isTransporter()) {
        header("HTTP/1.1 403 Forbidden");
        exit("Accès refusé: réservé aux transporteurs");
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header("HTTP/1.1 403 Forbidden");
        exit("Accès refusé: réservé aux administrateurs");
    }
}
?>