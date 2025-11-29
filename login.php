<?php
require_once 'config.php';

// Generate state for CSRF protection
$state = bin2hex(random_bytes(32));
$_SESSION['line_state'] = $state;

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
