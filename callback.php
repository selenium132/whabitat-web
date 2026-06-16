<?php
require_once 'config.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Invalid Request - 認証コードがありません。<br><a href="index.php">トップページへ</a>');
}

// CSRF対策: state を常に検証する（スキップ経路なし）。
// 通常はセッションの state を使い、セッションCookieが維持されない環境
// （LINEアプリ内ブラウザ等）では login.php が併載した Cookie の state で検証する。
$state = $_GET['state'] ?? '';
$saved_state = $_SESSION['line_state'] ?? ($_COOKIE['line_state'] ?? '');
unset($_SESSION['line_state']);
setcookie('line_state', '', time() - 3600, '/');
if (empty($saved_state) || empty($state) || !hash_equals($saved_state, $state)) {
    die('セッションの有効期限が切れたか、不正なアクセスです。<br><a href="login.php">もう一度ログイン</a>');
}

// 1. Get Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/oauth2/v2.1/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => LINE_CALLBACK_URL,
    'client_id' => LINE_CHANNEL_ID,
    'client_secret' => LINE_CHANNEL_SECRET,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$token_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$token_data = json_decode($response, true);

if ($token_http !== 200 || !isset($token_data['access_token'])) {
    error_log('LINE token exchange failed: HTTP ' . $token_http . ' ' . $response);
    die('LINEログインに失敗しました。<br><a href="login.php">もう一度ログイン</a>');
}

// 2. Get User Profile
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/profile');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$profile_response = curl_exec($ch);
$profile_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$profile = json_decode($profile_response, true);

if ($profile_http !== 200 || empty($profile['userId'])) {
    error_log('LINE profile fetch failed: HTTP ' . $profile_http . ' ' . $profile_response);
    die('LINEプロフィールの取得に失敗しました。<br><a href="login.php">もう一度ログイン</a>');
}

$line_user_id = $profile['userId'];
$line_name = $profile['displayName'] ?? '';
$avatar_url = $profile['pictureUrl'] ?? '';

// 3. Check DB
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
$stmt->execute([$line_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Existing User - Update LINE name and avatar in case they changed
    $stmt = $pdo->prepare("UPDATE users SET line_name = ?, avatar_url = ? WHERE id = ?");
    $stmt->execute([$line_name, $avatar_url, $user['id']]);

    // セッション固定対策: ログイン確定時にセッションIDを再生成
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['is_approved'] = $user['is_approved']; // Set Approval Status
    
    // If profile is incomplete, force update
    if (empty($user['name']) || empty($user['student_id'])) {
        header("Location: register_profile.php");
    } else {
        if (!empty($_SESSION['redirect_after_login'])) {
            $redirect_url = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header("Location: " . $redirect_url);
        } else {
            header("Location: dashboard.php");
        }
    }
} else {
    // New User -> Create basic record and redirect to profile fill
    $stmt = $pdo->prepare("INSERT INTO users (line_user_id, line_name, avatar_url) VALUES (?, ?, ?)");
    $stmt->execute([$line_user_id, $line_name, $avatar_url]);

    // セッション固定対策: ログイン確定時にセッションIDを再生成
    session_regenerate_id(true);
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['role'] = 'member';
    
    header("Location: register_profile.php");
}
exit;
?>
