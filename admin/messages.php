<?php
require_once '../config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ一覧 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .message-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: var(--shadow-sm);
        }
        .message-meta {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }
        .message-body {
            white-space: pre-wrap;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo">WHABITAT Admin</a>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <a href="../dashboard.php" style="display: inline-block; margin-bottom: 1rem;">&lt; ダッシュボードに戻る</a>
            <h1>お問い合わせ一覧</h1>
            
            <?php if (empty($messages)): ?>
                <div class="card">メッセージはありません。</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card">
                        <div class="message-meta">
                            <span><strong><?php echo htmlspecialchars($msg['name']); ?></strong> (<?php echo htmlspecialchars($msg['email']); ?>)</span>
                            <span><?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?></span>
                        </div>
                        <div class="message-body"><?php echo htmlspecialchars($msg['message']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
