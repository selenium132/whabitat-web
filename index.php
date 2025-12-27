<?php 
require_once 'config.php'; 
$csrf_token = generateCsrfToken();

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
    <meta name="description" content="WHABITAT（ワビタット）は、国際NGO Habitat for Humanity Japan の学生支部です。「誰もがきちんとした場所で暮らせる世界」を目指し、国内外で住居建築支援を行っています。">
    <link rel="canonical" href="https://whabitathome.com/">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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
            <button class="menu-toggle" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav>
                <ul class="nav-list">
                    <li><a href="#about" class="nav-link">About</a></li>
                    <li><a href="#activities" class="nav-link">Activities</a></li>
                    <li><a href="#blog" class="nav-link">Blog</a></li>
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

    <section class="hero">
        <div class="hero-content">
            <img src="logo.png" alt="WHABITAT Logo" class="hero-logo-main">
            <h1 class="hero-title">WHABITAT</h1>
            <div class="hero-subtitle-wrapper">
                <img src="waseda_logo.png" alt="Waseda University Logo" class="hero-logo-sub">
                <p class="hero-subtitle">WASEDA UNIVERSITY</p>
            </div>
        </div>
    </section>

    <section id="about" class="bg-white">
        <div class="container">
            <h2 class="section-title"><span>About Us</span></h2>
            <p style="text-align: center; max-width: 700px; margin: 0 auto 4rem; color: var(--text-light);">
                WHABITAT（ワビタット）は、国際NGO Habitat for Humanity Japan の学生支部です。<br>
                「誰もがきちんとした場所で暮らせる世界」を目指し、国内外で住居建築支援を行っています。
            </p>

            <div class="about-grid">
                <div class="info-card">
                    <div style="color: var(--accent-green); font-size: 2rem; margin-bottom: 1rem;"><i
                            class="fas fa-university"></i></div>
                    <h3>団体概要</h3>
                    <ul style="font-size: 0.95rem; color: var(--text-color);">
                        <li style="margin-bottom: 0.5rem;">早稲田大学公認サークル</li>
                        <li style="margin-bottom: 0.5rem;">WAVOC 公認</li>
                        <li style="margin-bottom: 0.5rem;">国際NGO Habitat for Humanity Japan 学生支部</li>
                        <li>設立：2006年</li>
                    </ul>
                </div>
                <div class="info-card">
                    <div style="color: var(--accent-blue); font-size: 2rem; margin-bottom: 1rem;"><i
                            class="fas fa-users"></i></div>
                    <h3>規模・構成</h3>
                    <ul style="font-size: 0.95rem; color: var(--text-color);">
                        <li style="margin-bottom: 0.5rem;">人数：約200名</li>
                        <li style="margin-bottom: 0.5rem;">男女比：4:6</li>
                        <li style="margin-bottom: 0.5rem;">学部：全学部（文系理系問わず）</li>
                        <li>早稲田大学の学生限定</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="activities" class="bg-light">
        <div class="container">
            <h2 class="section-title"><span>Activities</span></h2>
            <div class="activity-grid">
                <!-- GV -->
                <a href="activity_gv.php" class="activity-card" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('gv_new.jpg?v=<?php echo time(); ?>');">
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
                <a href="activity_jv.php" class="activity-card" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('jv.jpg?v=<?php echo time(); ?>');">
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
                <a href="activity_mtg.php" class="activity-card" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('daily.jpg?v=<?php echo time(); ?>');">
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
                <a href="activity_domestic.php" class="activity-card" style="text-decoration: none; color: inherit; display: block;">
                    <div class="activity-img"
                        style="background-image: url('domestic.jpg?v=<?php echo time(); ?>');">
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
            <h2 class="section-title"><span>Blog</span></h2>
            <p style="text-align: center; color: var(--text-light); margin-bottom: 2.5rem;">活動報告やお知らせ</p>
            
            <?php if (!empty($recent_blogs)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($recent_blogs as $blog): ?>
                    <a href="blog_view.php?id=<?php echo $blog['id']; ?>" style="text-decoration: none; color: inherit; display: block; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <?php if ($blog['thumbnail']): ?>
                            <div style="width: 100%; height: 180px; background-image: url('<?php echo htmlspecialchars($blog['thumbnail']); ?>'); background-size: cover; background-position: center;"></div>
                        <?php else: ?>
                            <div style="width: 100%; height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-alt" style="font-size: 2.5rem; color: rgba(255,255,255,0.5);"></i>
                            </div>
                        <?php endif; ?>
                        <div style="padding: 1.25rem;">
                            <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; line-height: 1.4;"><?php echo htmlspecialchars($blog['title']); ?></h3>
                            <div style="font-size: 0.8rem; color: #888;">
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


    <section id="contact" class="bg-white">
        <div class="container">
            <h2 class="section-title"><span>Contact</span></h2>
            <div class="contact-form">
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
</body>

</html>