<?php
// =====================================================================
// Google OAuth コールバック
// スプシ出力時に「押した本人のGoogleアカウント」を特定するための認証受け口。
// 本人のメールを検証してセッションに保存し、元の出力ページへ戻す。
// 認可（誰が出力できるか）は各出力ページの admin / event-admin チェックが担う。
// =====================================================================
require_once 'config.php';
requireLogin();

// スプシ共有は管理者機能。念のためここでも admin を要求する。
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// CSRF: state を必須検証（requireGoogleAccount が生成したもの）
$state = $_GET['state'] ?? '';
$saved_state = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if (empty($saved_state) || empty($state) || !hash_equals($saved_state, $state)) {
    die('不正なアクセス（stateが一致しません）。<br><a href="dashboard.php">ダッシュボードへ</a>');
}

// 認可コード（ユーザーが同意をキャンセルした場合は error が返る）
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $err = $_GET['error'] ?? 'コードがありません';
    die('Google認証がキャンセル/失敗しました（' . htmlspecialchars($err) . '）。<br><a href="dashboard.php">ダッシュボードへ</a>');
}

// 1. 認可コード → トークン交換
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $code,
    'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
    'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_OAUTH_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($resp, true);
if ($http !== 200 || empty($data['id_token'])) {
    error_log('Google token exchange failed: HTTP ' . $http . ' ' . $resp);
    die('Google認証に失敗しました。<br><a href="dashboard.php">ダッシュボードへ</a>');
}

// 2. id_token を Google の tokeninfo で検証（署名・iss・exp は Google 側が検証）
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($data['id_token']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$vresp = curl_exec($ch);
$vhttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$claims = json_decode($vresp, true);

// メール確認済み・aud（自分のクライアントID）一致を厳格にチェック
$email_verified = (($claims['email_verified'] ?? '') === true) || (($claims['email_verified'] ?? '') === 'true');
if ($vhttp !== 200 || empty($claims['email']) || !$email_verified || ($claims['aud'] ?? '') !== GOOGLE_OAUTH_CLIENT_ID) {
    error_log('Google id_token verify failed: HTTP ' . $vhttp . ' ' . $vresp);
    die('Googleアカウントの確認に失敗しました。<br><a href="dashboard.php">ダッシュボードへ</a>');
}

// 3. 検証済みのGoogleメールをセッションに保存（このセッション中は再認証不要）
$_SESSION['google_email'] = $claims['email'];

// 4. 元の出力ページへ戻る（ホワイトリスト検証。改行混入も正規表現で排除）
$return = $_SESSION['google_oauth_return'] ?? 'dashboard.php';
unset($_SESSION['google_oauth_return']);
if (!preg_match('#^(admin/members_export_sheet\.php|form_google_sheet\.php)(\?[^\s]*)?$#', $return)) {
    $return = 'dashboard.php';
}
header('Location: ' . $return);
exit;
