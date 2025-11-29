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
    validateCsrfToken($_POST['csrf_token'] ?? ''); // CSRF Check

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
$csrf_token = generateCsrfToken(); // Generate Token

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
        .event-header {
            margin-bottom: 2rem;
        }
        .event-date-badge {
            display: inline-block;
            background: var(--secondary-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .attendance-form {
            background: var(--bg-color);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        .radio-group {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-weight: 600;
        }
        .participant-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        .participant-card {
            background: white;
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 8px;
        }
        .admin-details {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px dashed #eee;
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
        <div class="dashboard-container">
            <a href="dashboard.php" style="display: inline-block; margin-bottom: 1rem; color: var(--text-light);">&lt; ダッシュボードに戻る</a>
            
            <div class="card">
                <div class="event-header">
                    <div class="event-date-badge">
                        <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?>
                    </div>
                    <h1 style="margin-bottom: 1rem;"><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($event['description']); ?></div>
                </div>

                <div class="attendance-form">
                    <h3 style="margin-bottom: 1rem;">出欠回答</h3>
                    
                    <?php if ($my_attendance): ?>
                        <div style="background: #e6f7ff; color: #0050b3; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
                            <strong>提出済みです</strong>（現在の回答: <?php echo getStatusLabel($my_attendance['status']); ?>）<br>
                            <small>変更する場合は再送信してください。</small>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
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
                        <div class="form-group">
                            <input type="text" name="comment" class="form-input" placeholder="一言コメント（任意）" value="<?php echo htmlspecialchars($my_attendance['comment'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            <?php echo $my_attendance ? '回答を更新する' : '回答を送信'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <h2 class="section-title" style="text-align: left; margin-bottom: 1.5rem;">参加予定メンバー (<?php echo count($participants); ?>名)</h2>
            
            <?php if (empty($participants)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">まだ参加予定者はいません。</div>
            <?php else: ?>
                <div class="participant-list">
                    <?php foreach ($participants as $p): ?>
                        <div class="participant-card">
                            <div style="font-weight: bold; margin-bottom: 0.3rem;">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </div>
                            <?php if ($p['comment']): ?>
                                <div style="font-size: 0.9rem; color: var(--text-light);">
                                    "<?php echo htmlspecialchars($p['comment']); ?>"
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_admin): ?>
                                <div class="admin-details">
                                    ID: <?php echo htmlspecialchars($p['student_id']); ?><br>
                                    LINE: <?php echo htmlspecialchars($p['line_name']); ?><br>
                                    Grade: <?php echo htmlspecialchars($p['grade']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
