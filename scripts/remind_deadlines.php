<?php
/**
 * 出欠確認/アンケートの自動リマインド（CLI専用・cron で1時間ごとに実行）
 *
 *  1) 締切24時間前リマインド : close_at が今から24時間以内のイベントで、まだ回答していない対象者へ LINE push
 *  2) 当日リマインド         : 今日が event_date のイベント(出欠確認)で「参加」と回答した人へ LINE push（朝7時台の実行時のみ）
 *
 * 二重送信防止: event_reminders テーブルに (event_id, kind) を記録し、同じ種別は1イベント1回だけ送る。
 * LINE の無料枠(月200通)を消費するため、送信数はログに出す。LINE未連携・ブロック済みは自動的に送れないだけで失敗にはしない。
 *
 * XServer Cron 設定例（毎時5分）:
 *   5 * * * *  /usr/bin/php /home/＜サーバーID＞/whabitathome.com/public_html/scripts/remind_deadlines.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';

$pdo = getDB();
$pdo->exec("CREATE TABLE IF NOT EXISTS event_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    sent_count INT NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_kind (event_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$site = 'https://whabitathome.com';
$totalSent = 0;

// 送信先 line_user_id の配列へ同一文言を multicast（500件ずつ）。戻り値は送信した宛先数。
function remindMulticast(array $lineIds, $text) {
    $lineIds = array_values(array_unique(array_filter($lineIds)));
    $sent = 0;
    foreach (array_chunk($lineIds, 500) as $chunk) {
        if (lineBotApiPost('message/multicast', ['to' => $chunk, 'messages' => [['type' => 'text', 'text' => $text]]])) {
            $sent += count($chunk);
        }
    }
    return $sent;
}

function alreadySent(PDO $pdo, $eventId, $kind) {
    $s = $pdo->prepare("SELECT 1 FROM event_reminders WHERE event_id = ? AND kind = ?");
    $s->execute([$eventId, $kind]);
    return (bool)$s->fetchColumn();
}
function markSent(PDO $pdo, $eventId, $kind, $count) {
    $pdo->prepare("INSERT IGNORE INTO event_reminders (event_id, kind, sent_count) VALUES (?, ?, ?)")->execute([$eventId, $kind, $count]);
}

// ---------------------------------------------------------------
// 1) 締切24時間前: 未回答の対象者へ
// ---------------------------------------------------------------
$stmt = $pdo->query("SELECT * FROM events
    WHERE is_archived = 0 AND close_at IS NOT NULL
      AND close_at > NOW() AND close_at <= (NOW() + INTERVAL 24 HOUR)");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
    if (alreadySent($pdo, $ev['id'], 'close24h')) continue;

    // 対象者: target_users 指定があればその人たち、無ければ承認済み会員全員
    $targets = [];
    if (!empty($ev['target_users'])) {
        $t = json_decode($ev['target_users'], true);
        if (is_array($t) && $t) $targets = array_map('intval', $t);
    }
    if ($targets) {
        $in = implode(',', array_fill(0, count($targets), '?'));
        $u = $pdo->prepare("SELECT id, line_user_id FROM users WHERE is_approved = 1 AND id IN ($in)");
        $u->execute($targets);
    } else {
        $u = $pdo->query("SELECT id, line_user_id FROM users WHERE is_approved = 1");
    }
    $users = $u->fetchAll(PDO::FETCH_ASSOC);

    // 回答済みを除外
    $a = $pdo->prepare("SELECT user_id FROM attendance WHERE event_id = ?");
    $a->execute([$ev['id']]);
    $answered = array_flip(array_map('intval', $a->fetchAll(PDO::FETCH_COLUMN)));

    $lineIds = [];
    foreach ($users as $usr) {
        if (!isset($answered[(int)$usr['id']]) && !empty($usr['line_user_id'])) $lineIds[] = $usr['line_user_id'];
    }

    $isSurvey = (($ev['type'] ?? 'event') === 'survey');
    $close = date('n/j H:i', strtotime($ev['close_at']));
    $text = ($isSurvey ? "📋 アンケートの締切が近づいています" : "📅 出欠回答の締切が近づいています") . "\n\n"
          . "「{$ev['title']}」\n締切: {$close}\n\n"
          . "まだ回答がありません。こちらから回答をお願いします🙏\n{$site}/form_view.php?id=" . (int)$ev['id'];

    $sent = $lineIds ? remindMulticast($lineIds, $text) : 0;
    markSent($pdo, $ev['id'], 'close24h', $sent);
    $totalSent += $sent;
    echo "close24h event#{$ev['id']} 「{$ev['title']}」 未回答 " . count($lineIds) . "名 → 送信 {$sent}\n";
}

// ---------------------------------------------------------------
// 2) 当日リマインド: 朝7時台の実行時のみ、「参加」回答者へ
// ---------------------------------------------------------------
if ((int)date('G') === 7) {
    $stmt = $pdo->query("SELECT * FROM events
        WHERE is_archived = 0 AND (type = 'event' OR type IS NULL) AND DATE(event_date) = CURDATE()");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
        if (alreadySent($pdo, $ev['id'], 'dayof')) continue;
        $j = $pdo->prepare("SELECT u.line_user_id FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.event_id = ? AND a.status = 'join'");
        $j->execute([$ev['id']]);
        $lineIds = $j->fetchAll(PDO::FETCH_COLUMN);
        $when = date('H:i', strtotime($ev['event_date']));
        $text = "🔔 本日のイベントです\n\n「{$ev['title']}」\n開始: {$when}\n\n詳細: {$site}/form_view.php?id=" . (int)$ev['id'];
        $sent = $lineIds ? remindMulticast($lineIds, $text) : 0;
        markSent($pdo, $ev['id'], 'dayof', $sent);
        $totalSent += $sent;
        echo "dayof event#{$ev['id']} 「{$ev['title']}」 参加者 " . count($lineIds) . "名 → 送信 {$sent}\n";
    }
}

echo "OK: " . date('Y-m-d H:i') . " 送信合計 {$totalSent}\n";
