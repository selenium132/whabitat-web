<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GV (Global Village) | WHABITAT</title>
    <meta name="description" content="GV（Global Village）は、国際NGO Habitat for Humanity が世界中で展開する海外住居建築ボランティアプログラムです。WHABITATの渡航実績やGVの流れ、よくある質問を紹介します。">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =========================================================
           GV ページ固有スタイル — ミニマル / モノトーン
           landing.css の変数（--lp-*）を踏襲。色は写真にのみ持たせる。
           ========================================================= */
        .gv-main { padding-top: 0; padding-bottom: 0; }
        .gv-narrow { max-width: 940px; }

        /* ページ見出し（ヒーロー代わりの上品な導入） */
        .gv-lead-head { text-align: center; max-width: 760px; margin: 0 auto 3.5rem; }
        .gv-eyebrow {
            font-family: 'Montserrat', sans-serif; font-size: .72rem; font-weight: 600;
            letter-spacing: .22em; text-transform: uppercase; color: var(--lp-muted);
            display: block; margin-bottom: 1.2rem;
        }
        .gv-page-title {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(2rem, 5vw, 2.9rem); letter-spacing: .04em;
            color: var(--lp-ink); margin: 0 0 1.4rem; line-height: 1.2;
        }
        .gv-page-title small {
            display: block; font-size: .9rem; font-weight: 500; letter-spacing: .04em;
            color: var(--lp-muted); margin-top: .6rem;
        }
        .gv-lead-text { font-size: 1rem; line-height: 2; color: var(--lp-muted); margin: 0; }
        .gv-lead-text strong { color: var(--lp-ink); font-weight: 600; }

        /* キービジュアル（控えめな暗幕＋細枠。全面巨大表示はしない） */
        .gv-hero-img {
            position: relative; max-width: 1040px; margin: 0 auto 5rem;
            border: 1px solid var(--lp-line); border-radius: var(--lp-radius); overflow: hidden;
        }
        .gv-hero-img img { display: block; width: 100%; height: clamp(220px, 42vw, 420px); object-fit: cover; }

        /* セクション見出し（landing.css の .section-title と統一感） */
        .gv-section { padding: 5rem 0; }
        .gv-section + .gv-section { border-top: 1px solid var(--lp-line); }
        .gv-h2 { text-align: center; margin-bottom: 3rem; }
        .gv-h2 span {
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: clamp(1.4rem, 3.2vw, 1.9rem); letter-spacing: .01em; color: var(--lp-ink);
        }
        .gv-h2 span::after {
            content: ""; display: block; width: 30px; height: 1px; margin: 1.1rem auto 0;
            background: var(--lp-ink); opacity: .28;
        }
        .gv-sub { text-align: center; color: var(--lp-muted); font-size: .94rem; margin: -1.8rem auto 2.8rem; max-width: 40em; }

        /* VISION カード */
        .gv-vision { max-width: 760px; margin: 0 auto; border: 1px solid var(--lp-line); border-radius: var(--lp-radius); padding: 2.6rem 2rem; text-align: center; background: var(--lp-paper-2); }
        .gv-vision-label { font-family: 'Montserrat', sans-serif; font-size: .7rem; font-weight: 600; letter-spacing: .22em; color: var(--lp-muted); margin-bottom: 1rem; }
        .gv-vision-quote { font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: clamp(1.05rem, 2.6vw, 1.4rem); line-height: 1.5; color: var(--lp-ink); margin: 0 0 1.4rem; }
        .gv-vision-body { font-size: .95rem; line-height: 1.95; color: var(--lp-muted); margin: 0; }

        .gv-pillars-intro { text-align: center; margin: 3rem auto 2.2rem; font-size: 1rem; line-height: 1.9; color: var(--lp-ink); max-width: 38em; }
        .gv-pillars-intro b { font-weight: 600; }

        /* 3つの柱 */
        .gv-pillars { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .gv-card { border: 1px solid var(--lp-line); border-radius: var(--lp-radius); padding: 2rem 1.7rem; background: var(--lp-paper); transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease); }
        .gv-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .gv-card-icon { font-size: 1.3rem; color: var(--lp-ink); margin-bottom: 1rem; opacity: .85; }
        .gv-card h3 { font-family: 'Montserrat', sans-serif; font-size: 1.1rem; font-weight: 600; letter-spacing: .04em; color: var(--lp-ink); margin: 0 0 .3rem; }
        .gv-card .gv-card-sub { font-size: .78rem; font-weight: 500; letter-spacing: .02em; color: var(--lp-muted); margin: 0 0 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--lp-line); }
        .gv-card p { font-size: .9rem; line-height: 1.85; color: var(--lp-muted); margin: 0; }

        /* 参加する意義 */
        .gv-reasons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; max-width: 980px; margin: 0 auto; }
        .gv-reason { border: 1px solid var(--lp-line); border-radius: var(--lp-radius); padding: 2rem 1.7rem; background: var(--lp-paper); transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease); }
        .gv-reason:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .gv-reason-num { font-family: 'Montserrat', sans-serif; font-size: .8rem; font-weight: 600; letter-spacing: .18em; color: var(--lp-muted); margin-bottom: 1rem; }
        .gv-reason h3 { font-size: 1.05rem; font-weight: 600; color: var(--lp-ink); margin: 0 0 .8rem; }
        .gv-reason p { font-size: .9rem; line-height: 1.85; color: var(--lp-muted); margin: 0; }

        /* GVの流れ（罫線基調のステップ） */
        .gv-steps { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0; max-width: 1040px; margin: 0 auto; border: 1px solid var(--lp-line); border-radius: var(--lp-radius); overflow: hidden; }
        .gv-step { position: relative; padding: 1.8rem 1.2rem; background: var(--lp-paper); border-right: 1px solid var(--lp-line); transition: background .4s var(--lp-ease); }
        .gv-step:last-child { border-right: none; }
        .gv-step:hover { background: var(--lp-paper-2); }
        .gv-step-num { font-family: 'Montserrat', sans-serif; font-size: .7rem; font-weight: 600; letter-spacing: .14em; color: var(--lp-muted); margin-bottom: .7rem; }
        .gv-step h4 { font-size: .98rem; font-weight: 600; color: var(--lp-ink); margin: 0 0 .5rem; }
        .gv-step p { font-size: .82rem; line-height: 1.7; color: var(--lp-muted); margin: 0; }

        /* History（年ごとのグリッド・カラー写真） */
        .gv-history-year { margin-bottom: 3rem; }
        .gv-history-year:last-child { margin-bottom: 0; }
        .gv-year-title { font-family: 'Montserrat', sans-serif; font-size: 1.15rem; font-weight: 600; letter-spacing: .1em; color: var(--lp-ink); margin: 0 0 1.4rem; padding-bottom: .7rem; border-bottom: 1px solid var(--lp-line); }
        .gv-history-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem; }
        .gv-history-card { display: block; text-decoration: none; color: inherit; border: 1px solid var(--lp-line); border-radius: var(--lp-radius); overflow: hidden; transition: border-color .35s var(--lp-ease), transform .35s var(--lp-ease); }
        .gv-history-card:hover { border-color: var(--lp-ink); transform: translateY(-4px); }
        .gv-history-card img { display: block; width: 100%; height: 190px; object-fit: cover; transition: transform .7s var(--lp-ease); }
        .gv-history-card:hover img { transform: scale(1.04); }
        .gv-history-body { padding: 1.1rem 1.2rem 1.3rem; }
        .gv-history-meta { display: flex; gap: .5rem; align-items: center; margin-bottom: .55rem; }
        .gv-history-meta span { font-family: 'Montserrat', sans-serif; font-size: .62rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--lp-muted); border: 1px solid var(--lp-line); border-radius: 999px; padding: .2rem .6rem; }
        .gv-history-team { font-size: 1rem; font-weight: 600; color: var(--lp-ink); margin: 0; }

        /* FAQ */
        .gv-faq { max-width: 760px; margin: 0 auto; border-top: 1px solid var(--lp-line); }
        .gv-faq-item { border-bottom: 1px solid var(--lp-line); padding: 1.5rem .2rem; }
        .gv-faq-q { display: flex; gap: .7rem; align-items: baseline; font-size: 1rem; font-weight: 600; color: var(--lp-ink); }
        .gv-faq-q .gv-q-mark { font-family: 'Montserrat', sans-serif; font-size: .85rem; color: var(--lp-muted); flex-shrink: 0; }
        .gv-faq-a { font-size: .92rem; line-height: 1.9; color: var(--lp-muted); margin: .8rem 0 0; padding-left: 1.5rem; }

        @media (max-width: 900px) {
            .gv-pillars, .gv-reasons { grid-template-columns: 1fr; }
            .gv-steps { grid-template-columns: repeat(2, 1fr); }
            .gv-step { border-right: 1px solid var(--lp-line); border-bottom: 1px solid var(--lp-line); }
            .gv-step:nth-child(2n) { border-right: none; }
            .gv-step:nth-last-child(-n+2):nth-child(2n+1), .gv-step:last-child { border-bottom: none; }
        }
        @media (max-width: 560px) {
            .gv-steps { grid-template-columns: 1fr; }
            .gv-step { border-right: none; }
            .gv-step:not(:last-child) { border-bottom: 1px solid var(--lp-line); }
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
                    <li><a href="index.php#blog" class="nav-link">Blog</a></li>
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

    <main class="gv-main">
        <section class="page-hero">
            <div class="page-hero-bg" style="background-image: url('gv_new.jpg?v=<?php echo @filemtime(__DIR__ . '/gv_new.jpg') ?: '1'; ?>');"></div>
            <div class="page-hero-overlay"></div>
            <div class="page-hero-inner">
                <p class="page-hero-eyebrow">Main Event</p>
                <h1 class="page-hero-title">GV (Global Village)</h1>
                <p class="page-hero-sub">国際NGO Habitat for Humanity が世界で展開する、海外住居建築ボランティア。開発途上国へ渡航し、現地で家づくりに参加します。</p>
            </div>
        </section>

        <nav class="page-crumb" aria-label="パンくず">
            <a href="index.php">Home</a><span>/</span><a href="index.php#activities">Activities</a><span>/</span>GV
        </nav>

        <!-- What is GV -->
        <section class="gv-section bg-light">
            <div class="container">
                <h2 class="gv-h2 fade-in"><span>GVとは？</span></h2>
                <div class="gv-vision fade-in">
                    <p class="gv-vision-label">VISION</p>
                    <p class="gv-vision-quote">"A world where everyone has a decent place to live"</p>
                    <p class="gv-vision-body">
                        「誰もがきちんとした場所で暮らせる世界」の実現を目指し、<br>
                        私たちは開発途上国へ渡航し、現地のホームオーナーと共に、<br>
                        安全で安心できる住居の建築支援を行います。
                    </p>
                </div>

                <p class="gv-pillars-intro fade-in">
                    GVの活動には、<b>Work</b>・<b>CA</b>・<b>SLEA</b> の3つの柱が存在します。
                </p>

                <div class="gv-pillars stagger-children">
                    <div class="gv-card fade-in">
                        <div class="gv-card-icon"><i class="fas fa-hammer"></i></div>
                        <h3>WORK</h3>
                        <p class="gv-card-sub">建築活動 (約5日間)</p>
                        <p>現地の専門職人の指導のもと、レンガ積みやセメント運搬などの作業に従事します。ホームオーナーと共に汗を流すことで、支援者・被支援者の枠を超えた信頼関係を築きます。</p>
                    </div>
                    <div class="gv-card fade-in">
                        <div class="gv-card-icon"><i class="fas fa-camera-retro"></i></div>
                        <h3>CA</h3>
                        <p class="gv-card-sub">Cultural Activity (約2日間)</p>
                        <p>現地の歴史的建造物や文化遺産を訪問し、その国の歴史や文化背景を肌で感じます。多角的な視点から支援国を理解する重要な機会です。</p>
                    </div>
                    <div class="gv-card fade-in">
                        <div class="gv-card-icon"><i class="fas fa-hands-helping"></i></div>
                        <h3>SLEA</h3>
                        <p class="gv-card-sub">Social Learning &amp; Exchange</p>
                        <p>現地コミュニティとの交流や、過去の支援先訪問、災害教育などを通じて、現地の社会課題を深く学び、私たちにできることを考えます。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3 Reasons -->
        <section class="gv-section">
            <div class="container">
                <h2 class="gv-h2 fade-in"><span>WHABITATのGVに参加する意義</span></h2>
                <div class="gv-reasons stagger-children">
                    <div class="gv-reason fade-in">
                        <div class="gv-reason-num">01</div>
                        <h3>一生ものの仲間</h3>
                        <p>多様なバックグラウンドを持つメンバーとの共同生活や、現地の人々との交流を通じて、表面的な付き合いではない、生涯続く深い信頼関係を築くことができます。</p>
                    </div>
                    <div class="gv-reason fade-in">
                        <div class="gv-reason-num">02</div>
                        <h3>忘れられない思い出</h3>
                        <p>観光旅行では決して味わえない、現地の人々の生活に深く入り込む体験は、貧困問題の現実を肌で感じる機会となり、一生の財産となる原体験をもたらします。</p>
                    </div>
                    <div class="gv-reason fade-in">
                        <div class="gv-reason-num">03</div>
                        <h3>圧倒的な成長</h3>
                        <p>異文化環境下での予期せぬ課題や、チームでの合意形成プロセスを通じて、実践的な課題解決能力やリーダーシップ、多角的な視点を養います。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section class="gv-section bg-light">
            <div class="container">
                <h2 class="gv-h2 fade-in"><span>GVの流れ</span></h2>
                <div class="gv-steps fade-in">
                    <div class="gv-step">
                        <div class="gv-step-num">01</div>
                        <h4>チーム結成</h4>
                        <p>メンバー選考・決定。</p>
                    </div>
                    <div class="gv-step">
                        <div class="gv-step-num">02</div>
                        <h4>事前MTG</h4>
                        <p>約12回のミーティング。現地の課題学習や安全管理。</p>
                    </div>
                    <div class="gv-step">
                        <div class="gv-step-num">03</div>
                        <h4>事前合宿</h4>
                        <p>チームビルディング。渡航に向けた最終確認。</p>
                    </div>
                    <div class="gv-step">
                        <div class="gv-step-num">04</div>
                        <h4>現地活動</h4>
                        <p>建築支援(Work)と文化学習(CA)。</p>
                    </div>
                    <div class="gv-step">
                        <div class="gv-step-num">05</div>
                        <h4>事後MTG</h4>
                        <p>活動の振り返り。学びの言語化。</p>
                    </div>
                    <div class="gv-step">
                        <div class="gv-step-num">06</div>
                        <h4>報告会</h4>
                        <p>活動成果の発表。支援者への報告。</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- History -->
        <section class="gv-section">
            <div class="container">
                <h2 class="gv-h2 fade-in"><span>WHABITAT GV History</span></h2>
                <p class="gv-sub fade-in">これまでに派遣されたチームの記録です。</p>

                <div class="gv-history-year fade-in">
                    <h3 class="gv-year-title">2020</h3>
                    <div class="gv-history-grid">
                        <a href="https://www.instagram.com/sakanto_gv" target="_blank" class="gv-history-card">
                            <img src="gv_sakanto.jpg" alt="さかんとGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>India</span></div>
                                <h4 class="gv-history-team">さかんとGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/habitat_paruparu_gv/" target="_blank" class="gv-history-card">
                            <img src="gv_paruparu.jpg" alt="ぱるぱるGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Vietnam</span></div>
                                <h4 class="gv-history-team">ぱるぱるGV</h4>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="gv-history-year fade-in">
                    <h3 class="gv-year-title">2023</h3>
                    <div class="gv-history-grid">
                        <a href="https://www.instagram.com/tantangood_whabitat2023" target="_blank" class="gv-history-card">
                            <img src="gv_tantangood.jpg" alt="たんたんぐGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Summer</span><span>Indonesia</span></div>
                                <h4 class="gv-history-team">たんたんぐGV</h4>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="gv-history-year fade-in">
                    <h3 class="gv-year-title">2024</h3>
                    <div class="gv-history-grid">
                        <a href="https://www.instagram.com/yupurumu_whabitat" target="_blank" class="gv-history-card">
                            <img src="gv_yupurumu.jpg" alt="ゆぷるむGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Cambodia</span></div>
                                <h4 class="gv-history-team">ゆぷるむGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/magkarawn_gv" target="_blank" class="gv-history-card">
                            <img src="gv_magkarawn.jpg" alt="マカランGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Philippines</span></div>
                                <h4 class="gv-history-team">マカランGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/sukairu.gv_whabitat" target="_blank" class="gv-history-card">
                            <img src="gv_sukairu.jpg" alt="すかいるGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Summer</span><span>Cambodia</span></div>
                                <h4 class="gv-history-team">すかいるGV</h4>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="gv-history-year fade-in">
                    <h3 class="gv-year-title">2025</h3>
                    <div class="gv-history-grid">
                        <a href="https://www.instagram.com/bangalgv" target="_blank" class="gv-history-card">
                            <img src="gv_bangal.jpg" alt="ばんがるGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Nepal</span></div>
                                <h4 class="gv-history-team">ばんがるGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/wabarumahgv" target="_blank" class="gv-history-card">
                            <img src="gv_wabarumah.jpg" alt="わばるまGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Indonesia</span></div>
                                <h4 class="gv-history-team">わばるまGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/dangan_gv" target="_blank" class="gv-history-card">
                            <img src="gv_dangan.jpg" alt="ダンガンGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Spring</span><span>Vietnam</span></div>
                                <h4 class="gv-history-team">ダンガンGV</h4>
                            </div>
                        </a>
                        <a href="https://www.instagram.com/erumela_gv" target="_blank" class="gv-history-card">
                            <img src="gv_erumela.jpg" alt="エルメラGV">
                            <div class="gv-history-body">
                                <div class="gv-history-meta"><span>Summer</span><span>Indonesia</span></div>
                                <h4 class="gv-history-team">エルメラGV</h4>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="gv-section bg-light">
            <div class="container">
                <h2 class="gv-h2 fade-in"><span>よくある質問 (FAQ)</span></h2>
                <div class="gv-faq fade-in">
                    <div class="gv-faq-item">
                        <div class="gv-faq-q"><span class="gv-q-mark">Q.</span> 英語が話せなくても大丈夫ですか？</div>
                        <p class="gv-faq-a">大丈夫です！チームメンバーで助け合います。大切なのは「伝えようとする気持ち」です。</p>
                    </div>
                    <div class="gv-faq-item">
                        <div class="gv-faq-q"><span class="gv-q-mark">Q.</span> 建築作業は未経験でも平気ですか？</div>
                        <p class="gv-faq-a">ほとんどの学生が未経験からスタートします。現地の大工さんが丁寧に教えてくれるので安心してください。ヘルメットや手袋などの安全装備もしっかり着用します。</p>
                    </div>
                    <div class="gv-faq-item">
                        <div class="gv-faq-q"><span class="gv-q-mark">Q.</span> 費用はどれくらいかかりますか？</div>
                        <p class="gv-faq-a">渡航先や航空券の価格によりますが、おおよそ20〜30万円程度です（航空券、滞在費、保険、寄付金含む）。</p>
                    </div>
                </div>
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
