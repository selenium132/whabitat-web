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
    <meta name="description" content="WHABITAT（ワビタット）の活動報告やお知らせをお届けします。">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
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
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <button class="menu-toggle" aria-label="Toggle Menu" aria-expanded="false" aria-controls="nav-list">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav>
                <ul class="nav-list" id="nav-list">
                    <li><a href="index.php#about" class="nav-link">About</a></li>
                    <li><a href="index.php#activities" class="nav-link">Activities</a></li>
                    <li><a href="blog.php" class="nav-link">Blog</a></li>
                    <li><a href="index.php#contact" class="nav-link">Contact</a></li>
                    <li>
                        <a href="https://x.com/whabitat?s=21" target="_blank" class="social-icon"><i
                                class="fab fa-x-twitter"></i></a>
                        <a href="https://www.instagram.com/whabinsta" target="_blank"
                            class="social-icon"><i class="fab fa-instagram"></i></a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php" class="btn-login"><i class="fas fa-user"></i> MY PAGE</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn-login"><i class="fas fa-lock"></i> MEMBER LOGIN</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function () {
            this.classList.toggle('active');
            const isOpen = document.querySelector('.nav-list').classList.toggle('nav-open');
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close menu when a link is clicked
        document.querySelectorAll('.nav-link, .btn-login').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelector('.menu-toggle').classList.remove('active');
                document.querySelector('.menu-toggle').setAttribute('aria-expanded', 'false');
                document.querySelector('.nav-list').classList.remove('nav-open');
            });
        });
    </script>

    <main>
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
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
                <a href="https://www.instagram.com/whabinsta" target="_blank">Instagram</a>
                <a href="index.php#contact">Contact</a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.8rem; color: #ccc;">&copy; 2025 WHABITAT Waseda University Chapter.
                All Rights Reserved.</p>
        </div>
    </footer>

    <script>
    // Intersection Observer for fade-in animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

    // Header scroll effect
    const header = document.querySelector('.header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }, { passive: true });
    </script>
</body>

</html>
