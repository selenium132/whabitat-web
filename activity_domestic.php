<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>国内単発ボランティア | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <nav>
                <ul class="nav-list">
                    <li><a href="index.php#about" class="nav-link">About</a></li>
                    <li><a href="index.php#activities" class="nav-link">Activities</a></li>
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

    <main style="padding-top: 120px; padding-bottom: 50px;">
        <div class="container">
            <h1 class="section-title"><span>国内単発ボランティア</span></h1>
            
            <!-- Top Image (JV Style) -->
            <img src="domestic.jpg?v=<?php echo time(); ?>" alt="Domestic Volunteer" style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 4rem; box-shadow: var(--shadow-md);">

            <div class="domestic-intro">
                <h3 style="text-align: center; margin-bottom: 2rem; color: var(--primary-color); font-size: 1.5rem;">なぜ、ワビタットでボランティア？</h3>
                
                <div style="max-width: 680px; margin: 0 auto; line-height: 2; text-align: center;">
                    <p style="margin-bottom: 1.5rem;">
                        「自分の成長のため」「人の役に立ちたい」「大学生の今しかできないことがしたい」<br>
                        参加の理由は人それぞれですが、多くのメンバーが口を揃えて言うのは、
                    </p>
                    <p style="font-size: 1.3rem; font-weight: 700; color: var(--accent-orange); margin-bottom: 1.5rem;">
                        「ワビタットでのボランティアは楽しいから！」
                    </p>
                    <p style="margin-bottom: 3rem;">
                        真剣に全力で活動しながらも、仲間と笑顔で楽しめるのが魅力です。
                    </p>

                    <p style="text-align: justify; text-align-last: center;">
                        ワビタットの国内ボランティアは、大きく分けて<strong>「日帰り（1日）」</strong>と<strong>「短期合宿（1泊〜）」</strong>の2種類があります。（※長期の派遣ボランティアについては <a href="activity_gv.php" style="color: var(--accent-blue);">GV</a> や <a href="activity_jv.php" style="color: var(--accent-orange);">JV</a> のページをご覧ください）
                    </p>
                </div>
            </div>

            <!-- Annual Schedule (Modern Timeline) -->
            <div style="text-align: center; margin-bottom: 4rem;">
                <h3 style="display: inline-block; position: relative; font-size: 1.8rem; letter-spacing: 0.1em; color: var(--primary-color); margin: 0;">
                    年間スケジュール
                    <span style="position: absolute; bottom: -1.2rem; right: -3rem; font-size: 0.75rem; font-weight: normal; color: #888; white-space: nowrap;">※2026年度は未定</span>
                </h3>
            </div>
            <div class="timeline-modern">
                <!-- Feb -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">02 <span style="font-size: 0.9rem; font-weight: normal;">Feb</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>西和賀<br>援農<br>ゴミ拾い</p>
                        </div>
                    </div>
                </div>
                <!-- Mar -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">03 <span style="font-size: 0.9rem; font-weight: normal;">Mar</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>援農<br>武家屋敷<br>丹波山</p>
                        </div>
                    </div>
                </div>
                <!-- Apr -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">04 <span style="font-size: 0.9rem; font-weight: normal;">Apr</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>新歓ゴミ拾い</p>
                        </div>
                    </div>
                </div>
                <!-- May -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">05 <span style="font-size: 0.9rem; font-weight: normal;">May</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>ビーチクリーン<br>援農</p>
                        </div>
                    </div>
                </div>
                <!-- Jun -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">06 <span style="font-size: 0.9rem; font-weight: normal;">Jun</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>援農</p>
                        </div>
                    </div>
                </div>
                <!-- Aug -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">08 <span style="font-size: 0.9rem; font-weight: normal;">Aug</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>ゴミ拾い<br>丹波山</p>
                        </div>
                    </div>
                </div>
                <!-- Sep -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">09 <span style="font-size: 0.9rem; font-weight: normal;">Sep</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>寺泊<br>お神輿手伝い<br>援農</p>
                        </div>
                    </div>
                </div>
                <!-- Oct -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">10 <span style="font-size: 0.9rem; font-weight: normal;">Oct</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>援農<br>防災訓練</p>
                        </div>
                    </div>
                </div>
                <!-- Nov -->
                <div class="timeline-row">
                    <div class="timeline-time">
                        <span class="timeline-month">11 <span style="font-size: 0.9rem; font-weight: normal;">Nov</span></span>
                    </div>
                    <div class="timeline-content-side">
                        <div class="timeline-detail">
                            <p>ゴミ拾い</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. Trash Picking & Beach Clean (MERGED) -->
            <section class="domestic-section">
                <h2 class="domestic-section-title">日帰りボランティア</h2>
                <p class="section-desc">最も参加しやすく、月に1〜2回程度行われています。</p>
                
                <h3 class="domestic-subsection-title trash">ゴミ拾い・ビーチクリーン</h3>
                <div class="domestic-grid-wide" style="margin-bottom: 3rem;">
                    <div class="domestic-card domestic-card-horizontal">
                        <img src="domestic_trash_picking.jpg" alt="ゴミ拾い・ビーチクリーン" style="height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag trash">Trash Picking & Beach Clean</span>
                            <h4>ゴミ拾い・ビーチクリーン</h4>
                            <p>
                                渋谷や早稲田周辺で年2回程度、ビンゴや謎解きを取り入れた楽しいゴミ拾いを行っています。<br>
                                さらに5月〜6月頃には、神奈川県や千葉県などの海岸へ遠征して<strong>ビーチクリーン</strong>も実施！海風を感じながら、他のチームやサークルと協力して活動する人気イベントです。
                            </p>
                        </div>
                    </div>
                </div>

                <h3 class="domestic-subsection-title agri">農業ボランティア (援農)</h3>
                <div class="domestic-grid" style="margin-bottom: 3rem;">
                    <div class="domestic-card">
                        <img src="domestic_iijima_farm.jpg" alt="飯島農園" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag agri">Chiba</span>
                            <h4>北習志野</h4>
                            <p>千葉県北習志野にある無農薬野菜を作っている農園でのお手伝い。お味噌の仕込みを行ったり、美味しいお野菜をいただいたりと、食と農に触れる貴重な体験ができます。</p>
                        </div>
                    </div>
                    <div class="domestic-card">
                        <img src="domestic_earth_farm.jpg" alt="東京地球農園" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag agri">Tokyo</span>
                            <h4>あきる野</h4>
                            <p>自然豊かな東京都あきる野市での農業体験。様々なお野菜に加え、栗や鶏のお世話などができます。お昼にはうどんやカレーをいただけたりと、心も体も温まる活動です。</p>
                        </div>
                    </div>
                </div>

                <h3 class="domestic-subsection-title community">地域連携・その他</h3>
                <div class="domestic-grid">
                    <div class="domestic-card">
                        <img src="domestic_omikoshi.jpg" alt="お神輿・お祭り手伝い" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag community">Festival</span>
                            <h4>お神輿・お祭り手伝い</h4>
                            <p>早稲田周辺の町内会のお祭りに参加し、お神輿を担ぐお手伝いをします。幅広い年齢層の地域の方々と交流できる貴重な機会です。</p>
                        </div>
                    </div>
                    <div class="domestic-card">
                        <img src="domestic_bosai.jpg" alt="地域防災訓練" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag community">Bosai</span>
                            <h4>地域防災訓練</h4>
                            <p>町内会の防災訓練に参加し、地域の方々と共に防災意識を高めます。炊き出し（芋煮など）のお手伝いをすることもあり、地域との繋がりを深めます。</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Short-term Camps -->
            <section class="domestic-section">
                <h2 class="domestic-section-title">短期合宿ボランティア</h2>
                <p class="section-desc">1泊〜3泊4日程度。事前・事後の活動が少ないため、気軽に参加できるのが特徴です。</p>
                <div class="domestic-grid">
                    <div class="domestic-card">
                        <img src="domestic_teradomari.jpg" alt="寺泊 (新潟県長岡市)" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover; transform: scale(1.05); object-position: 60% 50%;">
                        <div class="domestic-content">
                            <span class="domestic-tag camp">Niigata</span>
                            <h4>寺泊</h4>
                            <p>「求草里山再生プロジェクト」に参加し、竹林整備や地域観光の支援を行います。竹や木を刈って神社周辺を整備したり、地域の方とBBQで交流を深めます。</p>
                        </div>
                    </div>
                    <div class="domestic-card">
                        <img src="domestic_nishiwaga.jpg" alt="西和賀（岩手県）" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover; object-position: right bottom;">
                        <div class="domestic-content">
                            <span class="domestic-tag camp">Iwate</span>
                            <h4>西和賀</h4>
                            <p>豪雪地帯である岩手県西和賀町で、2月の「雪あかり」イベントのお手伝いをします。雪国ならではの文化に触れ、地域の方々と深く交流できる活動です。</p>
                        </div>
                    </div>
                    <div class="domestic-card">
                        <img src="domestic_bukeyashiki.jpg" alt="武家屋敷 (東京都日の出町)" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag camp">Tokyo</span>
                            <h4>武家屋敷</h4>
                            <p>歴史ある武家屋敷で、引越しのお手伝いや、忍者道場の広報支援などを行いました。援農とセットで行くことが多く、夜は温泉や鍋を囲んでの交流も！</p>
                        </div>
                    </div>
                    <div class="domestic-card">
                        <img src="domestic_tabayama.jpg" alt="丹波山村 (山梨県)" style="width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover;">
                        <div class="domestic-content">
                            <span class="domestic-tag camp">Yamanashi</span>
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
</body>
</html>
