<?php require_once 'config.php'; $csrf_token = generateCsrfToken(); ?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHABITAT | 早稲田大学ボランティアサークル</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
                    <li><a href="#contact" class="nav-link">Contact</a></li>
                    <li>
                        <a href="https://x.com/whabitat?s=21" target="_blank" class="social-icon"><i
                                class="fab fa-x-twitter"></i></a>
                        <a href="https://www.instagram.com/whabinsta?igsh=MXIybDBlMjFhZWVndA==" target="_blank"
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
            <h1 class="hero-title">WHABITAT</h1>
            <p class="hero-subtitle">WASEDA UNIVERSITY</p>
        </div>
    </section>

    <section id="about" class="bg-white">
        <div class="container">
            <h2 class="section-title"><span>About Us</span></h2>
            <p style="text-align: center; max-width: 700px; margin: 0 auto 4rem; color: var(--text-light);">
                WHABITAT（ワビタット）は、国際NGO Habitat for Humanity Japan の正式な学生支部です。<br>
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
                <div class="activity-card">
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
                </div>

                <!-- JV -->
                <div class="activity-card">
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
                </div>

                <!-- MTG -->
                <div class="activity-card">
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
                </div>

                <!-- Domestic Volunteer -->
                <div class="activity-card">
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
                </div>
            </div>
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

                <form action="contact_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
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
                    <button type="submit" class="btn-submit">送信する</button>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
                <a href="https://www.instagram.com/whabinsta?igsh=MXIybDBlMjFhZWVndA==" target="_blank">Instagram</a>
                <a href="#contact">Contact</a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.8rem; color: #ccc;">&copy; 2025 WHABITAT Waseda University Chapter.
                All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>