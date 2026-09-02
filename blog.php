<?php
require_once 'config.php';

$pdo = getDB();

// Fetch published blogs (記事は年々増えるためページ送りで取得)
$per_page = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$total_pages = 1;
$blogs = [];
try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE is_published = 1")->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page));
    if ($page > $total_pages) $page = $total_pages;
    $stmt = $pdo->prepare("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.is_published = 1 ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, ($page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->execute();
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
    <meta name="description" content="WHABITAT（ワビタット）の活動報告やお知らせをお届けします。">
    <link rel="canonical" href="https://whabitathome.com/blog.php<?php echo $page > 1 ? '?page=' . (int)$page : ''; ?>">
    <?php if ($page > 1): ?><link rel="prev" href="https://whabitathome.com/blog.php<?php echo $page > 2 ? '?page=' . ($page - 1) : ''; ?>"><?php endif; ?>
    <?php if ($page < $total_pages): ?><link rel="next" href="https://whabitathome.com/blog.php?page=<?php echo $page + 1; ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="Blog | WHABITAT">
    <meta property="og:description" content="WHABITAT（早稲田大学ボランティアサークル）の活動報告やお知らせ。">
    <meta property="og:url" content="https://whabitathome.com/blog.php">
    <meta property="og:image" content="https://whabitathome.com/ogp.jpg?v=<?php echo @filemtime(__DIR__ . '/ogp.jpg') ?: '1'; ?>">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ========= Blog 一覧（ミニマル・モノトーン）========= */
        .blog-page {
            padding: 9rem 0 7rem;
        }

        .blog-page-head {
            text-align: center;
            margin-bottom: 4rem;
        }
        .blog-page-label {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--lp-muted);
            display: block;
            margin-bottom: 1rem;
        }
        .blog-page-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: clamp(1.9rem, 4.5vw, 2.6rem);
            letter-spacing: .02em;
            color: var(--lp-ink);
            margin: 0;
        }
        .blog-page-title::after {
            content: "";
            display: block;
            width: 30px;
            height: 1px;
            margin: 1.3rem auto 0;
            background: var(--lp-ink);
            opacity: .28;
        }
        .blog-page-lead {
            margin: 1.2rem auto 0;
            font-size: .95rem;
            color: var(--lp-muted);
            line-height: 1.8;
        }

        /* 戻るリンク / 管理リンク */
        .blog-back {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            font-weight: 500;
            letter-spacing: .02em;
            color: var(--lp-muted);
            text-decoration: none;
            margin-bottom: 2.5rem;
            transition: color .25s;
        }
        .blog-back:hover { color: var(--lp-ink); }
        .blog-back i { font-size: .75rem; }

        .blog-admin-bar {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
        }

        /* 記事リスト：横並びカード（罫線基調） */
        .blog-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            max-width: 880px;
            margin: 0 auto;
        }
        .blog-row {
            display: flex;
            gap: 1.8rem;
            align-items: stretch;
            text-decoration: none;
            color: inherit;
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            overflow: hidden;
            transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease);
        }
        .blog-row:hover {
            border-color: var(--lp-ink);
            transform: translateY(-3px);
        }
        .blog-row-thumb {
            width: 200px;
            flex-shrink: 0;
            background-size: cover;
            background-position: center;
            background-color: var(--lp-paper-2);
        }
        .blog-row-thumb--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--lp-line);
        }
        .blog-row-thumb--empty i { font-size: 1.6rem; }
        .blog-row-body {
            flex: 1;
            min-width: 0;
            padding: 1.6rem 1.8rem 1.6rem 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .blog-row-body h2 {
            text-align: left;
            font-size: 1.12rem;
            font-weight: 600;
            line-height: 1.5;
            color: var(--lp-ink);
            margin: 0 0 .55rem;
        }
        .blog-row-excerpt {
            font-size: .88rem;
            line-height: 1.75;
            color: var(--lp-muted);
            margin: 0 0 .9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .blog-row-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.2rem;
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            letter-spacing: .06em;
            color: var(--lp-muted);
        }
        .blog-row-meta i { margin-right: .35rem; opacity: .8; }

        /* 空状態 */
        /* ページ送り（ミニマル・モノトーン） */
        .pager { display: flex; justify-content: center; align-items: center; gap: 1.2rem; margin-top: 3.5rem; }
        .pager-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            font-family: 'Montserrat', sans-serif; font-size: .78rem; font-weight: 600; letter-spacing: .06em;
            color: var(--lp-ink); text-decoration: none;
            border: 1px solid var(--lp-line); border-radius: 999px; padding: .45rem 1.1rem;
            transition: border-color .3s, background .3s;
        }
        .pager-btn:hover { border-color: var(--lp-ink); background: rgba(26,26,26,.04); }
        .pager-btn--disabled { opacity: .35; pointer-events: none; }
        .pager-info { font-family: 'Montserrat', sans-serif; font-size: .8rem; color: var(--lp-muted); letter-spacing: .08em; }

        .blog-empty {
            max-width: 560px;
            margin: 0 auto;
            text-align: center;
            padding: 4.5rem 2rem;
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            background: var(--lp-paper);
        }
        .blog-empty-en {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: .14em;
            font-size: 1.05rem;
            color: var(--lp-ink);
            margin: 0 0 .6rem;
        }
        .blog-empty p {
            font-size: .9rem;
            color: var(--lp-muted);
            margin: 0;
            line-height: 1.8;
        }

        @media (max-width: 680px) {
            .blog-page { padding: 7rem 0 5rem; }
            .blog-row { flex-direction: column; gap: 0; }
            .blog-row-thumb { width: 100%; height: 180px; }
            .blog-row-body { padding: 1.4rem 1.4rem 1.5rem; }
        }
    </style>
</head>

<body>
    <?php $nav_blog = 'blog.php'; include 'partials/header.php'; ?>

    <main id="main">
        <section class="bg-white blog-page">
            <div class="container">
                <a href="index.php" class="blog-back"><i class="fas fa-chevron-left"></i> トップに戻る</a>

                <div class="blog-page-head fade-in">
                    <span class="blog-page-label">Journal</span>
                    <h1 class="blog-page-title">Blog</h1>
                    <p class="blog-page-lead">活動報告やお知らせ</p>
                </div>

                <?php if ($is_admin): ?>
                <div class="blog-admin-bar fade-in">
                    <a href="admin/blog.php" class="btn-secondary"><i class="fas fa-plus"></i> 新規投稿・管理</a>
                </div>
                <?php endif; ?>

                <?php if (empty($blogs)): ?>
                    <div class="blog-empty fade-in">
                        <p class="blog-empty-en">Coming Soon</p>
                        <p>記事を準備中です。お楽しみに。</p>
                    </div>
                <?php else: ?>
                    <div class="blog-list stagger-children">
                        <?php foreach ($blogs as $blog): ?>
                            <?php
                                // CSS url() コンテキスト用にサニタイズ（引用符・括弧・空白・バックスラッシュ等を除去してブレイクアウトを防止）
                                $thumb_css = preg_replace('/[\'"()\\\\\s]/', '', (string)$blog['thumbnail']);
                            ?>
                            <a href="blog_view.php?id=<?php echo (int)$blog['id']; ?>" class="blog-row fade-in">
                                <?php if ($thumb_css !== ''): ?>
                                    <div class="blog-row-thumb" style="background-image: url('<?php echo htmlspecialchars($thumb_css, ENT_QUOTES); ?>');"></div>
                                <?php else: ?>
                                    <div class="blog-row-thumb blog-row-thumb--empty">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="blog-row-body">
                                    <h2><?php echo htmlspecialchars($blog['title']); ?></h2>
                                    <p class="blog-row-excerpt">
                                        <?php echo htmlspecialchars(mb_substr(strip_tags($blog['content']), 0, 100)); ?>...
                                    </p>
                                    <div class="blog-row-meta">
                                        <span><i class="far fa-calendar-alt"></i><?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?></span>
                                        <?php if ($blog['author_name']): ?>
                                            <span><i class="far fa-user"></i><?php echo htmlspecialchars($blog['author_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="pager" aria-label="ページ送り">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="pager-btn"><i class="fas fa-chevron-left"></i> 前へ</a>
                        <?php else: ?>
                            <span class="pager-btn pager-btn--disabled"><i class="fas fa-chevron-left"></i> 前へ</span>
                        <?php endif; ?>
                        <span class="pager-info"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="pager-btn">次へ <i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pager-btn pager-btn--disabled">次へ <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'partials/footer.php'; ?>

</body>

</html>
