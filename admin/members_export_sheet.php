<?php
require_once '../config.php';
require_once '../google_user_sheets.php';

requireLogin();

// Only Admin can export members
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

// 本人のGoogle Drive連携を要求（未連携なら一度だけOAuthへ。認証後ここへ戻る）。
requireGoogleDriveConnection('admin/members_export_sheet.php');

$pdo = getDB();

try {
    // 本人のGoogleアカウントで本人のDriveに名簿シートを作成/更新（本人が所有＝編集自由）
    $url = gus_export_members_to_user_sheet($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    error_log('member sheet export failed (user ' . ($_SESSION['user_id'] ?? '?') . '): ' . $e->getMessage());
    // トークン失効など → 連携情報をリセットして再連携を促す
    $rec = gus_get_record($_SESSION['user_id']);
    if ($rec) { unset($rec['refresh_token']); gus_set_record($_SESSION['user_id'], $rec); }
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>再連携が必要です</title></head><body style="font-family:sans-serif;max-width:480px;margin:2rem auto;padding:0 1.2rem;line-height:1.8;color:#2a2a2a">'
       . '<h1 style="font-size:1.15rem">再連携が必要です</h1>'
       . '<p>Google連携の有効期限が切れたか、アクセスが取り消された可能性があります。もう一度「シートに出力」を押すと再連携できます。</p>'
       . '<p><a href="members.php">メンバー管理へ戻る</a></p></body></html>';
    exit;
}

// 成功。アプリ内ブラウザ（LINE等）では docs.google.com を直接開くと Google ログインで弾かれるため、
// 「Sheetsアプリで開く」案内を表示する。実ブラウザならそのまま遷移。
if (isInAppBrowser()) {
    $u = htmlspecialchars($url, ENT_QUOTES);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>名簿シートを更新しました</title>'
       . '<style>body{font-family:"Noto Sans JP",sans-serif;background:#faf9f6;color:#2a2a2a;margin:0;padding:2rem 1.2rem;line-height:1.8}'
       . '.box{max-width:480px;margin:1.5rem auto;background:#fff;border:1px solid #e6e2d9;border-radius:12px;padding:1.6rem}'
       . 'h1{font-size:1.15rem;margin:0 0 1rem}a.btn{display:block;text-align:center;background:#1a1a1a;color:#fff;text-decoration:none;padding:0.9rem;border-radius:8px;font-weight:600;margin:1.2rem 0}'
       . '.muted{color:#8d877c;font-size:.86rem}</style></head><body><div class="box">'
       . '<h1>✓ 名簿シートを更新しました</h1>'
       . '<p>あなたのGoogleアカウントの名簿シートに最新データを書き込みました。下のボタンで開けます（Googleスプレッドシートアプリが入っていればアプリで開きます）。</p>'
       . '<a class="btn" href="' . $u . '">名簿シートを開く</a>'
       . '<p class="muted">開けない場合は、Googleスプレッドシート（またはドライブ）アプリの「最近使用したアイテム」から「[WHABITAT] メンバー名簿」を開いてください。あなたが所有者なので編集できます。</p>'
       . '</div></body></html>';
    exit;
}

header('Location: ' . $url);
exit;
