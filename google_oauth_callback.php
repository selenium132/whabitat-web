<?php
// =====================================================================
// Google OAuth コールバック（各自アカウント方式）
// 認可コード→トークン交換でリフレッシュトークンを取得し、本人(user_id)に紐づけ保存。
// 以降は本人のDriveに名簿シートを作成/更新できる（ブラウザ不要）。
// 認可（誰が出力できるか）は各出力ページの admin チェックが担う。
// =====================================================================
require_once 'config.php';
require_once 'google_user_sheets.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// エラー時に「戻る」先。ダッシュボード経由の二度手間を避けるため、
// 可能なら元の出力ページ（$_SESSION['google_oauth_return']）に戻す。
// オープンリダイレクト対策として members_export_sheet.php:81 と同じホワイトリストで検証。
function gocReturnUrl() {
    $return = $_SESSION['google_oauth_return'] ?? 'dashboard.php';
    if (!preg_match('#^(admin/members_export_sheet\.php|form_google_sheet\.php)(\?[^\s]*)?$#', $return)) {
        $return = 'dashboard.php';
    }
    return $return;
}

// CSRF: state を必須検証
$state = $_GET['state'] ?? '';
$saved_state = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if (empty($saved_state) || empty($state) || !hash_equals($saved_state, $state)) {
    die('連携の有効期限が切れました。もう一度お試しください。<br><a href="' . htmlspecialchars(gocReturnUrl(), ENT_QUOTES) . '">もう一度試す</a>');
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $err = $_GET['error'] ?? 'コードがありません';
    die('Google連携がキャンセル/失敗しました（' . htmlspecialchars($err) . '）。<br><a href="' . htmlspecialchars(gocReturnUrl(), ENT_QUOTES) . '">もう一度試す</a>');
}

// 1. 認可コード → トークン交換（refresh_token + id_token を取得）
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
    die('Google連携に失敗しました。<br><a href="' . htmlspecialchars(gocReturnUrl(), ENT_QUOTES) . '">もう一度試す</a>');
}

// 2. id_token を Google の tokeninfo で検証してメールを取得（aud/email_verified を厳格確認）
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($data['id_token']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$vresp = curl_exec($ch);
$vhttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$claims = json_decode($vresp, true);
$email_verified = (($claims['email_verified'] ?? '') === true) || (($claims['email_verified'] ?? '') === 'true');
if ($vhttp !== 200 || empty($claims['email']) || !$email_verified || ($claims['aud'] ?? '') !== GOOGLE_OAUTH_CLIENT_ID) {
    error_log('Google id_token verify failed: HTTP ' . $vhttp . ' ' . $vresp);
    die('Googleアカウントの確認に失敗しました。<br><a href="' . htmlspecialchars(gocReturnUrl(), ENT_QUOTES) . '">もう一度試す</a>');
}

// 3. リフレッシュトークンを本人(user_id)に紐づけて保存
//    prompt=consent のため通常は毎回 refresh_token が返る。万一返らない場合は既存を保持。
$uid = $_SESSION['user_id'];
$rec = gus_get_record($uid) ?: [];
$rec['email'] = $claims['email'];
if (!empty($data['refresh_token'])) {
    $rec['refresh_token'] = $data['refresh_token'];
}
if (empty($rec['refresh_token'])) {
    error_log('Google OAuth: refresh_token not returned for user ' . $uid);
    die('Google連携でリフレッシュトークンが取得できませんでした。Googleアカウントの「サードパーティ アクセス」から本アプリを一度解除し、再度お試しください。<br><a href="' . htmlspecialchars(gocReturnUrl(), ENT_QUOTES) . '">もう一度試す</a>');
}
gus_set_record($uid, $rec);

// 4. 元の出力ページへ戻る（ホワイトリスト検証）
$return = gocReturnUrl();
unset($_SESSION['google_oauth_return']);
header('Location: ' . $return);
exit;
