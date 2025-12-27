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

// Fetch related posts (other recent posts)
$stmt = $pdo->prepare("SELECT id, title, thumbnail, created_at FROM blogs WHERE id != ? AND is_published = 1 ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$blog_id]);
$related_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']); ?> | WHABITAT Blog</title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($blog['content']), 0, 150)); ?>">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .article-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .article-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .article-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.4;
            color: #222;
        }
        .article-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 0.9rem;
            color: #888;
            flex-wrap: wrap;
        }
        .article-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .article-thumbnail {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .article-content {
            font-size: 1.05rem;
            line-height: 2;
            color: #333;
        }
        .article-content p {
            margin-bottom: 1.5rem;
        }
        .article-content h2, .article-content h3 {
            margin: 2rem 0 1rem;
            font-weight: 600;
        }
        .article-content blockquote {
            border-left: 4px solid #667eea;
            padding-left: 1.5rem;
            margin: 1.5rem 0;
            color: #555;
            font-style: italic;
        }
        .article-content ul, .article-content ol {
            margin: 1rem 0 1.5rem 1.5rem;
        }
        .article-content li {
            margin-bottom: 0.5rem;
        }
        .article-content hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 2rem 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #667eea;
        }
        .share-section {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 3rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        .share-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            transition: transform 0.2s, opacity 0.2s;
        }
        .share-btn:hover {
            transform: scale(1.1);
            opacity: 0.9;
        }
        .share-twitter { background: #1da1f2; }
        .share-line { background: #00b900; }
        .share-copy { background: #666; cursor: pointer; border: none; }
        .related-section {
            margin-top: 4rem;
        }
        .related-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
        }
        .related-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s;
        }
        .related-card:hover {
            transform: translateY(-5px);
        }
        .related-card-img {
            height: 140px;
            background-size: cover;
            background-position: center;
            background-color: #f0f0f0;
        }
        .related-card-content {
            padding: 1rem;
        }
        .related-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 0.3rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .related-card-date {
            font-size: 0.75rem;
            color: #888;
        }
        .draft-banner {
            background: #fff3cd;
            color: #856404;
            padding: 10px 20px;
            text-align: center;
            font-size: 0.9rem;
        }
        .admin-edit-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #667eea;
            color: white;
            padding: 15px 25px;
            border-radius: 30px;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .admin-edit-btn:hover {
            transform: translateY(-3px);
        }
        @media (max-width: 768px) {
            .article-title { font-size: 1.7rem; }
            .article-content { font-size: 1rem; }
            .related-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php if (!$blog['is_published']): ?>
    <div class="draft-banner">
        <i class="fas fa-eye-slash"></i> この記事は下書き状態です（管理者のみ表示）
    </div>
    <?php endif; ?>

    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <main>
        <div class="article-container">
            <a href="blog.php" class="back-link"><i class="fas fa-chevron-left"></i> ブログ一覧に戻る</a>
            
            <article>
                <div class="article-header">
                    <h1 class="article-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
                    <div class="article-meta">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?></span>
                        <?php if ($blog['author_name']): ?>
                            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($blog['thumbnail']): ?>
                    <img src="<?php echo htmlspecialchars($blog['thumbnail']); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="article-thumbnail">
                <?php endif; ?>
                
                <div class="article-content">
                    <?php 
                    $content = htmlspecialchars($blog['content']);
                    // Simple markdown-like processing
                    $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
                    $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
                    $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
                    $content = preg_replace('/^- (.+)$/m', '<li>$1</li>', $content);
                    $content = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $content);
                    $content = preg_replace('/^---$/m', '<hr>', $content);
                    $content = nl2br($content);
                    echo $content;
                    ?>
                </div>
                
                <div class="share-section">
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>&text=<?php echo urlencode($blog['title']); ?>" 
                       target="_blank" class="share-btn share-twitter" title="Xでシェア">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="https://social-plugins.line.me/lineit/share?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>" 
                       target="_blank" class="share-btn share-line" title="LINEでシェア">
                        <i class="fab fa-line"></i>
                    </a>
                    <button class="share-btn share-copy" onclick="copyLink()" title="リンクをコピー">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
            </article>
            
            <?php if (!empty($related_posts)): ?>
            <div class="related-section">
                <h2 class="related-title">他の記事</h2>
                <div class="related-grid">
                    <?php foreach ($related_posts as $post): ?>
                        <a href="blog_view.php?id=<?php echo $post['id']; ?>" class="related-card">
                            <?php if ($post['thumbnail']): ?>
                                <div class="related-card-img" style="background-image: url('<?php echo htmlspecialchars($post['thumbnail']); ?>');"></div>
                            <?php else: ?>
                                <div class="related-card-img" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                            <?php endif; ?>
                            <div class="related-card-content">
                                <h3 class="related-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <span class="related-card-date"><?php echo date('Y.m.d', strtotime($post['created_at'])); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($is_admin): ?>
    <a href="admin/blog.php?edit=<?php echo $blog['id']; ?>" class="admin-edit-btn">
        <i class="fas fa-edit"></i> 編集
    </a>
    <?php endif; ?>

    <footer style="background: #f8f9fa; padding: 2rem; text-align: center; color: #888; font-size: 0.85rem;">
        <a href="index.php" style="color: #666; text-decoration: none;">© WHABITAT</a>
    </footer>

    <script>
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('リンクをコピーしました！');
            });
        }
    </script>
</body>
</html>
