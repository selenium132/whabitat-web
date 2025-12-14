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

// For LINE in-app browser: Show a page first, then redirect via JavaScript
// This gives the browser time to store cookies before navigating away
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン中... | WHABITAT</title>
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>LINEログインに移動しています...</p>
    </div>
    <script>
        // Wait a moment for cookies to be set, then redirect
        setTimeout(function() {
            window.location.href = <?php echo json_encode($url); ?>;
        }, 100);
    </script>
</body>
</html>
