<?php
require_once 'config.php';
requireLogin();

$event_id = $_GET['id'] ?? 0;
$pdo = getDB();

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: dashboard.php");
    exit;
}

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? ''); // CSRF Check

    $status = $_POST['status'] ?? '';
    $comment = $_POST['comment'] ?? '';
    $response_data = $_POST['response_data'] ?? null; // JSON String handling custom answers
    
    if ($status) {
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, user_id, status, comment, response_data) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, comment = ?, response_data = ?");
        $stmt->execute([$event_id, $_SESSION['user_id'], $status, $comment, $response_data, $status, $comment, $response_data]);
        // Refresh to show updated data
        header("Location: event_view.php?id=" . $event_id);
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

// Parse Schema
$form_schema = [];
if (!empty($event['form_schema'])) {
    $decoded = json_decode($event['form_schema'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $form_schema = $decoded;
    }
}

// Parse My Responses
$my_answers = [];
if (!empty($my_attendance['response_data'])) {
    $decoded_ans = json_decode($my_attendance['response_data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ans)) {
        $my_answers = $decoded_ans;
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f0ebf8;
            font-family: 'Roboto', 'Noto Sans JP', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background: white;
            box-shadow: none;
            border-bottom: 1px solid #e0e0e0;
        }
        .view-container {
            max-width: 640px;
            margin: 40px auto;
            padding: 0 1rem;
        }
        
        /* Event Header Card */
        .header-card {
            background: white;
            border-radius: 8px;
            border-top: 10px solid rgb(103, 58, 183);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 24px;
        }
        .event-title {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 400;
        }
        .event-desc {
            font-size: 14px;
            color: #202124;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        .submitted-msg {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin-bottom: 24px;
            border-left: 5px solid #0f9d58;
        }

        /* Question Cards */
        .q-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin-bottom: 24px;
        }
        .q-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .req-asterisk {
            color: #d93025;
            margin-left: 4px;
        }

        /* Inputs */
        .q-text-input {
            width: 100%;
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 8px 0;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .q-text-input:focus {
            border-bottom: 2px solid rgb(103, 58, 183);
            background: #fafafa;
        }
        
        .option-label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #202124;
        }
        .option-label input {
            margin-right: 12px;
            accent-color: rgb(103, 58, 183);
            transform: scale(1.2);
        }

        .select-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .btn-submit {
            background-color: rgb(103, 58, 183);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            float: left;
        }
        .btn-submit:hover {
            background-color: rgb(85, 45, 160);
        }
        
        .btn-clear {
            float: right;
            color: rgb(103, 58, 183);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        /* Participant List Section */
        .participants-section {
            max-width: 640px;
            margin: 60px auto 20px;
            padding: 0 1rem;
        }
        .p-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .p-avatar {
            width: 40px;
            height: 40px;
            background: rgb(103, 58, 183);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .admin-details {
            font-size: 0.8rem;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo">WHABITAT</a>
        </div>
    </header>

    <div class="view-container">
        
        <form method="POST" action="" id="entryForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="response_data" id="response_data_input">

            <!-- Title Header -->
            <div class="header-card">
                <h1 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="event-desc"><?php echo htmlspecialchars($event['description']); ?></div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                    <strong>開催日時:</strong> <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?><br>
                    <span style="color: #d93025;">* 必須</span>
                </div>
            </div>

            <?php if ($my_attendance): ?>
                <div class="submitted-msg">
                    <h3 style="margin-bottom: 10px;">回答済みです</h3>
                    <p style="font-size: 14px;">あなたの回答: <strong><?php echo getStatusLabel($my_attendance['status']); ?></strong></p>
                    <p style="font-size: 14px; color: #666; margin-top: 5px;">内容を修正する場合は、下記フォームを編集して再度送信してください。</p>
                </div>
            <?php endif; ?>

            <!-- Basic Attendance Status (Always required) -->
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
                        
                        <?php if ($q['type'] === 'paragraph'): ?>
                            <input type="text" class="q-text-input custom-input" name="ans_<?php echo $index; ?>" value="<?php echo htmlspecialchars($prev_val); ?>" placeholder="回答を入力" <?php echo $requiredAttr; ?>>
                            
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
            <?php else: ?>
                <!-- Fallback Comment field if no schema (or maybe always show comment?) -->
                <div class="q-card">
                    <div class="q-title">一言コメント</div>
                    <input type="text" name="comment" class="q-text-input" value="<?php echo htmlspecialchars($my_attendance['comment'] ?? ''); ?>" placeholder="回答を入力">
                </div>
            <?php endif; ?>

            <div style="overflow: hidden; padding-bottom: 20px;">
                <button type="button" onclick="submitForm()" class="btn-submit">送信</button>
                <button type="button" onclick="document.getElementById('entryForm').reset()" class="btn-clear">フォームをクリア</button>
            </div>

        </form>

        <div style="text-align: right; margin-top: 10px;">
            <a href="event_responses.php?id=<?php echo $event_id; ?>" style="color: #1967d2; text-decoration: none; font-size: 14px;">
                <i class="fas fa-list"></i> みんなの回答を見る
            </a>
        </div>
    </div>

    <script>
        function submitForm() {
            // Collect Custom Answers
            const answers = {};
            
            // Loop through our known indices if schema exists
            const customCards = document.querySelectorAll('.custom-q');
            customCards.forEach(card => {
                const index = card.dataset.index;
                const type = card.dataset.type;
                let val = null;

                if (type === 'paragraph') {
                    val = card.querySelector('input').value;
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
            document.getElementById('entryForm').submit();
        }
    </script>

</body>
</html>
