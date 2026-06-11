<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>国内単発ボランティア | WHABITAT</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WHABITAT">
    <meta property="og:title" content="国内単発ボランティア | WHABITAT">
    <meta property="og:description" content="ゴミ拾い・農業・地域のお手伝い。身近なところから始める、地域に根ざしたボランティア。">
    <meta property="og:url" content="https://whabitathome.com/activity_domestic.php">
    <meta property="og:image" content="https://whabitathome.com/domestic_hero.jpg">
    <meta property="og:locale" content="ja_JP">
    <meta name="twitter:card" content="summary_large_image">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ===== 国内単発ボランティア — ページ固有（ミニマル / モノトーン） ===== */
        .dm-main { padding-top: 0; padding-bottom: 6rem; }

        /* ページ見出し */
        .dm-head { text-align: center; margin-bottom: 3rem; }
        .dm-head .dm-eyebrow {
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: .72rem; font-weight: 600; letter-spacing: .24em;
            text-transform: uppercase; color: var(--lp-muted);
            margin-bottom: 1rem;
        }
        .dm-head h1 {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(1.7rem, 4.5vw, 2.4rem); letter-spacing: .02em;
            color: var(--lp-ink); margin: 0;
        }
        .dm-head .dm-jp { display: block; font-size: 1.05rem; font-weight: 500; color: var(--lp-muted); margin-top: .8rem; }

        /* トップ写真：カラーで見せる。罫線 + 控えめな暗幕で見出しを白文字 */
        .dm-hero {
            position: relative; width: 100%; height: clamp(220px, 38vw, 420px);
            border: 1px solid var(--lp-line); border-radius: var(--lp-radius);
            overflow: hidden; margin-bottom: 5rem;
        }
        .dm-hero img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* 導入文 */
        .dm-intro { max-width: 720px; margin: 0 auto 6rem; text-align: center; }
        .dm-intro h2 {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(1.3rem, 3vw, 1.7rem); color: var(--lp-ink);
            margin: 0 0 .8rem;
        }
        .dm-intro h2::after {
            content: ""; display: block; width: 30px; height: 1px;
            margin: 1.1rem auto 2rem; background: var(--lp-ink); opacity: .28;
        }
        .dm-intro p { line-height: 2; color: var(--lp-muted); margin: 0 0 1.4rem; }
        .dm-intro .dm-lead {
            font-size: 1.12rem; font-weight: 700; color: var(--lp-ink);
            letter-spacing: .02em; margin: 1.8rem 0;
        }
        .dm-intro .dm-note { font-size: .9rem; margin-top: 2.4rem; }
        .dm-intro strong { color: var(--lp-ink); font-weight: 700; }
        .dm-intro a { color: var(--lp-ink); text-decoration: underline; text-underline-offset: .2em; }
        .dm-intro a:hover { opacity: .6; }

        /* 年間スケジュール：罫線基調のタイムライン */
        .dm-sched-head { text-align: center; margin-bottom: 3rem; }
        .dm-sched-head h2 {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(1.3rem, 3vw, 1.7rem); letter-spacing: .02em;
            color: var(--lp-ink); margin: 0;
        }
        .dm-sched-head .dm-sched-note {
            display: block; font-size: .78rem; color: var(--lp-muted);
            margin-top: .8rem; letter-spacing: .04em;
        }
        .dm-timeline {
            max-width: 760px; margin: 0 auto 6rem;
            border-top: 1px solid var(--lp-line);
        }
        .dm-tl-row {
            display: grid; grid-template-columns: 7.5rem 1fr; gap: 1.5rem;
            align-items: baseline; padding: 1.3rem .3rem;
            border-bottom: 1px solid var(--lp-line);
            transition: background .35s var(--lp-ease);
        }
        .dm-tl-row:hover { background: var(--lp-paper-2); }
        .dm-tl-month {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: 1.5rem; color: var(--lp-ink); line-height: 1;
            display: flex; align-items: baseline; gap: .4rem;
        }
        .dm-tl-month small { font-size: .72rem; font-weight: 500; letter-spacing: .12em; color: var(--lp-muted); text-transform: uppercase; }
        .dm-tl-items { display: flex; flex-wrap: wrap; gap: .5rem; }
        .dm-tl-items span {
            font-size: .82rem; color: var(--lp-ink);
            border: 1px solid var(--lp-line); border-radius: 999px;
            padding: .25rem .8rem; line-height: 1.5;
        }

        /* 各セクション見出し */
        .dm-section { max-width: var(--lp-max); margin: 0 auto 6rem; }
        .dm-section-title {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(1.25rem, 2.8vw, 1.6rem); color: var(--lp-ink);
            letter-spacing: .01em; text-align: center; margin: 0 0 .6rem;
        }
        .dm-section-desc { text-align: center; color: var(--lp-muted); font-size: .95rem; line-height: 1.8; margin: 0 auto 3rem; max-width: 40em; }

        /* サブ見出し（左の短い罫線つき） */
        .dm-subtitle {
            display: flex; align-items: center; gap: .8rem;
            font-size: 1.02rem; font-weight: 700; color: var(--lp-ink);
            letter-spacing: .02em; margin: 0 0 1.6rem;
        }
        .dm-subtitle::before { content: ""; width: 24px; height: 1px; background: var(--lp-ink); opacity: .5; flex: none; }

        /* カードグリッド */
        .dm-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .dm-grid:last-child { margin-bottom: 0; }

        .dm-card {
            display: flex; flex-direction: column;
            background: var(--lp-paper); border: 1px solid var(--lp-line);
            border-radius: var(--lp-radius); overflow: hidden;
            transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease);
        }
        .dm-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .dm-card-img { overflow: hidden; }
        .dm-card-img img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; transition: transform .7s var(--lp-ease); }
        .dm-card:hover .dm-card-img img { transform: scale(1.04); }
        .dm-card-body { padding: 1.6rem; }
        .dm-tag {
            display: inline-block; font-family: 'Montserrat', sans-serif;
            font-size: .64rem; font-weight: 600; letter-spacing: .12em;
            text-transform: uppercase; color: var(--lp-muted);
            border: 1px solid var(--lp-line); background: transparent;
            padding: .25rem .7rem; border-radius: 999px; margin-bottom: .9rem;
        }
        .dm-card-body h4 { font-size: 1.12rem; font-weight: 600; color: var(--lp-ink); margin: 0 0 .7rem; }
        .dm-card-body p { font-size: .9rem; line-height: 1.85; color: var(--lp-muted); margin: 0; }
        .dm-card-body p strong { color: var(--lp-ink); font-weight: 700; }

        /* 横長カード（ゴミ拾い） */
        .dm-card-wide { grid-column: 1 / -1; }
        @media (min-width: 781px) {
            .dm-card-wide { flex-direction: row; }
            .dm-card-wide .dm-card-img { flex: 0 0 42%; }
            .dm-card-wide .dm-card-img img { height: 100%; aspect-ratio: auto; }
            .dm-card-wide .dm-card-body { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 2.2rem; }
        }

        @media (max-width: 780px) {
            .dm-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .dm-tl-row { grid-template-columns: 5.5rem 1fr; gap: 1rem; }
            .dm-tl-month { font-size: 1.25rem; }
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

    <main class="dm-main">
        <section class="page-hero">
            <div class="page-hero-bg" style="background-image: url('domestic_hero.jpg?v=<?php echo @filemtime(__DIR__ . '/domestic_hero.jpg') ?: '1'; ?>'); background-position: center 62%;"></div>
            <div class="page-hero-overlay"></div>
            <div class="page-hero-inner">
                <p class="page-hero-eyebrow">Domestic Volunteer</p>
                <h1 class="page-hero-title">国内単発ボランティア</h1>
                <p class="page-hero-sub">ゴミ拾い・農業・地域のお手伝い。身近なところから始める、地域に根ざしたボランティア。</p>
            </div>
        </section>

        <nav class="page-crumb" aria-label="パンくず">
            <a href="index.php">Home</a><span>/</span><a href="index.php#activities">Activities</a><span>/</span>国内単発
        </nav>

        <div class="container">

            <!-- Intro -->
            <div class="dm-intro fade-in">
                <h2>なぜ、ワビタットでボランティア？</h2>
                <p>
                    「自分の成長のため」「人の役に立ちたい」「大学生の今しかできないことがしたい」<br>
                    参加の理由は人それぞれですが、多くのメンバーが口を揃えて言うのは、
                </p>
                <p class="dm-lead">「ワビタットでのボランティアは楽しいから！」</p>
                <p>真剣に全力で活動しながらも、仲間と笑顔で楽しめるのが魅力です。</p>
                <p class="dm-note">
                    ワビタットの国内ボランティアは、大きく分けて<strong>「日帰り（1日）」</strong>と<strong>「短期合宿（1泊〜）」</strong>の2種類があります。（※長期の派遣ボランティアについては <a href="activity_gv.php">GV</a> や <a href="activity_jv.php">JV</a> のページをご覧ください）
                </p>
            </div>

            <!-- Annual Schedule -->
            <div class="dm-sched-head fade-in">
                <h2>年間スケジュール</h2>
                <span class="dm-sched-note">※2026年度は未定</span>
            </div>
            <div class="dm-timeline fade-in">
                <div class="dm-tl-row">
                    <span class="dm-tl-month">02 <small>Feb</small></span>
                    <div class="dm-tl-items"><span>西和賀</span><span>援農</span><span>ゴミ拾い</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">03 <small>Mar</small></span>
                    <div class="dm-tl-items"><span>援農</span><span>武家屋敷</span><span>丹波山</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">04 <small>Apr</small></span>
                    <div class="dm-tl-items"><span>新歓ゴミ拾い</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">05 <small>May</small></span>
                    <div class="dm-tl-items"><span>ビーチクリーン</span><span>援農</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">06 <small>Jun</small></span>
                    <div class="dm-tl-items"><span>援農</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">08 <small>Aug</small></span>
                    <div class="dm-tl-items"><span>ゴミ拾い</span><span>丹波山</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">09 <small>Sep</small></span>
                    <div class="dm-tl-items"><span>寺泊</span><span>お神輿手伝い</span><span>援農</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">10 <small>Oct</small></span>
                    <div class="dm-tl-items"><span>援農</span><span>防災訓練</span></div>
                </div>
                <div class="dm-tl-row">
                    <span class="dm-tl-month">11 <small>Nov</small></span>
                    <div class="dm-tl-items"><span>ゴミ拾い</span></div>
                </div>
            </div>

            <!-- 1. Day-trip Volunteer -->
            <section class="dm-section fade-in">
                <h2 class="dm-section-title">日帰りボランティア</h2>
                <p class="dm-section-desc">最も参加しやすく、月に1〜2回程度行われています。</p>

                <h3 class="dm-subtitle">ゴミ拾い・ビーチクリーン</h3>
                <div class="dm-grid">
                    <div class="dm-card dm-card-wide">
                        <div class="dm-card-img"><img src="domestic_trash_picking.jpg" alt="ゴミ拾い・ビーチクリーン"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Trash Picking &amp; Beach Clean</span>
                            <h4>ゴミ拾い・ビーチクリーン</h4>
                            <p>
                                渋谷や早稲田周辺で年2回程度、ビンゴや謎解きを取り入れた楽しいゴミ拾いを行っています。<br>
                                さらに5月〜6月頃には、神奈川県や千葉県などの海岸へ遠征して<strong>ビーチクリーン</strong>も実施！海風を感じながら、他のチームやサークルと協力して活動する人気イベントです。
                            </p>
                        </div>
                    </div>
                </div>

                <h3 class="dm-subtitle">農業ボランティア (援農)</h3>
                <div class="dm-grid">
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_iijima_farm.jpg" alt="飯島農園"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Chiba</span>
                            <h4>北習志野</h4>
                            <p>千葉県北習志野にある無農薬野菜を作っている農園でのお手伝い。お味噌の仕込みを行ったり、美味しいお野菜をいただいたりと、食と農に触れる貴重な体験ができます。</p>
                        </div>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_earth_farm.jpg" alt="東京地球農園"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Tokyo</span>
                            <h4>あきる野</h4>
                            <p>自然豊かな東京都あきる野市での農業体験。様々なお野菜に加え、栗や鶏のお世話などができます。お昼にはうどんやカレーをいただけたりと、心も体も温まる活動です。</p>
                        </div>
                    </div>
                </div>

                <h3 class="dm-subtitle">地域連携・その他</h3>
                <div class="dm-grid">
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_omikoshi.jpg" alt="お神輿・お祭り手伝い"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Festival</span>
                            <h4>お神輿・お祭り手伝い</h4>
                            <p>早稲田周辺の町内会のお祭りに参加し、お神輿を担ぐお手伝いをします。幅広い年齢層の地域の方々と交流できる貴重な機会です。</p>
                        </div>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_bosai.jpg" alt="地域防災訓練"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Bosai</span>
                            <h4>地域防災訓練</h4>
                            <p>町内会の防災訓練に参加し、地域の方々と共に防災意識を高めます。炊き出し（芋煮など）のお手伝いをすることもあり、地域との繋がりを深めます。</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Short-term Camps -->
            <section class="dm-section fade-in">
                <h2 class="dm-section-title">短期合宿ボランティア</h2>
                <p class="dm-section-desc">1泊〜3泊4日程度。事前・事後の活動が少ないため、気軽に参加できるのが特徴です。</p>
                <div class="dm-grid">
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_teradomari.jpg" alt="寺泊 (新潟県長岡市)" style="object-position: 60% 50%;"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Niigata</span>
                            <h4>寺泊</h4>
                            <p>「求草里山再生プロジェクト」に参加し、竹林整備や地域観光の支援を行います。竹や木を刈って神社周辺を整備したり、地域の方とBBQで交流を深めます。</p>
                        </div>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_nishiwaga.jpg" alt="西和賀（岩手県）" style="object-position: right bottom;"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Iwate</span>
                            <h4>西和賀</h4>
                            <p>豪雪地帯である岩手県西和賀町で、2月の「雪あかり」イベントのお手伝いをします。雪国ならではの文化に触れ、地域の方々と深く交流できる活動です。</p>
                        </div>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_bukeyashiki.jpg" alt="武家屋敷 (東京都日の出町)"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Tokyo</span>
                            <h4>武家屋敷</h4>
                            <p>歴史ある武家屋敷で、引越しのお手伝いや、忍者道場の広報支援などを行いました。援農とセットで行くことが多く、夜は温泉や鍋を囲んでの交流も！</p>
                        </div>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-img"><img src="domestic_tabayama.jpg" alt="丹波山村 (山梨県)"></div>
                        <div class="dm-card-body">
                            <span class="dm-tag">Yamanashi</span>
                            <h4>丹波山</h4>
                            <p>人口減少や空き家問題などの課題を抱える丹波山村へ滞在。空き家の片付けを通じて、地域再生のお手伝いをします。</p>
                        </div>
                    </div>
                </div>
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
