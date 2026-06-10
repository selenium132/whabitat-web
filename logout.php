<?php
require_once 'config.php'; // applies session cookie settings and starts the session

// Clear all session data
$_SESSION = [];

// Expire the session cookie (matching the params used when the session was created)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

header("Location: index.php");
exit;
?>
