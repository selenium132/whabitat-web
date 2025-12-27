<?php
require_once 'config.php';

$pdo = getDB();

// Fetch published blogs
$blogs = [];
try {
    $stmt = $pdo->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.is_published = 1 ORDER BY b.created_at DESC");
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | WHABITAT</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .blog-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 20px 60px;
            text-align: center;
            color: white;
        }
        .blog-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .blog-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .blog-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2rem;
        }
        .blog-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        .blog-card-img {
            width: 100%;
            height: 220px;
            background-size: cover;
            background-position: center;
            background-color: #f0f0f0;
            position: relative;
        }
        .blog-card-date {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .blog-card-content {
            padding: 1.5rem;
        }
        .blog-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #222;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .blog-card-excerpt {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.7;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .blog-card-footer {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: #888;
        }
        .blog-card-author {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .read-more {
            margin-left: auto;
            color: var(--primary-color, #667eea);
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #888;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }
        .admin-bar {
            background: #333;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
            align-items: center;
        }
        .admin-bar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        .admin-bar a:hover {
            background: rgba(255,255,255,0.2);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
        }
        .back-link:hover {
            color: var(--primary-color);
        }
        @media (max-width: 768px) {
            .blog-hero h1 { font-size: 2rem; }
            .blog-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php if ($is_admin): ?>
    <div class="admin-bar">
        <span><i class="fas fa-shield-alt"></i> 管理者モード</span>
        <a href="admin/blog.php"><i class="fas fa-plus-circle"></i> 新規投稿</a>
        <a href="admin/blog.php"><i class="fas fa-cog"></i> 記事管理</a>
    </div>
    <?php endif; ?>

    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <div class="blog-hero">
        <h1>Blog</h1>
        <p>WHABITATの活動報告・お知らせ</p>
    </div>

    <main>
        <div class="blog-container">
            <a href="index.php" class="back-link"><i class="fas fa-chevron-left"></i> トップに戻る</a>
            
            <?php if (empty($blogs)): ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #333;">Coming Soon</h2>
                    <p>ブログ記事はまだありません。<br>最初の記事をお待ちください！</p>
                </div>
            <?php else: ?>
                <div class="blog-grid">
                    <?php foreach ($blogs as $blog): ?>
                        <a href="blog_view.php?id=<?php echo $blog['id']; ?>" class="blog-card">
                            <?php if ($blog['thumbnail']): ?>
                                <div class="blog-card-img" style="background-image: url('<?php echo htmlspecialchars($blog['thumbnail']); ?>');">
                                    <span class="blog-card-date"><?php echo date('Y.m.d', strtotime($blog['created_at'])); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="blog-card-img" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                    <span class="blog-card-date"><?php echo date('Y.m.d', strtotime($blog['created_at'])); ?></span>
                                    <i class="fas fa-file-alt" style="font-size: 3.5rem; color: rgba(255,255,255,0.3);"></i>
                                </div>
                            <?php endif; ?>
                            <div class="blog-card-content">
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($blog['title']); ?></h3>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars(mb_substr(strip_tags($blog['content']), 0, 120)); ?>...</p>
                                <div class="blog-card-footer">
                                    <?php if ($blog['author_name']): ?>
                                        <span class="blog-card-author">
                                            <i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="read-more">続きを読む →</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer style="background: #f8f9fa; padding: 2rem; text-align: center; color: #888; font-size: 0.85rem;">
        <a href="index.php" style="color: #666; text-decoration: none;">© WHABITAT</a>
    </footer>
</body>
</html>
