<?php 
require_once 'config.php';
$csrf_token = generateCsrfToken();
$hero = $_GET['hero'] ?? 'grid'; // 既定は写真タイル(grid)。比較用: full | split | grid | typo
$hero = in_array($hero, ['full','split','grid','typo'], true) ? $hero : 'full';

// Fetch recent blogs for homepage
$pdo = getDB();
$recent_blogs = [];
try {
    $stmt = $pdo->query("SELECT * FROM blogs WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3");
    $recent_blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
    $recent_blogs = [];
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHABITAT | 早稲田大学ボランティアサークル</title>
    <meta name="description" content="WHABITAT（ワビタット）は、国際NGO Habitat for Humanity の早稲田大学学生支部です。海外での住居建築ボランティア（GV）と、国内の地域に根ざしたボランティア活動に取り組んでいます。">
    <link rel="canonical" href="https://whabitathome.com/">

    <!-- OGP / SNSシェア用 -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="WHABITAT | 早稲田大学ボランティアサークル">
    <meta property="og:description" content="国際NGO Habitat for Humanity の早稲田大学学生支部。海外での住居建築ボランティア（GV）と、国内の地域に根ざしたボランティア活動に取り組んでいます。">
    <meta property="og:url" content="https://whabitathome.com/">
    <meta property="og:image" content="https://whabitathome.com/ogp.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <!-- Structured Data for Google Sitelinks -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "WHABITAT",
        "alternateName": "早稲田大学ハビタット",
        "url": "https://whabitathome.com",
        "logo": "https://whabitathome.com/logo.png",
        "description": "国際NGO Habitat for Humanity Japan の早稲田大学学生支部",
        "sameAs": [
            "https://www.instagram.com/whabitat_wu/"
        ]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "WHABITAT",
        "url": "https://whabitathome.com",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://whabitathome.com/?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SiteNavigationElement",
        "name": ["GV (海外ボランティア)", "JV (国内ボランティア)", "国内単発ボランティア", "定例MTG", "Contact"],
        "url": [
            "https://whabitathome.com/activity_gv.php",
            "https://whabitathome.com/activity_jv.php",
            "https://whabitathome.com/activity_domestic.php",
            "https://whabitathome.com/activity_mtg.php",
            "https://whabitathome.com/#contact"
        ]
    }
    </script>
</head>

<body>
    <header class="header">
        <div class="header-inner">
            <a href="#" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <button class="menu-toggle" aria-label="Toggle Menu" aria-expanded="false" aria-controls="nav-list">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav>
                <ul class="nav-list" id="nav-list">
                    <li><a href="#about" class="nav-link">About</a></li>
                    <li><a href="#activities" class="nav-link">Activities</a></li>
                    <li><a href="#blog" class="nav-link">Blog</a></li>
                    <li><a href="#join" class="nav-link">Join</a></li>
                    <li><a href="#contact" class="nav-link">Contact</a></li>
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

    <section class="hero hero--<?php echo htmlspecialchars($hero); ?>">
        <?php if ($hero === 'full'): ?>
        <div class="hero-bg" style="background-image: url('gv_new.jpg?v=<?php echo @filemtime(__DIR__ . '/gv_new.jpg') ?: '1'; ?>');"></div>
        <div class="hero-overlay"></div>
        <?php endif; ?>
        <div class="hero-content">
            <img src="logo.png" alt="WHABITAT Logo" class="hero-logo-main">
            <h1 class="hero-title">WHABITAT</h1>
            <p class="hero-catch">誰もが、きちんとした場所で暮らせる世界へ。</p>
            <div class="hero-subtitle-wrapper">
                <img src="waseda_logo.png" alt="Waseda University Logo" class="hero-logo-sub">
                <p class="hero-subtitle">WASEDA UNIVERSITY</p>
            </div>
            <div class="hero-cta">
                <a href="#activities" class="btn-hero btn-hero-primary"><i class="fas fa-compass"></i> 活動を見る</a>
                <a href="#contact" class="btn-hero btn-hero-ghost"><i class="far fa-paper-plane"></i> お問い合わせ</a>
            </div>
        </div>
        <?php if ($hero === 'split'): ?>
        <div class="hero-side-img" style="background-image: url('gv_new.jpg?v=<?php echo @filemtime(__DIR__ . '/gv_new.jpg') ?: '1'; ?>');"></div>
        <?php elseif ($hero === 'grid'): ?>
        <?php
        // フィルムストリップの並び: 活動ごとにグループ化
        // GV(海外) → MTG → 夏合宿(昼→夕) → 農業 → 雪かき、の流れでループ。
        // 3列目は object-position（同寸クロップ時の見せ位置）。
        $strip_photos = [
            ['tile3_s.jpg',  'GVでの住居建築',   'center 55%'],
            ['tile2_s.jpg',  'GV現地での移動',   ''],
            ['tile4_s.jpg',  '海外派遣',         'center 45%'],
            ['tile7_s.jpg',  '全体ミーティング', ''],
            ['tile1_s.jpg',  '夏合宿',           ''],
            ['tile8_s.jpg',  '夏合宿',           ''],
            ['tile9_s.jpg',  '夏合宿',           ''],
            ['tile5_s.jpg',  '農業ボランティア', ''],
            ['tile10_s.jpg', '農業ボランティア', 'center 78%'],
            ['tile6_s.jpg',  '農業ボランティア', ''],
            ['tile11_s.jpg', '雪かきボランティア', ''],
        ];
        ?>
        <div class="hero-strip">
            <div class="hero-strip-track">
                <?php for ($copy = 0; $copy < 2; $copy++): // シームレスループ用に2周分描画 ?>
                    <?php foreach ($strip_photos as [$file, $label, $pos]): ?>
                        <figure class="strip-item"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>>
                            <img src="<?php echo $file; ?>?v=<?php echo @filemtime(__DIR__ . '/' . $file) ?: '1'; ?>" alt="<?php echo htmlspecialchars($label); ?>"<?php echo $pos ? ' style="object-position: ' . $pos . ';"' : ''; ?><?php echo $copy ? ' loading="lazy"' : ''; ?> draggable="false">
                        </figure>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        <a href="#about" class="hero-scroll" aria-label="下へスクロール"><span></span></a>
    </section>

    <section id="about" class="bg-white">
        <div class="container">
            <div class="about-layout">
                <div class="about-intro fade-in">
                    <span class="about-label">About Us</span>
                    <h2 class="about-statement">「誰もが、きちんとした場所で<br>暮らせる世界」を目指して。</h2>
                    <p class="about-lead">WHABITAT（ワビタット）は、国際NGO Habitat for Humanity の早稲田大学学生支部です。海外では現地での住居建築ボランティア（GV）に参加し、国内では農業・ゴミ拾い・清掃・ビーチクリーンなど、地域に根ざしたボランティア活動に取り組んでいます。</p>
                </div>
                <div class="about-facts fade-in">
                    <dl class="fact-list">
                        <div class="fact-row"><dt>所属</dt><dd>早稲田大学公認サークル / WAVOC 公認</dd></div>
                        <div class="fact-row"><dt>母体</dt><dd>国際NGO Habitat for Humanity Japan 学生支部</dd></div>
                        <div class="fact-row"><dt>設立</dt><dd>2006年</dd></div>
                        <div class="fact-row"><dt>規模</dt><dd>約150名（男女比 4:6）</dd></div>
                        <div class="fact-row"><dt>構成</dt><dd>全学部・早稲田大学の学生限定</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="stats-grid stagger-children">
                <div class="stat-item fade-in">
                    <span class="stat-index">01</span>
                    <p class="stat-figure-line" aria-label="約150名以上">
                        <span class="stat-prefix">約</span><span class="stat-figure" data-target="150">0</span><span class="stat-suffix">名+</span>
                    </p>
                    <p class="stat-label">所属メンバー</p>
                    <span class="stat-en" aria-hidden="true">Members</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-index">02</span>
                    <p class="stat-figure-line" aria-label="活動20年">
                        <span class="stat-figure" data-target="20">0</span><span class="stat-suffix">年</span>
                    </p>
                    <p class="stat-label">活動の歴史（2006〜）</p>
                    <span class="stat-en" aria-hidden="true">History</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-index">03</span>
                    <p class="stat-figure-line" aria-label="全学部">
                        <span class="stat-figure stat-figure--word">全学部</span>
                    </p>
                    <p class="stat-label">文系・理系問わず</p>
                    <span class="stat-en" aria-hidden="true">Faculties</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-index">04</span>
                    <p class="stat-figure-line" aria-label="男女比 4対6">
                        <span class="stat-figure stat-figure--ratio">4:6</span>
                    </p>
                    <p class="stat-label">男女比（男:女）</p>
                    <span class="stat-en" aria-hidden="true">Ratio M:F</span>
                </div>
            </div>
        </div>
    </section>

    <section id="activities" class="bg-light">
        <div class="container">
            <h2 class="section-title fade-in"><span>Activities</span></h2>
            <div class="activity-grid stagger-children">
                <!-- GV -->
                <a href="activity_gv.php" class="activity-card fade-in" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('gv_hero.jpg?v=<?php echo @filemtime(__DIR__ . '/gv_hero.jpg') ?: '1'; ?>'); background-position: center 52%;">
                    </div>
                    <div class="activity-content">
                        <span class="activity-tag">Main Event</span>
                        <h3>GV (Global Village)</h3>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem;">
                            海外住居建築ボランティア。開発途上国へ渡航し、約2週間かけて「家を建てる」手伝いをします。</p>
                        <div style="font-size: 0.85rem; color: var(--accent-red); font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> ネパール、カンボジア、ベトナムなど
                        </div>
                    </div>
                </a>

                <!-- JV -->
                <a href="activity_jv.php" class="activity-card fade-in" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('jv.jpg?v=<?php echo @filemtime(__DIR__ . '/jv.jpg') ?: '1'; ?>');">
                    </div>
                    <div class="activity-content">
                        <span class="activity-tag">Domestic</span>
                        <h3>JV (Japan Village)</h3>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem;">
                            国内派遣ボランティア。日本各地の地域活性化、古民家再生、農業支援などを行います。</p>
                        <div style="font-size: 0.85rem; color: var(--accent-green); font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> 日本全国各地
                        </div>
                    </div>
                </a>

                <!-- MTG -->
                <a href="activity_mtg.php" class="activity-card fade-in" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('mtg_hero.jpg?v=<?php echo @filemtime(__DIR__ . '/mtg_hero.jpg') ?: '1'; ?>');">
                    </div>
                    <div class="activity-content">
                        <span class="activity-tag">Meeting</span>
                        <h3>全体ミーティング (MTG)</h3>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem;">
                            アイスブレイクで交流を深めたり、貧困・環境問題やボランティアの意義について学ぶ勉強会・ディスカッションを行います。</p>
                        <div style="font-size: 0.85rem; color: var(--accent-blue); font-weight: 600; margin-bottom: 0.5rem;">
                            <i class="far fa-clock"></i> 毎週水曜 6限（19:00〜）
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-color); font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> 早稲田キャンパス15号館 / 奉仕園
                        </div>
                    </div>
                </a>

                <!-- Domestic Volunteer -->
                <a href="activity_domestic.php" class="activity-card fade-in" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('domestic_hero.jpg?v=<?php echo @filemtime(__DIR__ . '/domestic_hero.jpg') ?: '1'; ?>'); background-position: center 62%;">
                    </div>
                    <div class="activity-content">
                        <span class="activity-tag">Domestic Volunteer</span>
                        <h3>国内単発ボランティア</h3>
                        <ul style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem; padding-left: 1.2rem;">
                            <li style="margin-bottom: 0.3rem;">ゴミ拾い（渋谷、早稲田周辺など）</li>
                            <li style="margin-bottom: 0.3rem;">農業ボランティア（東京地球農園など）</li>
                            <li style="margin-bottom: 0.3rem;">地域連携（お祭り手伝い、防災など）</li>
                            <li>短期合宿（新潟、山梨など 1〜3泊）</li>
                        </ul>
                        <div style="font-size: 0.85rem; color: var(--accent-green); font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> 都内近郊および地方各地
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section id="blog" class="bg-white">
        <div class="container">
            <h2 class="section-title fade-in"><span>Blog</span></h2>
            <p class="fade-in" style="text-align: center; color: var(--text-light); margin-bottom: 2.5rem;">活動報告やお知らせ</p>
            
            <?php if (!empty($recent_blogs)): ?>
            <div class="blog-grid">
                <?php foreach ($recent_blogs as $blog): ?>
                    <a href="blog_view.php?id=<?php echo (int)$blog['id']; ?>" class="blog-card fade-in">
                        <?php if ($blog['thumbnail']): ?>
                            <div class="blog-card-img" style="background-image: url('<?php echo htmlspecialchars($blog['thumbnail']); ?>');"></div>
                        <?php else: ?>
                            <div class="blog-card-img" style="background: linear-gradient(135deg, var(--lp-accent) 0%, var(--lp-accent-2) 100%); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-alt" style="font-size: 2.5rem; color: rgba(255,255,255,0.6);"></i>
                            </div>
                        <?php endif; ?>
                        <div class="blog-card-body">
                            <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                            <div style="font-size: 0.8rem; color: var(--lp-text-soft);">
                                <i class="far fa-calendar-alt"></i> <?php echo date('Y年m月d日', strtotime($blog['created_at'])); ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="blog.php" class="btn-secondary" style="display: inline-block;">すべての記事を見る <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #888;">
                <i class="fas fa-newspaper" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p style="font-size: 1.1rem;">Coming Soon</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">記事を準備中です。お楽しみに！</p>
                <div style="margin-top: 1.5rem;">
                    <a href="blog.php" class="btn-secondary" style="display: inline-block;">ブログページへ <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>


    <!-- Join Section -->
    <section id="join" class="bg-light">
        <div class="container">
            <h2 class="section-title fade-in"><span>Join Us</span></h2>
            <p class="join-lead fade-in">WHABITATは年中入会可能です（会費は半期3,000円）。<br>InstagramのDM、または下のお問い合わせフォームからお気軽にどうぞ。</p>
            <ol class="join-steps stagger-children">
                <li class="join-step fade-in">
                    <span class="join-num">01</span>
                    <div>
                        <h3>入会したい旨を連絡する</h3>
                        <p>InstagramのDM（または本ページのお問い合わせフォーム）で「入会したい」と送ってください。学部・学年・フルネームを添えてもらえるとスムーズです。</p>
                    </div>
                </li>
                <li class="join-step fade-in">
                    <span class="join-num">02</span>
                    <div>
                        <h3>会費を入金して連絡する</h3>
                        <p>案内される口座に会費3,000円（半期分）を入金し、完了したらその旨を連絡してください。</p>
                    </div>
                </li>
                <li class="join-step fade-in">
                    <span class="join-num">03</span>
                    <div>
                        <h3>会員専用ページに登録する</h3>
                        <p>案内に沿ってこのサイトの会員専用ページにログインし、必要事項を入力します。終わったら一言連絡してください。</p>
                    </div>
                </li>
                <li class="join-step fade-in">
                    <span class="join-num">04</span>
                    <div>
                        <h3>正規グループへ招待</h3>
                        <p>確認が取れ次第、WHABITATの正規LINEグループに招待します。一緒に活動しましょう！</p>
                    </div>
                </li>
            </ol>
            <div class="join-cta fade-in">
                <a href="https://www.instagram.com/whabinsta" target="_blank" class="btn-secondary"><i class="fab fa-instagram"></i> Instagram DMで連絡する</a>
                <a href="#contact" class="btn-hero btn-hero-ghost"><i class="far fa-paper-plane"></i> フォームから問い合わせる</a>
            </div>
        </div>
    </section>

    <section id="contact" class="bg-white">
        <div class="container">
            <h2 class="section-title fade-in"><span>Contact</span></h2>
            <div class="contact-form fade-in">
                <?php if (isset($_SESSION['contact_success']) && $_SESSION['contact_success']): ?>
                <div
                    style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                    メッセージを送信しました。ありがとうございます！
                </div>
                <?php unset($_SESSION['contact_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['contact_error'])): ?>
                <div
                    style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                    <?php echo htmlspecialchars($_SESSION['contact_error']); ?>
                </div>
                <?php unset($_SESSION['contact_error']); ?>
                <?php endif; ?>

                <form action="contact_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <!-- Honeypot field - hidden from humans, bots will fill it -->
                    <div style="position: absolute; left: -9999px;" aria-hidden="true">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">お名前</label>
                        <input type="text" name="name" class="form-input" required placeholder="早稲田 花子">
                    </div>
                    <div class="form-group">
                        <label class="form-label">メールアドレス</label>
                        <input type="email" name="email" class="form-input" required placeholder="example@waseda.jp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">メッセージ</label>
                        <textarea name="message" class="form-input" rows="5" required
                            placeholder="ご質問やメッセージをどうぞ"></textarea>
                    </div>
                    <div class="form-group" style="display: flex; justify-content: center;">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>
                    <button type="submit" class="btn-submit">送信する</button>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
                <a href="https://www.instagram.com/whabinsta" target="_blank">Instagram</a>
                <a href="#contact">Contact</a>
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

    document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right').forEach(el => {
        observer.observe(el);
    });

    // Header scroll effect
    const header = document.querySelector('.header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }, { passive: true });

    // ヒーローのフィルムストリップ: 自動スクロール + ドラッグ/横ホイールで手動送り
    const stripEl = document.querySelector('.hero-strip');
    const stripTrack = document.querySelector('.hero-strip-track');
    if (stripEl && stripTrack) {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const speed = window.matchMedia('(max-width: 768px)').matches ? 62 : 80; // px/s
        let offset = 0, setW = 0, dragging = false, lastX = 0, prev = performance.now();
        const measure = () => { setW = stripTrack.scrollWidth / 2; };
        measure();
        window.addEventListener('resize', measure);
        const tick = (now) => {
            const dt = Math.min((now - prev) / 1000, 0.1); prev = now;
            if (!dragging && !reduced) offset += speed * dt;
            if (setW > 0) offset = ((offset % setW) + setW) % setW;
            stripTrack.style.transform = 'translate3d(' + (-offset) + 'px, 0, 0)';
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        stripEl.addEventListener('pointerdown', (e) => {
            dragging = true; lastX = e.clientX;
            stripEl.setPointerCapture(e.pointerId);
        });
        stripEl.addEventListener('pointermove', (e) => {
            if (!dragging) return;
            offset -= (e.clientX - lastX); lastX = e.clientX;
        });
        ['pointerup', 'pointercancel'].forEach(t => stripEl.addEventListener(t, () => { dragging = false; }));
        stripEl.addEventListener('wheel', (e) => {
            if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) { offset += e.deltaX; e.preventDefault(); }
        }, { passive: false });
    }

    // 実績数字のカウントアップ
    const statFigures = document.querySelectorAll('.stat-figure[data-target]');
    if (statFigures.length) {
        const statObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const target = parseInt(el.dataset.target, 10) || 0;
                const duration = 1400;
                let startTime = null;
                const step = (now) => {
                    if (startTime === null) startTime = now;
                    const progress = Math.min((now - startTime) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.floor(eased * target).toLocaleString();
                    if (progress < 1) requestAnimationFrame(step);
                    else el.textContent = target.toLocaleString();
                };
                requestAnimationFrame(step);
                statObserver.unobserve(el);
            });
        }, { threshold: 0.4 });
        statFigures.forEach(el => statObserver.observe(el));
    }
    </script>
</body>

</html>