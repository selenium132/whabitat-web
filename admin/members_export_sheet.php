<?php
require_once '../config.php';
require_once '../google_user_sheets.php';

requireAdmin();

// 名簿全件を本人の Drive に書き出す＝持続コピーを作る操作のため POST + CSRF 必須。
// 従来は GET で発火でき、連携済み管理者にリンクを踏ませるだけで意図しない出力を起こせた。
// Google OAuth から戻ってきた直後（GET）は、ワンクリックの確認画面を挟んで POST させる。
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $csrf = htmlspecialchars(generateCsrfToken(), ENT_QUOTES);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>名簿をシートに出力</title>'
       . '<style>body{font-family:"Noto Sans JP",sans-serif;background:#faf9f6;color:#2a2a2a;margin:0;padding:2rem 1.2rem;line-height:1.8}'
       . '.box{max-width:480px;margin:1.5rem auto;background:#fff;border:1px solid #e6e2d9;border-radius:12px;padding:1.6rem}'
       . 'h1{font-size:1.15rem;margin:0 0 1rem}button.btn{display:block;width:100%;background:#1a1a1a;color:#fff;border:none;padding:0.9rem;border-radius:8px;font-weight:600;font-size:1rem;margin:1.2rem 0;cursor:pointer}'
       . '.muted{color:#6b655a;font-size:.86rem}a{color:#1a1a1a}</style></head><body><div class="box">'
       . '<h1>名簿をGoogleスプレッドシートに出力</h1>'
       . '<p>あなたのGoogleアカウントのDriveに「[WHABITAT] メンバー名簿」を作成/更新します（あなたが所有者になります）。</p>'
       . '<form method="POST" action="members_export_sheet.php"><input type="hidden" name="csrf_token" value="' . $csrf . '">'
       . '<button type="submit" class="btn">シートに出力する</button></form>'
       . '<p class="muted">この操作は監査ログに記録されます。 <a href="members.php">メンバー管理へ戻る</a></p>'
       . '</div></body></html>';
    exit;
}
validateCsrfToken($_POST['csrf_token'] ?? '');

// 本人のGoogle Drive連携を要求（未連携なら一度だけOAuthへ。認証後ここへ GET で戻り、上の確認画面から再度 POST する）。
requireGoogleDriveConnection('admin/members_export_sheet.php');

$pdo = getDB();

try {
    // 本人のGoogleアカウントで本人のDriveに名簿シートを作成/更新（本人が所有＝編集自由）
    $url = gus_export_members_to_user_sheet($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $msg = $e->getMessage();
    error_log('member sheet export failed (user ' . ($_SESSION['user_id'] ?? '?') . '): ' . $msg);
    // トークン失効のときだけ連携情報をリセット（それ以外で消すと再OAuthループになるため消さない）
    if (strncmp($msg, 'TOKEN_REFRESH_FAILED', 20) === 0) {
        $rec = gus_get_record($_SESSION['user_id']);
        if ($rec) { unset($rec['refresh_token']); gus_set_record($_SESSION['user_id'], $rec); }
    }
    $reconnect = (strncmp($msg, 'TOKEN_REFRESH_FAILED', 20) === 0);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>出力に失敗しました</title></head><body style="font-family:sans-serif;max-width:520px;margin:2rem auto;padding:0 1.2rem;line-height:1.7;color:#2a2a2a">'
       . '<h1 style="font-size:1.15rem">シート出力に失敗しました</h1>'
       . '<p>' . ($reconnect
            ? 'Google連携の有効期限が切れたか、アクセスが取り消された可能性があります。もう一度「シートに出力」を押すと再連携できます。'
            : '時間をおいて、もう一度「シートに出力」をお試しください。解消しない場合は管理者にお問い合わせください。')
       . '</p>'
       . '<p><a href="members.php">メンバー管理へ戻る</a></p></body></html>';
    exit;
}

// 監査ログ: 誰がいつ名簿全件をスプレッドシートへ吸い出したかを記録（持続コピーが作られる操作のため特に重要）
$export_count = 0;
try { $export_count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Exception $e) { error_log('export count failed: ' . $e->getMessage()); }
auditLog('export_sheet', null, null, '名簿をGoogleスプレッドシートへ出力（' . $export_count . '件）');

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
