<?php
require_once 'config.php';

// CSRF対策: state を生成。セッションに加え、Cookie にも併載する。
// LINEアプリ内ブラウザ等でセッションCookieが維持されない環境でも、
// SameSite=Lax の専用Cookie 経由で callback 側が state を検証できるようにし、
// 「state検証スキップ」経路を無くす（ログインCSRF対策）。
$state = bin2hex(random_bytes(32));
$_SESSION['line_state'] = $state;
setcookie('line_state', $state, [
    'expires'  => time() + 600, // 10分
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax', // OAuthリダイレクト(トップレベルGET)で送出される
]);

// Check for redirect URL passed from requireLogin (supports hash/fragments)
if (!empty($_GET['next'])) {
    $next_url = $_GET['next'];
    // Validating basic security to prevent open redirect to external domains.
    // Reject protocol-relative ("//evil.com") and backslash-based ("/\evil.com", "\\evil.com")
    // bypasses before host inspection, since browsers may treat these as external hosts.
    $normalized = str_replace('\\', '/', $next_url);
    if (strncmp($normalized, '//', 2) !== 0) {
        $parsed = parse_url($next_url);
        // Allow either a same-origin relative path (no host, must start with a single "/")
        // or an absolute URL whose host strictly matches an allowed host.
        if (empty($parsed['host'])) {
            if (isset($next_url[0]) && $next_url[0] === '/') {
                $_SESSION['redirect_after_login'] = $next_url;
            }
        } elseif ($parsed['host'] === $_SERVER['HTTP_HOST'] || $parsed['host'] === 'whabitathome.com') {
            $_SESSION['redirect_after_login'] = $next_url;
        }
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
