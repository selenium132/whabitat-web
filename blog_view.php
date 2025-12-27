<?php
require_once 'config.php';

$blog_id = $_GET['id'] ?? 0;
if (!$blog_id) {
    header("Location: blog.php");
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.id = ? AND b.is_published = 1");
$stmt->execute([$blog_id]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header("Location: blog.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']); ?> | WHABITAT Blog</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .blog-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .blog-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .blog-meta {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 1.5rem;
        }
        .blog-thumbnail {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .blog-content {
            font-size: 1rem;
            line-height: 1.8;
            color: #333;
        }
        .blog-content p {
            margin-bottom: 1.5rem;
        }
        .blog-content img {
            max-width: 100%;
            border-radius: 8px;
            margin: 1rem 0;
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
        .blog-footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            text-align: center;
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
        <div class="dashboard-container" style="max-width: 800px;">
            <a href="blog.php" class="back-link"><i class="fas fa-chevron-left"></i> ブログ一覧に戻る</a>
            
            <article>
                <div class="blog-header">
                    <h1 class="blog-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
                    <div class="blog-meta">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?></span>
                        <?php if ($blog['author_name']): ?>
                            <span style="margin-left: 15px;"><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($blog['thumbnail']): ?>
                    <img src="<?php echo htmlspecialchars($blog['thumbnail']); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" class="blog-thumbnail">
                <?php endif; ?>
                
                <div class="blog-content">
                    <?php echo nl2br(htmlspecialchars($blog['content'])); ?>
                </div>
                
                <div class="blog-footer">
                    <a href="blog.php" class="btn-secondary" style="display: inline-block;">
                        <i class="fas fa-arrow-left"></i> 他の記事を見る
                    </a>
                </div>
            </article>
        </div>
    </main>
</body>
</html>
