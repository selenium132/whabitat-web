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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | WHABITAT</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        <div class="dashboard-container" style="max-width: 900px;">
            <!-- Back link -->
            <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-color); text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> トップに戻る
            </a>
            
            <!-- Page Header -->
            <div class="card" style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Blog</h1>
                <p style="color: var(--text-light); margin: 0;">活動報告やお知らせ</p>
                
                <?php if ($is_admin): ?>
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                    <a href="admin/blog.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> 新規投稿・管理
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($blogs)): ?>
                <!-- Empty State -->
                <div class="card" style="text-align: center; padding: 4rem 2rem;">
                    <i class="fas fa-newspaper" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <h2 style="font-size: 1.3rem; margin-bottom: 0.5rem; color: #666;">Coming Soon</h2>
                    <p style="color: var(--text-light);">記事を準備中です。お楽しみに！</p>
                </div>
            <?php else: ?>
                <!-- Blog List -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php foreach ($blogs as $blog): ?>
                        <a href="blog_view.php?id=<?php echo $blog['id']; ?>" class="card" style="text-decoration: none; color: inherit; display: block; transition: transform 0.2s, box-shadow 0.2s;">
                            <div style="display: flex; gap: 1.5rem; align-items: flex-start;">
                                <!-- Thumbnail -->
                                <?php
                                    // CSS url() コンテキスト用にサニタイズ（引用符・括弧・空白・バックスラッシュ等を除去してブレイクアウトを防止）
                                    $thumb_css = preg_replace('/[\'"()\\\\\s]/', '', (string)$blog['thumbnail']);
                                ?>
                                <?php if ($thumb_css !== ''): ?>
                                    <div style="width: 160px; height: 100px; flex-shrink: 0; border-radius: 8px; background-image: url('<?php echo htmlspecialchars($thumb_css, ENT_QUOTES); ?>'); background-size: cover; background-position: center;"></div>
                                <?php else: ?>
                                    <div style="width: 160px; height: 100px; flex-shrink: 0; border-radius: 8px; background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-file-alt" style="font-size: 1.5rem; color: rgba(255,255,255,0.5);"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Content -->
                                <div style="flex: 1; min-width: 0;">
                                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; line-height: 1.4;"><?php echo htmlspecialchars($blog['title']); ?></h3>
                                    <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars(mb_substr(strip_tags($blog['content']), 0, 100)); ?>...
                                    </p>
                                    <div style="font-size: 0.8rem; color: #888; display: flex; gap: 15px;">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?></span>
                                        <?php if ($blog['author_name']): ?>
                                            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
