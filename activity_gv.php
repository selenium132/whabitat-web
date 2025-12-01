<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GV (Global Village) | WHABITAT</title>
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
                        <a href="https://www.instagram.com/whabinsta?igsh=MXIybDBlMjFhZWVndA==" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
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

    <!-- Hero Section -->
    <div class="page-hero" style="background-image: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('gv_new.jpg?v=<?php echo time(); ?>');">
        <div class="container">
            <h1 class="page-title">Global Village (GV)</h1>
            <p class="page-subtitle">家を建て、希望を築く10日間。<br>世界と出会い、新しい自分と出会う旅へ。</p>
        </div>
    </div>

    <main>
        <div class="container">
            <!-- Intro -->
            <div class="content-section text-center">
                <p class="lead-text">
                    GV（Global Village）は、Habitat for Humanityが世界中で展開する<br>
                    <strong>「海外住居建築ボランティア」</strong>プログラムです。<br>
                    <br>
                    ただ家を建てるだけではありません。<br>
                    <strong>Build（建築）</strong>、<strong>Cultural Activity（観光）</strong>、<strong>Social Learning（学び）</strong>。<br>
                    この3つの要素が詰まった、他では味わえない濃密な体験があなたを待っています。
                </p>
            </div>

            <!-- What is GV -->
            <div class="content-section">
                <h2 class="section-title"><span>GVとは？</span></h2>
                <div class="card bg-light">
                    <p class="text-center mb-2" style="font-weight: bold; color: var(--primary-color);">VISION</p>
                    <h3 class="text-center mb-4" style="font-family: 'Montserrat', sans-serif;">"A world where everyone has a decent place to live"</h3>
                    <p class="text-center">
                        「誰もがきちんとした場所で暮らせる世界」を目指し、<br>
                        私たちは開発途上国へ渡航し、現地のホームオーナーさんと共に家を建てます。
                    </p>
                </div>

                <div class="three-pillars mt-5">
                    <div class="pillar-card">
                        <div class="pillar-icon"><i class="fas fa-hammer"></i></div>
                        <h3>Build</h3>
                        <p class="sub-label">建築活動 (約5日間)</p>
                        <p>現地の家族や大工さんと共に汗を流します。レンガ積み、セメント運び、壁塗りなど。言葉が通じなくても、共に作業することで心がつながります。</p>
                    </div>
                    <div class="pillar-card">
                        <div class="pillar-icon"><i class="fas fa-camera-retro"></i></div>
                        <h3>CA</h3>
                        <p class="sub-label">Cultural Activity (約2日間)</p>
                        <p>アンコールワットやボロブドゥール遺跡など、その国の文化遺産や観光地を巡ります。国の歴史や文化を肌で感じる大切な時間です。</p>
                    </div>
                    <div class="pillar-card">
                        <div class="pillar-icon"><i class="fas fa-hands-helping"></i></div>
                        <h3>SLEA</h3>
                        <p class="sub-label">Social Learning & Exchange</p>
                        <p>現地コミュニティとの交流会や、過去に建てられた家の訪問、災害教育などを通じて、現地の社会課題について深く学びます。</p>
                    </div>
                </div>
            </div>

            <!-- 3 Reasons -->
            <div class="content-section bg-accent-light p-4 rounded">
                <h2 class="section-title"><span>WhabitatのGVに参加する3つの理由</span></h2>
                <div class="reason-grid">
                    <div class="reason-item">
                        <div class="reason-num">01</div>
                        <h3>一生ものの仲間</h3>
                        <p>共に汗を流し、寝食を共にすることで生まれる絆は特別です。帰国後も続く、深い友情がここで生まれます。</p>
                    </div>
                    <div class="reason-item">
                        <div class="reason-num">02</div>
                        <h3>圧倒的な成長</h3>
                        <p>チームビルディング、異文化適応、リーダーシップ。座学では学べない「生きた経験」があなたを成長させます。</p>
                    </div>
                    <div class="reason-item">
                        <div class="reason-num">03</div>
                        <h3>忘れられない思い出</h3>
                        <p>ただの観光旅行では絶対に出会えない、現地の人々の温かさや笑顔。その感動は一生の宝物になります。</p>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="content-section">
                <h2 class="section-title"><span>参加までの流れ</span></h2>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Step 1: 説明会・エントリー</h4>
                            <p>まずは説明会に参加して、GVの魅力を知ってください。その後、エントリーシートを提出します。</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Step 2: チーム結成・国内活動</h4>
                            <p>チームが決まったら、出発までに約12回のミーティングを行います。事前学習や募金活動、チームビルディング合宿などを通して準備を整えます。</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Step 3: 出発！ (GV本番)</h4>
                            <p>いよいよ渡航！10〜14日間の現地活動。最高の仲間と最高の経験を。</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Step 4: 帰国・報告会</h4>
                            <p>帰国後は事後研修を行い、活動の振り返りをします。5月頃には報告会も開催します。</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History -->
            <div class="content-section">
                <h2 class="section-title"><span>Whabitat GV History</span></h2>
                <p class="text-center mb-4">これまでに派遣されたチームの記録です。</p>
                <div class="history-grid">
                    <!-- Example Placeholders -->
                    <div class="history-card">
                        <div class="history-img-placeholder">Photo</div>
                        <div class="history-info">
                            <span class="history-year">2024 Summer</span>
                            <span class="history-country">Indonesia</span>
                            <h4 class="history-team">Team Example</h4>
                        </div>
                    </div>
                    <div class="history-card">
                        <div class="history-img-placeholder">Photo</div>
                        <div class="history-info">
                            <span class="history-year">2024 Spring</span>
                            <span class="history-country">Cambodia</span>
                            <h4 class="history-team">Team Example</h4>
                        </div>
                    </div>
                    <div class="history-card">
                        <div class="history-img-placeholder">Photo</div>
                        <div class="history-info">
                            <span class="history-year">2023 Summer</span>
                            <span class="history-country">Vietnam</span>
                            <h4 class="history-team">Team Example</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div class="content-section">
                <h2 class="section-title"><span>よくある質問 (FAQ)</span></h2>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-q"><i class="far fa-question-circle"></i> 英語が話せなくても大丈夫ですか？</div>
                        <div class="faq-a">
                            大丈夫です！チームメンバーで助け合いますし、現地には通訳スタッフもいます。大切なのは「伝えようとする気持ち」です。
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-q"><i class="far fa-question-circle"></i> 建築作業は未経験でも平気ですか？</div>
                        <div class="faq-a">
                            ほとんどの学生が未経験からスタートします。現地の大工さんが丁寧に教えてくれるので安心してください。ヘルメットや手袋などの安全装備もしっかり着用します。
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-q"><i class="far fa-question-circle"></i> 費用はどれくらいかかりますか？</div>
                        <div class="faq-a">
                            渡航先や航空券の価格によりますが、おおよそ20〜30万円程度です（航空券、滞在費、保険、寄付金含む）。積み立てや分割払いについても相談に乗ります。
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 4rem;">
                <a href="index.php#contact" class="btn-submit" style="display: inline-block; width: auto; padding: 1rem 3rem; text-decoration: none;">
                    GVに参加したい・話を聞きたい
                </a>
            </div>

        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="https://x.com/whabitat?s=21" target="_blank">X (Twitter)</a>
                <a href="https://www.instagram.com/whabinsta?igsh=MXIybDBlMjFhZWVndA==" target="_blank">Instagram</a>
                <a href="index.php#contact">Contact</a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.8rem; color: #ccc;">&copy; 2025 WHABITAT Waseda University Chapter. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
