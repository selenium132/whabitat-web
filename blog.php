<?php
require_once 'config.php';

$pdo = getDB();

// Fetch published blogs
$stmt = $pdo->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.is_published = 1 ORDER BY b.created_at DESC");
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .blog-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .blog-card-img {
            width: 100%;
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #f0f0f0;
        }
        .blog-card-content {
            padding: 1.5rem;
        }
        .blog-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            line-height: 1.4;
        }
        .blog-card-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 0.75rem;
        }
        .blog-card-excerpt {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #888;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .back-link:hover {
            color: var(--primary-color);
        }
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
        <div class="dashboard-container" style="max-width: 1000px;">
            <a href="index.php" class="back-link"><i class="fas fa-chevron-left"></i> トップに戻る</a>
            
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Blog</h1>
            <p style="color: #666; margin-bottom: 2rem;">WHABITATの活動報告やお知らせ</p>
            
            <?php if (empty($blogs)): ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>まだブログ記事がありません。</p>
                </div>
            <?php else: ?>
                <div class="blog-grid">
                    <?php foreach ($blogs as $blog): ?>
                        <a href="blog_view.php?id=<?php echo $blog['id']; ?>" class="blog-card">
                            <?php if ($blog['thumbnail']): ?>
                                <div class="blog-card-img" style="background-image: url('<?php echo htmlspecialchars($blog['thumbnail']); ?>');"></div>
                            <?php else: ?>
                                <div class="blog-card-img" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-file-alt" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                                </div>
                            <?php endif; ?>
                            <div class="blog-card-content">
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($blog['title']); ?></h3>
                                <div class="blog-card-meta">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?>
                                    <?php if ($blog['author_name']): ?>
                                        <span style="margin-left: 10px;"><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars(mb_substr(strip_tags($blog['content']), 0, 100)); ?>...</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
