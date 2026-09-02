<?php
// 会員本人による「退会・個人情報削除」の申請。
// 実際の削除は管理者が admin/members.php で行う（誤操作防止・監査ログ維持のため即時削除はしない）。
// 申請は contact_messages(source='withdrawal') に記録し、管理者へ LINE で通知する。
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register_profile.php#withdraw");
    exit;
}
validateCsrfToken($_POST['csrf_token'] ?? '');

$reason = trim($_POST['reason'] ?? '');
if (mb_strlen($reason) > 1000) $reason = mb_substr($reason, 0, 1000);

$pdo = getDB();
$u = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$u->execute([$_SESSION['user_id']]);
$me = $u->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header("Location: logout.php");
    exit;
}

// 未処理の申請が既にあれば重複登録しない（連打・二重送信対策）
$dup = $pdo->prepare("SELECT 1 FROM contact_messages WHERE source = 'withdrawal' AND is_read = 0 AND message LIKE ? LIMIT 1");
$dup->execute(['%[user_id:' . (int)$me['id'] . ']%']);
if ($dup->fetchColumn()) {
    $_SESSION['withdrawal_notice'] = '退会の申請はすでに受け付けています。管理者の対応をお待ちください。';
    header("Location: register_profile.php#withdraw");
    exit;
}

$name = ($me['name'] !== null && $me['name'] !== '') ? $me['name'] : '（氏名未登録）';
$message = "退会・個人情報削除の申請 [user_id:" . (int)$me['id'] . "]\n"
         . "理由・備考: " . ($reason !== '' ? $reason : '（記載なし）');
$ins = $pdo->prepare("INSERT INTO contact_messages (name, email, message, source, is_read) VALUES (?, ?, ?, 'withdrawal', 0)");
$ins->execute([$name, $me['email'] ?: '', $message]);

linePushToAdmins("🚪 退会・データ削除の申請がありました\n\n会員: {$name}\n\n"
    . ($reason !== '' ? "理由: " . (mb_strlen($reason) > 100 ? mb_substr($reason, 0, 100) . '…' : $reason) . "\n\n" : '')
    . "名簿管理から対応してください: https://whabitathome.com/admin/messages.php?source=withdrawal");

$_SESSION['withdrawal_notice'] = '退会の申請を受け付けました。管理者が確認のうえ、アカウントと個人情報を削除します。';
header("Location: register_profile.php#withdraw");
exit;
