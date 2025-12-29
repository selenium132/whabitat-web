<?php
require_once 'config.php';

// Generate state (kept for compatibility, but not strictly validated due to LINE in-app browser)
$state = bin2hex(random_bytes(32));
$_SESSION['line_state'] = $state;

// Check for redirect URL passed from requireLogin (supports hash/fragments)
if (!empty($_GET['next'])) {
    $next_url = $_GET['next'];
    // Validating basic security to prevent open redirect to external domains
    $parsed = parse_url($next_url);
    if (empty($parsed['host']) || $parsed['host'] === $_SERVER['HTTP_HOST'] || $parsed['host'] === 'whabitathome.com') {
         $_SESSION['redirect_after_login'] = $next_url;
    }
}

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
