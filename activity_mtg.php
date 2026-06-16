<?php require_once 'config.php';
$pdo = getDB();

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS mtg_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    year_group INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Fetch MTG history grouped by year
$entries = $pdo->query("SELECT * FROM mtg_history ORDER BY year_group DESC, event_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($entries as $entry) {
    $grouped[$entry['year_group']][] = $entry;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全体ミーティング (MTG) | WHABITAT</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="全体ミーティング (MTG) | WHABITAT">
    <meta property="og:description" content="毎週水曜6限後。学年を超えた交流と、ボランティアの意義を学ぶ場。">
    <meta property="og:url" content="https://whabitathome.com/activity_mtg.php">
    <meta property="og:image" content="https://whabitathome.com/images/common/mtg_hero.jpg">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ===== MTGページ固有：ミニマル/モノトーン（landing.cssトーンに統一） ===== */
        .mtg-main { padding-top: 0; padding-bottom: 6rem; }

        /* リード（写真なし・タイポ主体で上品に） */
        .mtg-lead {
            max-width: 760px;
            margin: 0 auto 4.5rem;
            text-align: center;
        }
        .mtg-lead .about-label {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem; letter-spacing: .22em; text-transform: uppercase;
            color: var(--lp-muted); display: block; margin-bottom: 1.2rem;
        }
        .mtg-lead h1.section-title { margin-bottom: 1.6rem; }
        .mtg-lead-text {
            font-size: 1rem; line-height: 1.95; color: var(--lp-muted); margin: 0;
        }

        /* 主写真：カラーのまま・軽い暗幕は不要だが大きすぎないよう抑制 */
        .mtg-photo {
            width: 100%; max-height: 420px; object-fit: cover;
            border-radius: var(--lp-radius);
            border: 1px solid var(--lp-line);
            display: block;
            margin: 0 auto 4rem;
        }

        /* 概要カード（罫線基調・影なし、index の info/fact トーン） */
        .mtg-info-card {
            max-width: 760px; margin: 0 auto;
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            padding: 2.5rem;
            transition: border-color .35s var(--lp-ease);
        }
        .mtg-info-card:hover { border-color: var(--lp-ink); }
        .mtg-info-card h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem; font-weight: 600; color: var(--lp-ink);
            margin: 0 0 1.2rem;
        }
        .mtg-info-card p { line-height: 1.95; color: var(--lp-muted); margin: 0 0 1.8rem; }
        .mtg-meta { margin: 0; border-top: 1px solid var(--lp-line); }
        .mtg-meta .fact-row { display: grid; grid-template-columns: 4.5em 1fr; gap: 1.2rem; padding: 1rem 0; border-bottom: 1px solid var(--lp-line); }
        .mtg-meta .fact-row dt { font-size: .8rem; font-weight: 600; color: var(--lp-muted); letter-spacing: .06em; }
        .mtg-meta .fact-row dd { font-size: .94rem; color: var(--lp-ink); margin: 0; line-height: 1.65; }

        /* History 見出し行 */
        .mtg-history { margin-top: 7rem; }
        .mtg-history-head {
            display: flex; justify-content: center; align-items: center;
            position: relative; margin-bottom: 3.5rem;
        }
        .mtg-history-head .section-title { margin: 0; }
        .mtg-add-btn {
            position: absolute; right: 0; top: 50%; transform: translateY(-50%);
            display: inline-flex; align-items: center; gap: .45rem;
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem; font-weight: 600; letter-spacing: .08em;
            color: var(--lp-ink); text-decoration: none;
            border: 1px solid var(--lp-line); border-radius: 999px;
            padding: .4rem 1rem; transition: border-color .3s, background .3s;
        }
        .mtg-add-btn:hover { border-color: var(--lp-ink); background: rgba(26,26,26,.04); }

        /* 年グループ見出し */
        .history-year-group { margin-bottom: 4rem; }
        .history-year-group .year-label {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem; font-weight: 600; color: var(--lp-ink);
            letter-spacing: .04em;
            border-bottom: 1px solid var(--lp-line);
            padding-bottom: .7rem; margin: 0 0 2rem;
        }

        /* History グリッド/カード（活動カードと同トーン） */
        .history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
            gap: 1.5rem;
        }
        .history-card {
            display: flex; flex-direction: column; height: 100%;
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            overflow: hidden; box-shadow: none;
            transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease);
        }
        .history-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .history-card-img { width: 100%; height: 180px; background-size: cover; background-position: center; }
        .history-card-placeholder {
            width: 100%; height: 180px;
            display: flex; align-items: center; justify-content: center;
            background: var(--lp-paper-2);
            border-bottom: 1px solid var(--lp-line);
            color: var(--lp-muted); font-size: 1.6rem;
        }
        .history-info { flex-grow: 1; display: flex; flex-direction: column; padding: 1.3rem 1.4rem; }
        .history-date {
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem; font-weight: 600; letter-spacing: .08em;
            color: var(--lp-muted);
        }
        .history-info h4 {
            margin: .55rem 0; font-size: 1.05rem; font-weight: 600;
            line-height: 1.45; color: var(--lp-ink);
        }
        .history-info h4 .history-subtitle {
            display: block; margin-top: .25rem;
            font-size: .85rem; font-weight: 400; color: var(--lp-muted);
        }
        .history-info p {
            font-size: .85rem; line-height: 1.7; color: var(--lp-muted); margin: 0;
        }

        .mtg-empty {
            max-width: 760px; margin: 0 auto; text-align: center;
            color: var(--lp-muted);
            background: var(--lp-paper);
            border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius);
            padding: 2.5rem;
        }

        @media (max-width: 680px) {
            .mtg-main { padding-top: 0; }
            .mtg-info-card { padding: 1.8rem; }
            .mtg-history-head { flex-direction: column; gap: 1rem; }
            .mtg-add-btn { position: static; transform: none; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <button class="menu-toggle" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav>
                <ul class="nav-list">
                    <li><a href="index.php#about" class="nav-link">About</a></li>
                    <li><a href="index.php#activities" class="nav-link">Activities</a></li>
                    <li><a href="index.php#blog" class="nav-link">Blog</a></li>
                    <li><a href="index.php#join" class="nav-link">Join</a></li>
                    <li><a href="index.php#contact" class="nav-link">Contact</a></li>
                    <li>
                        <a href="https://x.com/whabitat?s=21" target="_blank" class="social-icon"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://www.instagram.com/whabinsta" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
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
            document.querySelector('.nav-list').classList.toggle('nav-open');
        });

        // Close menu when a link is clicked
        document.querySelectorAll('.nav-link, .btn-login').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelector('.menu-toggle').classList.remove('active');
                document.querySelector('.nav-list').classList.remove('nav-open');
            });
        });
    </script>

    <main class="mtg-main">
        <section class="page-hero">
            <div class="page-hero-bg" style="background-image: url('images/common/mtg_hero.jpg?v=<?php echo @filemtime(__DIR__ . '/images/common/mtg_hero.jpg') ?: '1'; ?>');"></div>
            <div class="page-hero-overlay"></div>
            <div class="page-hero-inner">
                <p class="page-hero-eyebrow">Weekly Meeting</p>
                <h1 class="page-hero-title">全体ミーティング (MTG)</h1>
                <p class="page-hero-sub">毎週水曜6限後。学年を超えた交流と、ボランティアの意義を学ぶ場。</p>
            </div>
        </section>

        <nav class="page-crumb" aria-label="パンくず">
            <a href="index.php">Home</a><span>/</span><a href="index.php#activities">Activities</a><span>/</span>MTG
        </nav>

        <div class="container">

            <div class="mtg-info-card">
                <h3>週に一度の交流と学びの場</h3>
                <p>
                    毎週水曜日の6限後に集まり、全体ミーティングを行っています。<br>
                    アイスブレイクで学年を超えた交流を深めたり、貧困問題や環境問題、ボランティアの意義について学ぶワークショップを行ったりしています。
                </p>
                <dl class="mtg-meta">
                    <div class="fact-row"><dt>日時</dt><dd>毎週水曜 6限（19:00〜）</dd></div>
                    <div class="fact-row"><dt>場所</dt><dd>早稲田キャンパス15号館、または奉仕園</dd></div>
                </dl>
            </div>

            <!-- MTG History -->
            <section class="mtg-history">
                <div class="mtg-history-head">
                    <h2 class="section-title"><span>MTG History</span></h2>
                    <?php if ($is_admin): ?>
                        <a href="admin/mtg_history.php" class="mtg-add-btn">
                            <i class="fas fa-plus"></i> 履歴追加
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($grouped)): ?>
                    <div class="mtg-empty">
                        MTG履歴はまだありません。
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped as $year => $yearEntries): ?>
                        <div class="history-year-group">
                            <h3 class="year-label"><?php echo htmlspecialchars($year); ?></h3>

                            <div class="history-grid">
                                <?php foreach ($yearEntries as $entry): ?>
                                    <article class="history-card">
                                        <?php if ($entry['image_path']): ?>
                                            <div class="history-card-img" style="background-image: url('<?php echo htmlspecialchars($entry['image_path']); ?>');" role="img" aria-label="<?php echo htmlspecialchars($entry['title']); ?>"></div>
                                        <?php else: ?>
                                            <div class="history-card-placeholder">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="history-info">
                                            <span class="history-date"><?php echo date('Y.m.d', strtotime($entry['event_date'])); ?></span>
                                            <h4>
                                                <?php echo htmlspecialchars($entry['title']); ?>
                                                <?php if ($entry['subtitle']): ?>
                                                    <span class="history-subtitle"><?php echo htmlspecialchars($entry['subtitle']); ?></span>
                                                <?php endif; ?>
                                            </h4>
                                            <?php if ($entry['description']): ?>
                                                <p>
                                                    <?php echo nl2br(htmlspecialchars($entry['description'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
                <a href="https://www.instagram.com/whabinsta" target="_blank">Instagram</a>
                <a href="index.php#contact">Contact</a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.8rem; color: #ccc;">&copy; 2025 WHABITAT Waseda University Chapter. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
