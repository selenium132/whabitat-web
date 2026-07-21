<?php
require_once 'config.php';
require_once 'sheet_sync.php'; // 回答時の自動スプシ同期
requireLogin();

$pdo = getDB();

// Check if profile is complete - redirect if any required field is missing
$stmt = $pdo->prepare("SELECT name, student_id, grade, faculty, gender FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($user_profile['name']) || empty($user_profile['student_id']) || empty($user_profile['grade']) || empty($user_profile['faculty']) || empty($user_profile['gender'])) {
    // Pass return URL so user comes back after profile completion
    $return_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: register_profile.php?return=" . $return_url);
    exit;
}

$event_id = $_GET['id'] ?? 0;

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: dashboard.php");
    exit;
}

// Parse Schema（POSTの必須項目サーバ側検証で参照するため、送信処理より前に用意する）
$form_schema = [];
if (!empty($event['form_schema'])) {
    $decoded = json_decode($event['form_schema'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $form_schema = $decoded;
    }
}

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? ''); // CSRF Check

    // Server-side deadline check
    $now = new DateTime();
    $can_submit = true;
    
    // Check open_at
    if (!empty($event['open_at'])) {
        $open_time = new DateTime($event['open_at']);
        if ($now < $open_time) {
            $can_submit = false;
        }
    }
    
    // Check close_at
    if (!empty($event['close_at'])) {
        $close_time = new DateTime($event['close_at']);
        if ($now > $close_time) {
            $can_submit = false;
        }
    }
    
    // Check capacity (only for new 'join' submissions)
    $status = $_POST['status'] ?? '';
    // For surveys, auto-set status to 'join' (meaning 'submitted')
    if (($event['type'] ?? 'event') === 'survey') {
        $status = 'join';
    }
    if (!empty($event['capacity']) && $event['capacity'] > 0 && $status === 'join') {
        // Count current 'join' participants
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id = ? AND status = 'join' AND user_id != ?");
        $count_stmt->execute([$event_id, $_SESSION['user_id']]);
        $current_joins = $count_stmt->fetchColumn();
        
        if ($current_joins >= $event['capacity']) {
            // Check if user already has 'join' status (editing is allowed)
            $my_stmt = $pdo->prepare("SELECT status FROM attendance WHERE event_id = ? AND user_id = ?");
            $my_stmt->execute([$event_id, $_SESSION['user_id']]);
            $my_current = $my_stmt->fetchColumn();
            
            if ($my_current !== 'join') {
                $can_submit = false; // Cannot join, capacity full
            }
        }
    }
    
    if (!$can_submit) {
        // Redirect back without saving
        header("Location: form_view.php?id=" . $event_id);
        exit;
    }

    // Handle Delete Response
    if (($_POST['action'] ?? '') === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        // 既にスプシ連携済みのイベントなら、取り消しを自動反映（失敗しても削除は妨げない）
        syncEventToSheetSafe($pdo, $event_id);
        header("Location: form_view.php?id=" . $event_id);
        exit;
    }

    $comment = $_POST['comment'] ?? '';
    $response_data = $_POST['response_data'] ?? null; // JSON String handling custom answers
    
    // Server-side validation for required questions
    $validation_error = false;
    if ($status && !empty($form_schema) && $response_data) {
        $answers = json_decode($response_data, true);
        foreach ($form_schema as $idx => $q) {
            if (!empty($q['required'])) {
                $val = $answers[$idx] ?? null;
                // Check if the required field is empty
                if ($val === null || $val === '' || (is_array($val) && count($val) === 0)) {
                    $validation_error = true;
                    break;
                }
            }
        }
    }
    
    if ($validation_error) {
        // Redirect back with error
        header("Location: form_view.php?id=" . $event_id . "&error=required");
        exit;
    }
    
    if ($status) {
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, user_id, status, comment, response_data) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, comment = ?, response_data = ?");
        $stmt->execute([$event_id, $_SESSION['user_id'], $status, $comment, $response_data, $status, $comment, $response_data]);
        // アンケートは「回答送信時」に対象者へ追加する（旧: 閲覧時のGET書き込みは
        // CSRF・ID列挙の温床だったため廃止）。ここはCSRF検証済みPOST経路。
        if (($event['type'] ?? 'event') === 'survey') {
            try {
                $targets = json_decode($event['target_users'] ?? '[]', true);
                if (!is_array($targets)) $targets = [];
                if (!in_array($_SESSION['user_id'], $targets)) {
                    $targets[] = (int)$_SESSION['user_id'];
                    $upd = $pdo->prepare("UPDATE events SET target_users = ? WHERE id = ?");
                    $upd->execute([json_encode($targets), $event_id]);
                }
            } catch (Exception $e) {}
        }
        // 既にスプシ連携済みのイベントなら、回答を自動反映（失敗しても回答保存は妨げない）
        syncEventToSheetSafe($pdo, $event_id);
        // Refresh to show updated data
        header("Location: form_view.php?id=" . $event_id);
        exit;
    }
}

// Fetch My Attendance
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE event_id = ? AND user_id = ?");
$stmt->execute([$event_id, $_SESSION['user_id']]);
$my_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch All Participants (Only those who joined)
$stmt = $pdo->prepare("
    SELECT u.name, u.student_id, u.line_name, u.grade, a.status, a.comment 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.event_id = ? AND a.status = 'join'
    ORDER BY a.updated_at DESC
");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusLabel($status) {
    if ($status === 'join') return '参加';
    if ($status === 'decline') return '不参加';
    if ($status === 'maybe') return '未定';
    return '';
}

$is_admin = ($_SESSION['role'] === 'admin');
$csrf_token = generateCsrfToken();

// Parse My Responses
$my_answers = [];
if (!empty($my_attendance['response_data'])) {
    $decoded_ans = json_decode($my_attendance['response_data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ans)) {
        $my_answers = $decoded_ans;
    }
}

// Check Response Schedule
$now = new DateTime();
$is_open = true;
$schedule_message = '';

// Check open_at (if set, must be past this time to respond)
if (!empty($event['open_at'])) {
    $open_time = new DateTime($event['open_at']);
    if ($now < $open_time) {
        $is_open = false;
        $schedule_message = '回答受付開始: ' . $open_time->format('Y年m月d日 H:i');
    }
}

// Check close_at (if set, must be before this time to respond)
$is_closed = false;
if (!empty($event['close_at'])) {
    $close_time = new DateTime($event['close_at']);
    if ($now > $close_time) {
        $is_open = false;
        $is_closed = true;
        $schedule_message = '回答受付は終了しました（締切: ' . $close_time->format('Y年m月d日 H:i') . '）';
    }
}

// Check capacity (if set, count participants and compare)
$current_participants = count($participants); // Already fetched above - only 'join' status
$is_full = false;
if (!empty($event['capacity']) && $event['capacity'] > 0) {
    if ($current_participants >= $event['capacity']) {
        // Check if current user already joined (they can still edit)
        $already_joined = ($my_attendance && $my_attendance['status'] === 'join');
        if (!$already_joined) {
            $is_full = true;
            $is_open = false;
            $schedule_message = '定員に達しました（' . $current_participants . '/' . $event['capacity'] . '名）';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-color);
            font-family: 'Noto Sans JP', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background: white;
            box-shadow: none;
            border-bottom: 1px solid #e0e0e0;
        }
        .view-container {
            max-width: 640px;
            margin: 100px auto 40px; /* Adjusted for fixed header */
            padding: 0 1rem;
        }
        
        /* Event Header Card */
        .header-card {
            background: white;
            border-radius: 16px;
            /* Removed purple top border */
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            text-align: center;
        }
        .event-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .event-desc {
            font-size: 1rem;
            color: var(--text-color);
            line-height: 1.8;
            white-space: pre-wrap;
            margin-bottom: 1.5rem;
        }
        
        .submitted-msg {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            border-left: 5px solid var(--accent-green);
        }

        /* Question Cards */
        .q-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        .q-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        .req-asterisk {
            color: var(--accent-red);
            margin-left: 4px;
        }

        /* Inputs */
        .q-text-input {
            width: 100%;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px;
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
            background: #f9f9f9;
        }
        .q-text-input:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .option-label {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-color);
            padding: 8px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .option-label:hover {
            background-color: #f5f5f5;
        }
        .option-label input {
            margin-right: 12px;
            accent-color: var(--accent-blue); /* Site accent color */
            transform: scale(1.1);
        }

        .select-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 8px;
            font-size: 1rem;
            background: #f9f9f9;
        }
        .select-input:focus {
            border-color: var(--accent-blue);
            outline: none;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            float: left;
            box-shadow: var(--shadow-md);
            transition: 0.3s;
        }
        .btn-submit:hover {
            background-color: #1a252f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-clear {
            float: right;
            color: var(--text-light);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 12px;
        }
        .btn-clear:hover {
            color: var(--accent-red);
        }

    </style>
    <link rel="stylesheet" href="member.css?v=<?php echo @filemtime(__DIR__ . '/member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
            </a>
            <button type="button" onclick="copyCurrentUrl()" style="background: #495057; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">
                <i class="fas fa-link"></i> URLをコピー
            </button>
        </div>
    </header>

    <div class="view-container">


        <?php if (($_GET['error'] ?? '') === 'required'): ?>
        <div style="background: #fdecea; border: 1px solid #f5c6cb; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; color: #a94442;">
            <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i>
            必須項目が未入力です。入力内容をご確認のうえ、再度送信してください。
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="entryForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="response_data" id="response_data_input">

            <!-- Title Header -->
            <div class="header-card">
                <?php if (($event['type'] ?? 'event') === 'survey'): ?>
                    <div style="display:inline-block;background:#7d6a8e;color:white;padding:4px 12px;border-radius:20px;font-size:0.8rem;margin-bottom:12px;"><i class="fas fa-list-check" aria-hidden="true"></i> アンケート</div>
                <?php else: ?>
                    <div style="display:inline-block;background:#51666e;color:white;padding:4px 12px;border-radius:20px;font-size:0.8rem;margin-bottom:12px;"><i class="fas fa-calendar-check" aria-hidden="true"></i> 出欠確認</div>
                <?php endif; ?>
                <h1 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="event-desc"><?php echo htmlspecialchars($event['description']); ?></div>
                <?php if (($event['type'] ?? 'event') !== 'survey'): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                    <strong>開催日時:</strong> <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?><br>
                    <?php if (!$is_open): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 8px; color: #856404;">
                            <i class="fas fa-clock"></i> <?php echo htmlspecialchars($schedule_message); ?>
                        </div>
                    <?php else: ?>
                        <span style="color: var(--accent-red);">* 必須</span>
                        <?php if (!empty($event['close_at'])): ?>
                            <div style="margin-top: 8px; color: #888; font-size: 0.85rem;">
                                <i class="fas fa-hourglass-end"></i> 締切: <?php echo date('Y年m月d日 H:i', strtotime($event['close_at'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($event['capacity'])): ?>
                            <div style="margin-top: 8px; color: #888; font-size: 0.85rem;">
                                <i class="fas fa-users"></i> 定員: <?php echo $current_participants; ?>/<?php echo $event['capacity']; ?>名
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php else: /* survey header info */ ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                    <?php if (!$is_open): ?>
                        <div style="margin-top: 0; padding: 10px; background: #fff3cd; border-radius: 8px; color: #856404;">
                            <i class="fas fa-clock"></i> このアンケートは回答期間外です。
                        </div>
                    <?php else: ?>
                        <span style="color: var(--accent-red);">* 必須</span>
                        <?php if (!empty($event['close_at'])): ?>
                            <div style="margin-top: 8px; color: #888; font-size: 0.85rem;">
                                <i class="fas fa-hourglass-end"></i> 締切: <?php echo date('Y年m月d日 H:i', strtotime($event['close_at'])); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_open): ?>
                <!-- Response period is closed - show submitted response -->
                <?php if ($my_attendance): ?>
                    <div class="question-card" style="padding: 20px;">
                        <h3 style="margin-bottom: 15px; color: #333;">あなたの回答</h3>
                        <?php if (($event['type'] ?? 'event') !== 'survey'): ?>
                        <div style="background: #f5f5f5; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px;">
                            <strong>出欠:</strong> <?php echo getStatusLabel($my_attendance['status']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($form_schema) && !empty($my_answers)): ?>
                            <?php foreach ($form_schema as $index => $q): ?>
                                <?php if (isset($my_answers[$index])): ?>
                                    <div style="background: #f5f5f5; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px;">
                                        <strong><?php echo htmlspecialchars($q['title']); ?>:</strong> 
                                        <?php 
                                            $val = $my_answers[$index];
                                            if (is_array($val)) {
                                                echo htmlspecialchars(implode(', ', $val));
                                            } else {
                                                echo htmlspecialchars($val);
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="question-card" style="text-align: center; padding: 20px;">
                        <p style="color: #666;">回答が登録されていません。</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($is_closed): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center;">
                    <p style="color: #856404; margin: 0; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                        <?php if ($my_attendance): ?>
                            回答の変更が必要な場合は、幹部までご連絡ください。
                        <?php else: ?>
                            これ以降の参加希望の相談は幹部まで！
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="dashboard.php" class="btn-primary" style="display: inline-block;">ダッシュボードへ戻る</a>
                </div>
            <?php else: ?>
            
            <?php if ($my_attendance): ?>
                <div class="submitted-msg">
                    <h3 style="margin-bottom: 10px;">回答済みです</h3>
                    <p style="font-size: 14px;">
                        <?php if (($event['type'] ?? 'event') === 'survey'): ?>
                            ステータス: <strong>回答済</strong>
                        <?php else: ?>
                            あなたの回答: <strong><?php echo getStatusLabel($my_attendance['status']); ?></strong>
                        <?php endif; ?>
                    </p>
                    <p style="font-size: 14px; color: #666; margin-top: 5px;">内容を修正する場合は、下記フォームを編集して再度送信してください。</p>
                </div>
            <?php endif; ?>

            <!-- Basic Attendance Status (Always required) -->
            <?php if (($event['type'] ?? 'event') !== 'survey'): ?>
            <div class="q-card">
                <div class="q-title">出欠確認 <span class="req-asterisk">*</span></div>
                <div class="q-options">
                    <label class="option-label">
                        <input type="radio" name="status" value="join" required <?php echo ($my_attendance['status'] ?? '') === 'join' ? 'checked' : ''; ?>>
                        参加
                    </label>
                    <label class="option-label">
                        <input type="radio" name="status" value="decline" <?php echo ($my_attendance['status'] ?? '') === 'decline' ? 'checked' : ''; ?>>
                        不参加
                    </label>
                    <label class="option-label">
                        <input type="radio" name="status" value="maybe" <?php echo ($my_attendance['status'] ?? '') === 'maybe' ? 'checked' : ''; ?>>
                        未定
                    </label>
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" name="status" value="join">
            <?php endif; ?>

            <!-- Dynamic Custom Questions -->
            <?php if (!empty($form_schema)): ?>
                <?php foreach ($form_schema as $index => $q): ?>
                    <?php 
                        $qid = $index; 
                        $title = htmlspecialchars($q['title']);
                        $req = $q['required'] ? '<span class="req-asterisk">*</span>' : '';
                        $requiredAttr = $q['required'] ? 'required' : '';
                        // Retrieve previous answer if exists (saved as array index usually, or we can use keys if we had IDs)
                        // Here we rely on order index since we saved as array.
                        $prev_val = $my_answers[$index] ?? ''; 
                    ?>
                    
                    <div class="q-card custom-q" data-index="<?php echo $index; ?>" data-type="<?php echo $q['type']; ?>">
                        <div class="q-title"><?php echo $title . $req; ?></div>
                        <?php if (!empty($q['description'])): ?>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($q['description']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($q['type'] === 'paragraph'): ?>
                            <textarea class="q-text-input custom-input" name="ans_<?php echo $index; ?>" placeholder="回答を入力" rows="3" style="resize:vertical;" <?php echo $requiredAttr; ?>><?php echo htmlspecialchars($prev_val); ?></textarea>
                            
                        <?php elseif ($q['type'] === 'radio'): ?>
                            <?php foreach ($q['options'] as $opt): ?>
                                <label class="option-label">
                                    <input type="radio" name="ans_<?php echo $index; ?>" value="<?php echo htmlspecialchars($opt); ?>" class="custom-input" <?php echo ($prev_val === $opt) ? 'checked' : ''; ?> <?php echo $requiredAttr; ?>>
                                    <?php echo htmlspecialchars($opt); ?>
                                </label>
                            <?php endforeach; ?>

                        <?php elseif ($q['type'] === 'checkbox'): ?>
                            <?php 
                                $prev_checks = is_array($prev_val) ? $prev_val : [];
                            ?>
                            <?php foreach ($q['options'] as $opt): ?>
                                <label class="option-label">
                                    <input type="checkbox" name="ans_<?php echo $index; ?>[]" value="<?php echo htmlspecialchars($opt); ?>" class="custom-input" <?php echo in_array($opt, $prev_checks) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($opt); ?>
                                </label>
                            <?php endforeach; ?>

                        <?php elseif ($q['type'] === 'dropdown'): ?>
                            <select class="select-input custom-input" name="ans_<?php echo $index; ?>" <?php echo $requiredAttr; ?>>
                                <option value="">選択してください</option>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($prev_val === $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="overflow: hidden; padding-bottom: 20px;">
                <button type="button" onclick="submitForm()" class="btn-submit">
                    <?php echo $my_attendance ? '更新' : '送信'; ?>
                </button>
                <?php if ($my_attendance): ?>
                    <button type="button" onclick="deleteResponse()" class="btn-clear" style="color: var(--accent-red); margin-left: 10px;">回答を削除</button>
                <?php endif; ?>
                <button type="button" onclick="clearForm()" class="btn-clear">フォームをクリア</button>
            </div>

            <?php endif; // End of is_open check ?>

        </form>

        <?php 
            $is_survey_view = (($event['type'] ?? 'event') === 'survey');
            $show_responses_link = $is_survey_view || isEventAdmin($event_id) || $event['created_by'] == $_SESSION['user_id'];
        ?>
        <?php if ($show_responses_link): ?>
        <div style="text-align: right; margin-top: 10px;">
            <a href="form_responses.php?id=<?php echo $event_id; ?>" style="color: var(--primary-color); text-decoration: underline; text-underline-offset: .2em; font-size: 14px;">
                <i class="fas fa-list"></i> みんなの回答を見る
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Form schema for required validation
        const formSchema = <?php echo json_encode($form_schema, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        function submitForm() {
            // Validate required custom questions
            const customCards = document.querySelectorAll('.custom-q');
            for (let i = 0; i < customCards.length; i++) {
                const card = customCards[i];
                const index = parseInt(card.dataset.index);
                const type = card.dataset.type;
                
                // Check if this question is required
                if (formSchema[index] && formSchema[index].required) {
                    let val = null;
                    let isEmpty = false;
                    
                    if (type === 'paragraph') {
                        val = card.querySelector('textarea')?.value?.trim() || '';
                        isEmpty = val === '';
                    } else if (type === 'radio') {
                        const checked = card.querySelector('input:checked');
                        isEmpty = !checked;
                    } else if (type === 'checkbox') {
                        const checked = card.querySelectorAll('input:checked');
                        isEmpty = checked.length === 0;
                    } else if (type === 'dropdown') {
                        val = card.querySelector('select')?.value || '';
                        isEmpty = val === '';
                    }
                    
                    if (isEmpty) {
                        const qTitle = formSchema[index].title || ('質問' + (index + 1));
                        alert('「' + qTitle + '」は必須項目です。');
                        return;
                    }
                }
            }
            
            // Collect Custom Answers
            const answers = {};
            
            // Loop through our known indices if schema exists
            customCards.forEach(card => {
                const index = card.dataset.index;
                const type = card.dataset.type;
                let val = null;

                if (type === 'paragraph') {
                    val = card.querySelector('textarea').value;
                } else if (type === 'radio') {
                    const checked = card.querySelector('input:checked');
                    val = checked ? checked.value : '';
                } else if (type === 'checkbox') {
                    const checked = card.querySelectorAll('input:checked');
                    val = Array.from(checked).map(c => c.value);
                } else if (type === 'dropdown') {
                    val = card.querySelector('select').value;
                }
                
                answers[index] = val;
            });

            // Put JSON into hidden input
            if (Object.keys(answers).length > 0) {
                document.getElementById('response_data_input').value = JSON.stringify(answers);
            }

            // Submit
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '.6'; }
            document.getElementById('entryForm').submit();
        }

        function clearForm() {
            if(!confirm('入力内容をすべて消去しますか？')) return;

            // Clear Status Radio
            const statusRadios = document.querySelectorAll('input[name="status"]');
            statusRadios.forEach(r => r.checked = false);

            // Clear Custom Questions
            const customInputs = document.querySelectorAll('.custom-input');
            customInputs.forEach(input => {
                if (input.tagName === 'TEXTAREA') {
                    input.value = '';
                } else if (input.type === 'text') {
                    input.value = '';
                } else if (input.type === 'radio' || input.type === 'checkbox') {
                    input.checked = false;
                } else if (input.tagName === 'SELECT') {
                    input.value = '';
                }
            });
            
            // Clear Comment (fallback)
            const comments = document.querySelectorAll('input[name="comment"]');
            comments.forEach(c => c.value = '');
        }

        function deleteResponse() {
            if(!confirm('本当に回答を削除しますか？\nこの操作は取り消せません。')) return;
            const form = document.getElementById('entryForm');
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            form.appendChild(actionInput);
            form.submit();
        }
        
        // Copy current URL to clipboard
        function copyCurrentUrl() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                alert('URLをコピーしました！');
            }).catch(function(err) {
                prompt('URLをコピーしてください:', window.location.href);
            });
        }
    </script>

</body>
</html>
