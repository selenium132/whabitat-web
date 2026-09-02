<?php
/**
 * 出欠確認/アンケートの締切ダイジェスト（CLI専用・cron で1時間ごとに実行）
 *
 * LINE 公式アカウントの無料枠は月200通しかないため、会員への一斉 push は行わない。
 * 送るのは「イベントの主催者（作成者）1人に1通」だけ:
 *   1) 締切24時間前 : 未回答が何名いるかと回答一覧のリンク → 主催者が LINE グループで催促する（グループ投稿は無料）
 *   2) 当日の朝7時台 : 参加予定人数と一覧のリンク
 *
 * 二重送信防止: event_reminders に (event_id, kind) を記録し、同じ種別は1イベント1回だけ。
 * 安全弁: 当月の送信数が QUOTA_GUARD 通を超えていたら何も送らない（問い合わせ通知などの枠を残す）。
 *
 * XServer Cron 設定例（毎時5分）:
 *   5 * * * *  /usr/bin/php /home/＜サーバーID＞/whabitathome.com/public_html/scripts/remind_deadlines.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';

const QUOTA_GUARD = 150; // 当月これ以上使っていたらダイジェストは送らない（無料枠200通）

$pdo = getDB();
$pdo->exec("CREATE TABLE IF NOT EXISTS event_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    sent_count INT NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_kind (event_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 当月の LINE 送信数（取得できなければ安全側に倒して送らない）
function lineMonthlyUsage() {
    if (!defined('LINE_BOT_ACCESS_TOKEN') || LINE_BOT_ACCESS_TOKEN === '') return null;
    $ch = curl_init('https://api.line.me/v2/bot/message/quota/consumption');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . LINE_BOT_ACCESS_TOKEN],
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode((string)$resp, true);
    return ($http === 200 && isset($d['totalUsage'])) ? (int)$d['totalUsage'] : null;
}

$usage = lineMonthlyUsage();
if ($usage === null) {
    echo "LINE の送信数を取得できなかったため今回は送信しません（トークン/ネットワークを確認）\n";
    exit(0);
}
if ($usage >= QUOTA_GUARD) {
    echo "当月の LINE 送信数 {$usage} 通が上限目安 " . QUOTA_GUARD . " 通に達しているためダイジェストを停止中\n";
    exit(0);
}
echo "LINE 当月送信数: {$usage} 通\n";

$site = 'https://whabitathome.com';
$totalSent = 0;

function alreadySent(PDO $pdo, $eventId, $kind) {
    $s = $pdo->prepare("SELECT 1 FROM event_reminders WHERE event_id = ? AND kind = ?");
    $s->execute([$eventId, $kind]);
    return (bool)$s->fetchColumn();
}
function markSent(PDO $pdo, $eventId, $kind, $count) {
    $pdo->prepare("INSERT IGNORE INTO event_reminders (event_id, kind, sent_count) VALUES (?, ?, ?)")->execute([$eventId, $kind, $count]);
}
// 主催者(作成者)の line_user_id。作成者が LINE 未連携なら送り先なし
function organizerLineId(PDO $pdo, $event) {
    if (empty($event['created_by'])) return null;
    $s = $pdo->prepare("SELECT line_user_id FROM users WHERE id = ?");
    $s->execute([$event['created_by']]);
    $id = $s->fetchColumn();
    return $id ?: null;
}

// ---------------------------------------------------------------
// 1) 締切24時間前: 主催者へ「未回答◯名」ダイジェスト（1通）
// ---------------------------------------------------------------
$stmt = $pdo->query("SELECT * FROM events
    WHERE is_archived = 0 AND close_at IS NOT NULL
      AND close_at > NOW() AND close_at <= (NOW() + INTERVAL 24 HOUR)");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
    if (alreadySent($pdo, $ev['id'], 'close24h')) continue;

    // 対象者数: target_users 指定があればその人数、無ければ承認済み会員数
    $targets = [];
    if (!empty($ev['target_users'])) {
        $t = json_decode($ev['target_users'], true);
        if (is_array($t) && $t) $targets = array_map('intval', $t);
    }
    if ($targets) {
        $in = implode(',', array_fill(0, count($targets), '?'));
        $q = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_approved = 1 AND id IN ($in)");
        $q->execute($targets);
        $targetCount = (int)$q->fetchColumn();
    } else {
        $targetCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 1")->fetchColumn();
    }
    $a = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE event_id = ?");
    $a->execute([$ev['id']]);
    $answered = (int)$a->fetchColumn();
    $unanswered = max(0, $targetCount - $answered);

    $to = organizerLineId($pdo, $ev);
    $isSurvey = (($ev['type'] ?? 'event') === 'survey');
    $close = date('n/j H:i', strtotime($ev['close_at']));
    $text = ($isSurvey ? "📋 アンケートの締切まで24時間です" : "📅 出欠回答の締切まで24時間です") . "\n\n"
          . "「{$ev['title']}」\n締切: {$close}\n"
          . "回答済み {$answered} / 対象 {$targetCount} 名（未回答 {$unanswered} 名）\n\n"
          . "回答一覧: {$site}/form_responses.php?id=" . (int)$ev['id'] . "\n\n"
          . "催促する場合は LINE グループにこのリンクを貼ってください👇\n{$site}/form_view.php?id=" . (int)$ev['id'];

    $sent = ($to && linePushToUser($to, $text)) ? 1 : 0;
    markSent($pdo, $ev['id'], 'close24h', $sent);
    $totalSent += $sent;
    echo "close24h event#{$ev['id']} 「{$ev['title']}」 未回答 {$unanswered}名 → 主催者へ " . ($sent ? '送信' : '送信なし(LINE未連携)') . "\n";
}

// ---------------------------------------------------------------
// 2) 当日朝7時台: 主催者へ参加予定人数（1通）
// ---------------------------------------------------------------
if ((int)date('G') === 7) {
    $stmt = $pdo->query("SELECT * FROM events
        WHERE is_archived = 0 AND (type = 'event' OR type IS NULL) AND DATE(event_date) = CURDATE()");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
        if (alreadySent($pdo, $ev['id'], 'dayof')) continue;
        $j = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id = ? AND status = 'join'");
        $j->execute([$ev['id']]);
        $joins = (int)$j->fetchColumn();
        $to = organizerLineId($pdo, $ev);
        $when = date('H:i', strtotime($ev['event_date']));
        $text = "🔔 本日のイベントです\n\n「{$ev['title']}」\n開始: {$when}\n参加予定: {$joins} 名\n\n"
              . "参加者一覧: {$site}/form_responses.php?id=" . (int)$ev['id'];
        $sent = ($to && linePushToUser($to, $text)) ? 1 : 0;
        markSent($pdo, $ev['id'], 'dayof', $sent);
        $totalSent += $sent;
        echo "dayof event#{$ev['id']} 「{$ev['title']}」 参加 {$joins}名 → 主催者へ " . ($sent ? '送信' : '送信なし(LINE未連携)') . "\n";
    }
}

echo "OK: " . date('Y-m-d H:i') . " 送信合計 {$totalSent} 通\n";
