<?php
require_once 'config.php';

// Generate state for CSRF protection
$state = bin2hex(random_bytes(32));
$_SESSION['line_state'] = $state;

// Also set as cookie for browsers that don't preserve session across OAuth redirect
setcookie('line_state', $state, [
    'expires' => time() + 600, // 10 minutes
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None' // Required for cross-site OAuth
]);

$url = "https://access.line.me/oauth2/v2.1/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => LINE_CHANNEL_ID,
    'redirect_uri' => LINE_CALLBACK_URL,
    'state' => $state,
    'scope' => 'profile openid',
]);

header("Location: " . $url);
exit;
?>
