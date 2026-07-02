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

// OGP用: 本文の抜粋とサムネイルの絶対URL
$og_description = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($blog['content']))), 0, 90);
$og_image = !empty($blog['thumbnail'])
    ? 'https://whabitathome.com/' . ltrim($blog['thumbnail'], '/')
    : 'https://whabitathome.com/ogp.jpg';
$og_url = 'https://whabitathome.com/blog_view.php?id=' . (int)$blog['id'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']); ?> | WHABITAT</title>

    <!-- OGP / SNSシェア用（記事ごと） -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="<?php echo htmlspecialchars($blog['title']); ?> | WHABITAT">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($og_url); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?php echo htmlspecialchars($og_description); ?>">

    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* === blog_view: ミニマル・モノトーン（記事ページ固有） === */
        .article-wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* 戻る導線 */
        .article-back {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-family: 'Montserrat', sans-serif;
            font-size: .78rem;
            font-weight: 500;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--lp-muted);
            text-decoration: none;
            transition: color .25s var(--lp-ease);
            margin-bottom: 2.5rem;
        }
        .article-back:hover { color: var(--lp-ink); }
        .article-back i { font-size: .7rem; }

        /* 下書き表示（モノトーン罫線） */
        .draft-notice {
            display: flex;
            align-items: center;
            gap: .6rem;
            border: 1px solid var(--lp-line);
            background: var(--lp-paper-2);
            color: var(--lp-muted);
            padding: .8rem 1.1rem;
            border-radius: var(--lp-radius);
            margin-bottom: 2rem;
            font-size: .85rem;
            letter-spacing: .02em;
        }

        /* 記事ヘッダー */
        .article-head { margin-bottom: 2.5rem; }
        .article-eyebrow {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--lp-muted);
            display: block;
            margin-bottom: 1.2rem;
        }
        .article-title {
            font-size: clamp(1.6rem, 4vw, 2.3rem);
            font-weight: 600;
            line-height: 1.5;
            letter-spacing: .01em;
            color: var(--lp-ink);
            margin: 0 0 1.4rem;
        }
        .article-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.4rem;
            font-size: .82rem;
            color: var(--lp-muted);
            padding-bottom: 1.6rem;
            border-bottom: 1px solid var(--lp-line);
        }
        .article-meta span { display: inline-flex; align-items: center; gap: .5rem; }
        .article-meta i { font-size: .78rem; opacity: .8; }

        /* サムネイル（写真はカラーのまま） */
        .article-thumb {
            width: 100%;
            border-radius: var(--lp-radius);
            margin: 0 0 2.5rem;
            display: block;
        }

        /* 本文 */
        .article-content {
            font-size: 1rem;
            line-height: 2;
            color: var(--lp-ink-2);
        }
        .article-content h1 { font-family: 'Montserrat', sans-serif; font-size: 1.5rem; margin: 2.6rem 0 1rem; font-weight: 600; line-height: 1.5; color: var(--lp-ink); text-align: left; }
        .article-content h2 { font-size: 1.3rem; margin: 2.4rem 0 1rem; font-weight: 600; line-height: 1.55; color: var(--lp-ink); text-align: left; }
        .article-content h3 { font-size: 1.1rem; margin: 1.8rem 0 .75rem; font-weight: 600; line-height: 1.6; color: var(--lp-ink); text-align: left; }
        .article-content p { margin: 0 0 1.3rem; }
        .article-content ul, .article-content ol { margin: 0 0 1.3rem; padding-left: 1.4rem; }
        .article-content li { margin-bottom: .5rem; }
        .article-content blockquote {
            border-left: 2px solid var(--lp-ink);
            padding: .3rem 0 .3rem 1.2rem;
            margin: 1.6rem 0;
            color: var(--lp-muted);
            font-style: normal;
        }
        .article-content hr {
            border: none;
            border-top: 1px solid var(--lp-line);
            margin: 2.6rem 0;
        }
        .article-content img {
            max-width: 100%;
            border-radius: var(--lp-radius);
            margin: 1.4rem 0;
            display: block;
        }
        .article-content a {
            color: var(--lp-ink);
            text-decoration: underline;
            text-underline-offset: 3px;
            text-decoration-color: var(--lp-muted);
            transition: text-decoration-color .25s var(--lp-ease);
        }
        .article-content a:hover { text-decoration-color: var(--lp-ink); }
        .article-content .text-center { text-align: center; }

        /* シェア（墨の罫線アイコン） */
        .share-buttons {
            display: flex;
            align-items: center;
            gap: .9rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--lp-line);
        }
        .share-label {
            font-family: 'Montserrat', sans-serif;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--lp-muted);
            margin-right: .3rem;
        }
        .share-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid var(--lp-line);
            background: transparent;
            color: var(--lp-ink);
            font-size: .95rem;
            text-decoration: none;
            cursor: pointer;
            transition: border-color .3s var(--lp-ease), background .3s var(--lp-ease), color .3s var(--lp-ease), transform .3s var(--lp-ease);
        }
        .share-btn:hover {
            border-color: var(--lp-ink);
            background: var(--lp-ink);
            color: #fff;
            transform: translateY(-2px);
        }

        /* 管理者：編集導線 */
        .article-admin {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--lp-line);
            text-align: center;
        }

        /* 関連記事 */
        .related-block { margin-top: 4rem; }
        .related-title {
            font-family: 'Montserrat', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--lp-muted);
            margin: 0 0 .5rem;
        }
        .related-list { border-top: 1px solid var(--lp-line); }
        .related-item {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.1rem .2rem;
            border-bottom: 1px solid var(--lp-line);
            text-decoration: none;
            color: var(--lp-ink);
            transition: padding-left .3s var(--lp-ease), color .3s var(--lp-ease);
        }
        .related-item:hover { padding-left: .6rem; color: var(--lp-muted); }
        .related-item .related-name { font-size: .96rem; font-weight: 500; line-height: 1.5; color: var(--lp-ink); }
        .related-item .related-date { font-size: .78rem; color: var(--lp-muted); white-space: nowrap; flex-shrink: 0; }

        @media (max-width: 768px) {
            .article-wrap { padding: 0 1.2rem; }
            .related-item { flex-direction: column; gap: .3rem; }
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
        <section class="bg-white" style="padding-top: 9rem;">
            <div class="article-wrap fade-in">
                <!-- Back link -->
                <a href="blog.php" class="article-back">
                    <i class="fas fa-chevron-left"></i> Blog 一覧
                </a>

                <?php if (!$blog['is_published']): ?>
                <div class="draft-notice">
                    <i class="fas fa-eye-slash"></i> この記事は下書き状態です
                </div>
                <?php endif; ?>

                <!-- Article -->
                <article>
                    <!-- Header -->
                    <div class="article-head">
                        <span class="article-eyebrow">Blog</span>
                        <h1 class="article-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
                        <div class="article-meta">
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
                             class="article-thumb">
                    <?php endif; ?>

                    <!-- Content -->
                    <div class="article-content">
                        <?php
                        // WYSIWYG editor saves HTML directly, so we just output it
                        // Only allow safe HTML tags
                        $allowed_tags = '<h1><h2><h3><p><br><strong><b><em><i><u><a><img><ul><ol><li><blockquote><hr><div><span>';
                        $safe_content = strip_tags($blog['content'], $allowed_tags);
                        // Strip on* event handler attributes (e.g. onclick/onerror) to prevent stored XSS
                        $safe_content = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safe_content);
                        // Neutralize dangerous URL schemes in href/src.
                        // スキームは許可リスト方式（http/https/mailto/tel と相対パスのみ）。
                        // 判定前にHTMLエンティティを復号し制御文字/空白を除去することで、
                        // &#x6a;avascript: のようなエンコード回避や先頭制御文字の混入も無害化する。
                        $safe_content = preg_replace_callback(
                            '/\b(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                            function ($m) {
                                $val = trim($m[2], '"\'');
                                $decoded = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $decoded = preg_replace('/[\s\x00-\x20]+/', '', $decoded);
                                if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $decoded, $sm)) {
                                    $allowed = ['http', 'https', 'mailto', 'tel'];
                                    if (!in_array(strtolower($sm[1]), $allowed, true)) {
                                        return $m[1] . '="#"';
                                    }
                                }
                                return $m[0];
                            },
                            $safe_content
                        );
                        // style 属性内の危険なCSS（url()/expression/JSスキーム/@import）を含む場合は
                        // その style 属性ごと除去する。色・整列などの通常装飾は保持される。
                        $safe_content = preg_replace_callback(
                            '/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                            function ($m) {
                                if (preg_match('/url\s*\(|expression\s*\(|javascript\s*:|@import/i', $m[1])) {
                                    return '';
                                }
                                return $m[0];
                            },
                            $safe_content
                        );
                        // 本文画像は遅延読み込み（ファーストビュー外の写真でページを重くしない）
                        $safe_content = preg_replace('/<img\b/i', '<img loading="lazy" decoding="async"', $safe_content);
                        echo $safe_content;
                        ?>
                    </div>

                    <!-- Share -->
                    <div class="share-buttons">
                        <span class="share-label">Share</span>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>&text=<?php echo urlencode($blog['title']); ?>"
                           target="_blank" class="share-btn" title="Xでシェア">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                        <a href="https://social-plugins.line.me/lineit/share?url=<?php echo urlencode('https://whabitathome.com/blog_view.php?id=' . $blog['id']); ?>"
                           target="_blank" class="share-btn" title="LINEでシェア">
                            <i class="fab fa-line"></i>
                        </a>
                        <button class="share-btn" onclick="navigator.clipboard.writeText(location.href).then(()=>alert('リンクをコピーしました'))" title="リンクをコピー">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>

                    <?php if ($is_admin): ?>
                    <div class="article-admin">
                        <a href="admin/blog.php?edit=<?php echo $blog['id']; ?>" class="btn-secondary">
                            <i class="fas fa-edit"></i> 編集する
                        </a>
                    </div>
                    <?php endif; ?>
                </article>

                <!-- Related -->
                <?php if (!empty($related)): ?>
                <div class="related-block">
                    <h3 class="related-title">Other Posts</h3>
                    <div class="related-list">
                        <?php foreach ($related as $r): ?>
                            <a href="blog_view.php?id=<?php echo $r['id']; ?>" class="related-item">
                                <span class="related-name"><?php echo htmlspecialchars($r['title']); ?></span>
                                <span class="related-date"><?php echo date('Y/m/d', strtotime($r['created_at'])); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
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
        // Header scroll effect
        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, { passive: true });

        // Fade-in
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
    </script>
</body>
</html>
