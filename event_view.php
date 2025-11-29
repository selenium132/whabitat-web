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

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    if ($status) {
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, user_id, status, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, comment = ?");
        $stmt->execute([$event_id, $_SESSION['user_id'], $status, $comment, $status, $comment]);
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
// Note: We fetch more details, but will filter display based on role
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

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 100px auto 60px;
            padding: 2rem;
        }
        .event-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .event-date {
            color: #666;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .event-title {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .event-desc {
            line-height: 1.6;
            color: #444;
        }
        
        .attendance-section {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #eee;
        }
        .submitted-msg {
            background-color: #e6f7ff;
            border: 1px solid #91d5ff;
            color: #0050b3;
            padding: 0.8rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .participants-section h2 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        .participant-list {
            list-style: none;
            padding: 0;
        }
        .participant-item {
            background: white;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column; /* Changed to column for details */
            gap: 0.5rem;
        }
        .participant-main {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .participant-name {
            font-weight: bold;
        }
        .participant-comment {
            color: #666;
            font-size: 0.9rem;
        }
        .participant-details {
            font-size: 0.85rem;
            color: #555;
            background: #f5f5f5;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        .btn-submit {
            background-color: #333;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo">WHABITAT</a>
        </div>
    </header>

    <main>
        <div class="container">
            <a href="dashboard.php">&lt; ダッシュボードに戻る</a>
            
            <div class="event-header">
                <div class="event-date"><?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?></div>
                <h1 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="event-desc"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
            </div>

            <div class="attendance-section">
                <h3>出欠回答</h3>
                
                <?php if ($my_attendance): ?>
                    <div class="submitted-msg">
                        提出済みです（現在の回答: <?php echo getStatusLabel($my_attendance['status']); ?>）<br>
                        <small>内容を変更する場合は、以下のフォームから再送信してください。</small>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="status" value="join" <?php echo ($my_attendance['status'] ?? '') === 'join' ? 'checked' : ''; ?> required> 参加
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="status" value="decline" <?php echo ($my_attendance['status'] ?? '') === 'decline' ? 'checked' : ''; ?>> 不参加
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="status" value="maybe" <?php echo ($my_attendance['status'] ?? '') === 'maybe' ? 'checked' : ''; ?>> 未定
                        </label>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <input type="text" name="comment" placeholder="一言コメント（任意）" value="<?php echo htmlspecialchars($my_attendance['comment'] ?? ''); ?>" style="width: 100%; padding: 0.5rem;">
                    </div>
                    <button type="submit" class="btn-submit">
                        <?php echo $my_attendance ? '回答を更新する' : '回答を送信'; ?>
                    </button>
                </form>
            </div>

            <div class="participants-section">
                <h2>参加予定メンバー (<?php echo count($participants); ?>名)</h2>
                <?php if (empty($participants)): ?>
                    <p>まだ参加予定者はいません。</p>
                <?php else: ?>
                    <ul class="participant-list">
                        <?php foreach ($participants as $p): ?>
                            <li class="participant-item">
                                <div class="participant-main">
                                    <span class="participant-name">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </span>
                                    <?php if ($p['comment']): ?>
                                        <span class="participant-comment"><?php echo htmlspecialchars($p['comment']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($is_admin): ?>
                                    <div class="participant-details">
                                        [管理者表示] 
                                        学籍番号: <?php echo htmlspecialchars($p['student_id']); ?> | 
                                        LINE名: <?php echo htmlspecialchars($p['line_name']); ?> | 
                                        代: <?php echo htmlspecialchars($p['grade']); ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
