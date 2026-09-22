<?php
require_once __DIR__ . '/../config.php';

// Destroy session completely
 $_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start new session for flash message
session_start();
 $_SESSION['flash_msg']  = 'Logged out successfully.';
 $_SESSION['flash_type'] = 'success';

header('Location: index.php');
exit;
?>