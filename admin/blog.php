<?php
require_once '../config.php';
requireLogin();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $thumbnail = $_POST['thumbnail'] ?? '';
        
        if ($title && $content) {
            $stmt = $pdo->prepare("INSERT INTO blogs (title, content, thumbnail, author_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$title, $content, $thumbnail ?: null, $_SESSION['user_id']])) {
                $success = 'ブログを投稿しました！';
            } else {
                $error = 'エラーが発生しました。';
            }
        } else {
            $error = 'タイトルと本文を入力してください。';
        }
    } elseif ($action === 'delete') {
        $blog_id = $_POST['blog_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$blog_id]);
        $success = 'ブログを削除しました。';
    }
}

// Fetch existing blogs
$stmt = $pdo->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id ORDER BY b.created_at DESC");
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブログ管理 | WHABITAT</title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="apple-touch-icon" href="../logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .blog-form {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .blog-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .blog-item {
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .blog-item-info h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .blog-item-info .meta {
            font-size: 0.8rem;
            color: #888;
        }
        .blog-item-actions {
            display: flex;
            gap: 8px;
        }
        textarea.form-input {
            min-height: 200px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo">
                <img src="../logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 800px;">
            <a href="../dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; color: #333; text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> ダッシュボードに戻る
            </a>
            
            <h1 style="font-size: 1.8rem; margin-bottom: 1.5rem;">ブログ管理</h1>
            
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="blog-form">
                <h2 style="font-size: 1.2rem; margin-bottom: 1rem;"><i class="fas fa-plus-circle"></i> 新規投稿</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label">タイトル</label>
                        <input type="text" name="title" class="form-input" required placeholder="記事のタイトル">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">サムネイル画像URL（任意）</label>
                        <input type="url" name="thumbnail" class="form-input" placeholder="https://example.com/image.jpg">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">本文</label>
                        <textarea name="content" class="form-input" required placeholder="記事の本文を入力..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%;">投稿する</button>
                </form>
            </div>
            
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem;">投稿済み記事</h2>
            
            <?php if (empty($blogs)): ?>
                <div style="text-align: center; padding: 2rem; color: #888;">
                    まだ記事がありません。
                </div>
            <?php else: ?>
                <div class="blog-list">
                    <?php foreach ($blogs as $blog): ?>
                        <div class="blog-item">
                            <div class="blog-item-info">
                                <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                                <div class="meta">
                                    <?php echo date('Y/m/d H:i', strtotime($blog['created_at'])); ?> 
                                    <?php if ($blog['author_name']): ?>
                                        - <?php echo htmlspecialchars($blog['author_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="blog-item-actions">
                                <a href="../blog_view.php?id=<?php echo $blog['id']; ?>" target="_blank" class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                    <button type="submit" class="btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
