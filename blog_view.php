<?php
require_once 'config.php';

$blog_id = $_GET['id'] ?? 0;
if (!$blog_id) {
    header("Location: blog.php");
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.id = ?");
$stmt->execute([$blog_id]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header("Location: blog.php");
    exit;
}

// Allow admin to see draft posts
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$blog['is_published'] && !$is_admin) {
    header("Location: blog.php");
    exit;
}

// Fetch related posts
$related = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, created_at FROM blogs WHERE id != ? AND is_published = 1 ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$blog_id]);
    $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']); ?> | WHABITAT</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .article-content {
            font-size: 1rem;
            line-height: 1.9;
            color: #333;
        }
        .article-content h1 { font-size: 1.5rem; margin: 2rem 0 1rem; font-weight: 700; text-align: left; }
        .article-content h2 { font-size: 1.3rem; margin: 2rem 0 1rem; font-weight: 600; text-align: left; }
        .article-content h3 { font-size: 1.1rem; margin: 1.5rem 0 0.75rem; font-weight: 600; text-align: left; }
        .article-content blockquote {
            border-left: 3px solid var(--primary-color);
            padding-left: 1rem;
            margin: 1rem 0;
            color: #666;
        }
        .article-content hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 2rem 0;
        }
        .article-content img {
            max-width: 100%;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .article-content a {
            color: var(--primary-color);
            text-decoration: underline;
        }
        .article-content .text-center {
            text-align: center;
        }
        .share-buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        .share-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: #fff;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .share-btn:hover { opacity: 0.8; }
        .share-twitter { background: #1da1f2; }
        .share-line { background: #00b900; }
        .share-copy { background: #666; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 750px;">
            <!-- Back link -->
            <a href="blog.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-color); text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> ブログ一覧
            </a>
            
            <?php if (!$blog['is_published']): ?>
            <div style="background: #fff3cd; color: #856404; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                <i class="fas fa-eye-slash"></i> この記事は下書き状態です
            </div>
            <?php endif; ?>
            
            <!-- Article -->
            <article class="card">
                <!-- Header -->
                <div style="margin-bottom: 1.5rem;">
                    <h1 style="font-size: 1.6rem; line-height: 1.4; margin-bottom: 1rem;"><?php echo htmlspecialchars($blog['title']); ?></h1>
                    <div style="font-size: 0.85rem; color: #888; display: flex; gap: 15px; flex-wrap: wrap;">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?></span>
                        <?php if ($blog['author_name']): ?>
                            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Thumbnail -->
                <?php if ($blog['thumbnail']): ?>
                    <img src="<?php echo htmlspecialchars($blog['thumbnail']); ?>" 
                         alt="<?php echo htmlspecialchars($blog['title']); ?>"
                         style="width: 100%; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php endif; ?>
                
                <!-- Content -->
                <div class="article-content">
                    <?php 
                    // WYSIWYG editor saves HTML directly, so we just output it
                    // Only allow safe HTML tags
                    $allowed_tags = '<h1><h2><h3><p><br><strong><b><em><i><u><a><img><ul><ol><li><blockquote><hr><div><span>';
                    echo strip_tags($blog['content'], $allowed_tags);
                    ?>
                </div>
                
                <!-- Share -->
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>&text=<?php echo urlencode($blog['title']); ?>" 
                       target="_blank" class="share-btn share-twitter" title="Xでシェア">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="https://social-plugins.line.me/lineit/share?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>" 
                       target="_blank" class="share-btn share-line" title="LINEでシェア">
                        <i class="fab fa-line"></i>
                    </a>
                    <button class="share-btn share-copy" onclick="navigator.clipboard.writeText(location.href).then(()=>alert('リンクをコピーしました'))" title="リンクをコピー">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
                
                <?php if ($is_admin): ?>
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                    <a href="admin/blog.php?edit=<?php echo $blog['id']; ?>" class="btn-secondary">
                        <i class="fas fa-edit"></i> 編集する
                    </a>
                </div>
                <?php endif; ?>
            </article>
            
            <!-- Related -->
            <?php if (!empty($related)): ?>
            <div class="card" style="margin-top: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;">他の記事</h3>
                <?php foreach ($related as $r): ?>
                    <a href="blog_view.php?id=<?php echo $r['id']; ?>" 
                       style="display: block; padding: 0.75rem 0; border-bottom: 1px solid #eee; text-decoration: none; color: inherit;">
                        <div style="font-weight: 500;"><?php echo htmlspecialchars($r['title']); ?></div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 4px;"><?php echo date('Y/m/d', strtotime($r['created_at'])); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
