<?php
require_once '../config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();

// Handle Actions (Must be POST for state changes to prevent CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    // Handle Mark as Read
    if (isset($_POST['mark_read']) && is_numeric($_POST['mark_read'])) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$_POST['mark_read']]);
        header("Location: messages.php" . (isset($_POST['source']) ? "?source=" . $_POST['source'] : ""));
        exit;
    }

    // Handle Mark All as Read
    if (isset($_POST['mark_all_read'])) {
        $pdo->exec("UPDATE contact_messages SET is_read = 1");
        header("Location: messages.php");
        exit;
    }

    // Handle Delete
    if (isset($_POST['delete']) && is_numeric($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$_POST['delete']]);
        header("Location: messages.php" . (isset($_POST['source']) ? "?source=" . $_POST['source'] : ""));
        exit;
    }
}

// Filter by source
$source = $_GET['source'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT * FROM contact_messages";
$params = [];

if ($source === 'contact') {
    $sql .= " WHERE source = 'contact'";
} elseif ($source === 'suggestion') {
    $sql .= " WHERE source = 'suggestion'";
} elseif ($source === 'unread') {
    $sql .= " WHERE is_read = 0";
}

// Sort
if ($sort === 'oldest') {
    $sql .= " ORDER BY created_at ASC";
} else {
    $sql .= " ORDER BY created_at DESC";
}

$stmt = $pdo->query($sql);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread
$unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="apple-touch-icon" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ一覧 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo @filemtime(__DIR__ . '/../style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .message-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: var(--shadow-sm);
            position: relative;
        }
        .message-card.unread {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        .message-card.suggestion {
            border-left-color: #9c27b0;
        }
        .message-meta {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .message-body {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .source-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .source-badge.contact {
            background: #e3f2fd;
            color: #1976d2;
        }
        .source-badge.suggestion {
            background: #f3e5f5;
            color: #9c27b0;
        }
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            color: #333;
        }
        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .filter-btn:hover {
            background: #f5f5f5;
        }
        .filter-btn.active:hover {
            background: var(--primary-color);
        }
        .action-btns {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #eee;
        }
        .action-btn {
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 15px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }
        .btn-read {
            background: #28a745;
        }
        .btn-read:hover {
            background: #218838;
        }
        .btn-delete {
            background: #6c757d;
        }
        .btn-delete:hover {
            background: #dc3545;
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
    </style>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container">

            <h1>
                お問い合わせ一覧
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?php echo $unreadCount; ?> 件未読</span>
                <?php endif; ?>
            </h1>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <a href="?source=all" class="filter-btn <?php echo $source === 'all' ? 'active' : ''; ?>">すべて</a>
                <a href="?source=unread" class="filter-btn <?php echo $source === 'unread' ? 'active' : ''; ?>">未読のみ</a>
                <a href="?source=contact" class="filter-btn <?php echo $source === 'contact' ? 'active' : ''; ?>">📧 お問い合わせ</a>
                <a href="?source=suggestion" class="filter-btn <?php echo $source === 'suggestion' ? 'active' : ''; ?>">📮 目安箱</a>
                
                <span style="margin-left: auto;"></span>
                
                <a href="?sort=newest<?php echo $source !== 'all' ? '&source=' . $source : ''; ?>" class="filter-btn <?php echo $sort === 'newest' ? 'active' : ''; ?>">新しい順</a>
                <a href="?sort=oldest<?php echo $source !== 'all' ? '&source=' . $source : ''; ?>" class="filter-btn <?php echo $sort === 'oldest' ? 'active' : ''; ?>">古い順</a>
                
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="filter-btn" style="background: #28a745; color: white; border-color: #28a745; border-radius: 20px; padding: 0.5rem 1rem;">
                            <i class="fas fa-check-double"></i> すべて既読
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if (empty($messages)): ?>
                <div class="card">メッセージはありません。</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php 
                        $isUnread = isset($msg['is_read']) && $msg['is_read'] == 0;
                        $isSuggestion = isset($msg['source']) && $msg['source'] === 'suggestion';
                        $cardClass = 'message-card';
                        if ($isUnread) $cardClass .= ' unread';
                        if ($isSuggestion) $cardClass .= ' suggestion';
                    ?>
                    <div class="<?php echo $cardClass; ?>">
                        <div class="message-meta">
                            <span>
                                <?php if ($isSuggestion): ?>
                                    <span class="source-badge suggestion">📮 目安箱</span>
                                <?php else: ?>
                                    <span class="source-badge contact">📧 お問い合わせ</span>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($msg['name']); ?></strong>
                                <?php if (!$isSuggestion): ?>
                                    (<?php echo htmlspecialchars($msg['email']); ?>)
                                <?php endif; ?>
                            </span>
                            <span><?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?></span>
                        </div>
                        <div class="message-body"><?php echo htmlspecialchars($msg['message']); ?></div>
                        <div class="action-btns">
                            <?php if ($isUnread): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="mark_read" value="<?php echo $msg['id']; ?>">
                                    <?php if ($source !== 'all'): ?><input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>"><?php endif; ?>
                                    <button type="submit" class="action-btn btn-read">
                                        <i class="fas fa-check"></i> 既読にする
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？\n(スパムや不要なメッセージ)');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="delete" value="<?php echo $msg['id']; ?>">
                                <?php if ($source !== 'all'): ?><input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>"><?php endif; ?>
                                <button type="submit" class="action-btn btn-delete">
                                    <i class="fas fa-trash"></i> 削除
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
