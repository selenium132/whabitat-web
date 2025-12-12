<?php
require_once 'config.php';

// 1. Get Request Body
$input = file_get_contents('php://input');

// 2. Validate Signature
// Header is X-Line-Signature
$headers = getallheaders();
$signature = $headers['X-Line-Signature'] ?? null;

if (empty($signature) || empty($input)) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Error: Empty signature or input\n", FILE_APPEND);
    http_response_code(400);
    exit;
}

// HMAC-SHA256 signature validation
$hash = base64_encode(hash_hmac('sha256', $input, LINE_CHANNEL_SECRET, true));
if (!hash_equals($hash, $signature)) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Error: Signature mismatch. Config Secret: " . substr(LINE_CHANNEL_SECRET, 0, 5) . "...\n", FILE_APPEND);
    http_response_code(400);
    exit;
}

file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Success: Signature valid. Processing events...\n", FILE_APPEND);

// 3. Parse Event
$events = json_decode($input, true)['events'] ?? [];

foreach ($events as $event) {
    // Only handle message events
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Info: Ignored event type: " . $event['type'] . "\n", FILE_APPEND);
        continue;
    }

    $replyToken = $event['replyToken'];
    $userMessage = $event['message']['text'];
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Received message: " . $userMessage . "\n", FILE_APPEND);

    // 4. Logic: Check if user asks about events
    // Triggers: "イベント", "予定", "活動", "event"
    if (preg_match('/(イベント|予定|活動|event)/ui', $userMessage)) {
        
        $pdo = getDB();
        // Fetch Upcoming Events (Next 5)
        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
        $stmt->execute();
        $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $replyText = "";

        if (empty($upcoming_events)) {
            $replyText = "現在予定されているイベントはありません。";
        } else {
            $replyText = "📅 これからのイベント情報 📅\n\n";
            foreach ($upcoming_events as $ev) {
                $date = date('m/d H:i', strtotime($ev['event_date']));
                $replyText .= "🔹 {$date} ～\n   {$ev['title']}\n";
                // Add link to view if needed, e.g. "https://whabitathome.com/v2/event_view.php?id=" . $ev['id']
                $replyText .= "   👇 詳細・回答:\n   https://whabitathome.com/v2/event_view.php?id={$ev['id']}\n\n";
            }
            $replyText .= "サイトで確認: https://whabitathome.com/v2/dashboard.php";
        }

        // 5. Send Reply
        replyToLine($replyToken, $replyText);
    }
}

function replyToLine($replyToken, $text) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    
    $data = [
        'replyToken' => $replyToken,
        'messages' => [
            [
                'type' => 'text',
                'text' => $text
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_BOT_ACCESS_TOKEN
    ]);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    // Log result if needed
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Reply sent to $replyToken: " . $result . "\n", FILE_APPEND);
}
?>
