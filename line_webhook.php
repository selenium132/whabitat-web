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
$hash = base64_encode(hash_hmac('sha256', $input, LINE_BOT_CHANNEL_SECRET, true));
if (!hash_equals($hash, $signature)) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Error: Signature mismatch. Config Secret: " . substr(LINE_BOT_CHANNEL_SECRET, 0, 5) . "...\n", FILE_APPEND);
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
        
        // Identify the LINE user
        $line_user_id = $event['source']['userId'] ?? null;
        $db_user_id = null;
        $db_user_role = 'member';
        if ($line_user_id) {
            $user_stmt = $pdo->prepare("SELECT id, role FROM users WHERE line_user_id = ?");
            $user_stmt->execute([$line_user_id]);
            $db_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            if ($db_user) {
                $db_user_id = $db_user['id'];
                $db_user_role = $db_user['role'] ?? 'member';
            }
        }
        
        // Fetch user's attendance responses
        $user_attended = [];
        if ($db_user_id) {
            $att_stmt = $pdo->prepare("SELECT event_id, status FROM attendance WHERE user_id = ?");
            $att_stmt->execute([$db_user_id]);
            foreach ($att_stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $user_attended[$a['event_id']] = $a['status'];
            }
        }
        
        // Fetch Upcoming Events (exclude archived, exclude surveys)
        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_date >= CURDATE() AND (is_archived = 0 OR is_archived IS NULL) AND (type = 'event' OR type IS NULL) ORDER BY event_date ASC LIMIT 10");
        $stmt->execute();
        $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch Surveys targeted to this user
        $user_surveys = [];
        if ($db_user_id) {
            $survey_stmt = $pdo->prepare("SELECT * FROM events WHERE (is_archived = 0 OR is_archived IS NULL) AND type = 'survey' AND (close_at IS NULL OR close_at >= NOW()) ORDER BY event_date ASC");
            $survey_stmt->execute();
            $all_surveys = $survey_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_surveys as $sv) {
                $target_users = json_decode($sv['target_users'] ?? '[]', true);
                $is_target = empty($target_users) || in_array($db_user_id, $target_users);
                
                // Also check if user is admin, creator, or event_admin
                if (!$is_target) {
                    if ($db_user_role === 'admin' || $sv['created_by'] == $db_user_id) {
                        $is_target = true;
                    } else {
                        try {
                            $ea_stmt = $pdo->prepare("SELECT 1 FROM event_admins WHERE event_id = ? AND user_id = ?");
                            $ea_stmt->execute([$sv['id'], $db_user_id]);
                            if ($ea_stmt->fetch()) $is_target = true;
                        } catch (Exception $e) {}
                    }
                }
                
                if ($is_target) {
                    $user_surveys[] = $sv;
                }
            }
        }

        $replyText = "";

        // Events section
        if (empty($upcoming_events) && empty($user_surveys)) {
            $replyText = "現在予定されているイベントやアンケートはありません。";
        } else {
            if (!empty($upcoming_events)) {
                $replyText .= "📅 これからのイベント 📅\n\n";
                foreach ($upcoming_events as $ev) {
                    $date = date('m/d H:i', strtotime($ev['event_date']));
                    $status = "";
                    if ($db_user_id && isset($user_attended[$ev['id']])) {
                        $s = $user_attended[$ev['id']];
                        if ($s === 'join') $status = " ✅参加";
                        elseif ($s === 'maybe') $status = " 🤔未定";
                        elseif ($s === 'decline') $status = " ❌不参加";
                    } else {
                        $status = " ⬜未回答";
                    }
                    $replyText .= "🔹 {$date} ～{$status}\n   {$ev['title']}\n   👉 https://whabitathome.com/form_view.php?id={$ev['id']}\n\n";
                }
            }
            
            // Surveys section
            if (!empty($user_surveys)) {
                $replyText .= "📋 あなた宛のアンケート 📋\n\n";
                foreach ($user_surveys as $sv) {
                    $answered = isset($user_attended[$sv['id']]) ? " ✅回答済" : " ⬜未回答";
                    $replyText .= "🔸 {$sv['title']}{$answered}\n   👉 https://whabitathome.com/form_view.php?id={$sv['id']}\n\n";
                }
            }
            
            $replyText .= "サイトで確認: https://whabitathome.com/dashboard.php";
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
