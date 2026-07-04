<?php
require_once 'config.php';
require_once 'room_common.php';

// 1. Get Request Body
$input = file_get_contents('php://input');

// 2. Validate Signature
// Header is X-Line-Signature
$headers = getallheaders();
$signature = $headers['X-Line-Signature'] ?? null;

if (empty($signature) || empty($input)) {
    error_log('line_webhook: Empty signature or input');
    http_response_code(400);
    exit;
}

// HMAC-SHA256 signature validation
$hash = base64_encode(hash_hmac('sha256', $input, LINE_BOT_CHANNEL_SECRET, true));
if (!hash_equals($hash, $signature)) {
    error_log('line_webhook: Signature mismatch');
    http_response_code(400);
    exit;
}

// 3. Parse Event
$events = json_decode($input, true)['events'] ?? [];

foreach ($events as $event) {
  try {
    // 部室の入退室リッチメニュー（postbackイベント）
    if ($event['type'] === 'postback') {
        handleRoomPostback($event);
        continue;
    }

    // Only handle message events
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        continue;
    }

    $replyToken = $event['replyToken'];
    $userMessage = $event['message']['text'];

    // 4. Logic: Check if user asks about events
    // Triggers: "イベント", "予定", "活動", "event"
    if (preg_match('/(イベント|予定|活動|event)/ui', $userMessage)) {
        $pdo = getDB();
        $line_user_id = $event['source']['userId'] ?? null;
        sendEventsAndSurveysReply($pdo, $replyToken, $line_user_id);
    }
  } catch (Exception $e) {
      // 1件のイベント処理が失敗しても他のイベントやHTTP 200応答を妨げない
      error_log('line_webhook event error: ' . $e->getMessage());
  }
}

// イベント/アンケート一覧を返信する（テキストコマンド「イベント」とリッチメニューの両方から呼ばれる）
function sendEventsAndSurveysReply(PDO $pdo, $replyToken, $lineUserId) {
    $db_user_id = null;
    $db_user_role = 'member';
    if ($lineUserId) {
        $user_stmt = $pdo->prepare("SELECT id, role FROM users WHERE line_user_id = ?");
        $user_stmt->execute([$lineUserId]);
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

    replyToLine($replyToken, $replyText);
}

// 部室の入退室・利用状況確認・イベント確認（リッチメニューのpostbackイベント）
function handleRoomPostback($event) {
    $replyToken = $event['replyToken'] ?? null;
    if (!$replyToken) return;

    parse_str($event['postback']['data'] ?? '', $data);
    $action = $data['action'] ?? '';
    if (!in_array($action, ['toggle', 'status', 'event'], true)) {
        return;
    }

    $lineUserId = $event['source']['userId'] ?? null;
    if (!$lineUserId) return;

    $pdo = getDB();

    // イベント確認は未登録者でも見られる（既存のテキストコマンド「イベント」と同じ挙動）
    if ($action === 'event') {
        sendEventsAndSurveysReply($pdo, $replyToken, $lineUserId);
        return;
    }

    ensureRoomTables($pdo);

    $stmt = $pdo->prepare("SELECT id, is_approved FROM users WHERE line_user_id = ?");
    $stmt->execute([$lineUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        replyToLine($replyToken, "LINEアカウントとWHABITATの会員情報が連携されていません。\nhttps://whabitathome.com でログインしてからお試しください。");
        return;
    }
    if (empty($user['is_approved'])) {
        replyToLine($replyToken, "会員承認をお待ちください。承認後に入退室機能がご利用いただけます。");
        return;
    }

    $userId = (int)$user['id'];

    switch ($action) {
        case 'toggle':
            // 「部室入退出」は1ボタン。今の在室状態を見て入室/退室を自動判定する。
            if (isUserPresent($pdo, $userId)) {
                $result = roomCheckOut($pdo, $userId, 'line');
                replyToLine($replyToken, $result['success'] ? "👋 退室しました。" : "⚠️ " . $result['error']);
            } else {
                $result = roomCheckIn($pdo, $userId, 'line');
                replyToLine($replyToken, $result['success'] ? "✅ 入室しました。" : "⚠️ " . $result['error']);
            }
            break;

        case 'status':
            replyToLine($replyToken, formatOccupantsForLineText(getCurrentOccupants($pdo), getActiveReservation($pdo)));
            break;
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
    if ($result === false) {
        error_log('line_webhook: reply request failed: ' . curl_error($ch));
    }
    curl_close($ch);
}
?>
