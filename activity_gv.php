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

    <main style="padding-top: 120px; padding-bottom: 50px;">
        <div class="container">
            <h1 class="section-title"><span>GV (Global Village)</span></h1>
            <div class="activity-detail-content">
                <img src="gv_new.jpg?v=<?php echo time(); ?>" alt="GV" style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 2rem; box-shadow: var(--shadow-md);">
                
                <!-- Intro -->
                <div class="content-section text-center">
                    <p class="lead-text">
                        GV（Global Village）は、国際NGO Habitat for Humanityが世界中で展開する<br>
                        <strong>「海外住居建築ボランティア」</strong>プログラムです。<br>
                        <br>
                        WHABITATでは、単なる建築支援にとどまらず、<br>
                        <strong>Build（建築）</strong>、<strong>Cultural Activity（文化交流）</strong>、<strong>Social Learning（社会学習）</strong>の<br>
                        3つの柱を通じて、貧困住居問題への理解を深め、<br>
                        持続可能な支援のあり方を模索する活動を行っています。
                    </p>
                </div>

                <!-- What is GV -->
                <div class="content-section">
                    <h2 class="section-title"><span>GVとは？</span></h2>
                    <div class="card bg-light">
                        <p class="text-center mb-2" style="font-weight: bold; color: var(--primary-color);">VISION</p>
                        <h3 class="text-center mb-4" style="font-family: 'Montserrat', sans-serif;">"A world where everyone has a decent place to live"</h3>
                        <p class="text-center">
                            「誰もがきちんとした場所で暮らせる世界」の実現を目指し、<br>
                            私たちは開発途上国へ渡航し、現地のホームオーナーと共に、<br>
                            安全で安心できる住居の建築支援を行います。
                        </p>
                    </div>

                    <div class="three-pillars mt-5">
                        <div class="pillar-card">
                            <div class="pillar-icon"><i class="fas fa-hammer"></i></div>
                            <h3>Build</h3>
                            <p class="sub-label">建築活動 (約5日間)</p>
                            <p>現地の専門職人の指導のもと、レンガ積みやセメント運搬などの作業に従事します。ホームオーナーと共に汗を流すことで、支援者・被支援者の枠を超えた信頼関係を築きます。</p>
                        </div>
                        <div class="pillar-card">
                            <div class="pillar-icon"><i class="fas fa-camera-retro"></i></div>
                            <h3>CA</h3>
                            <p class="sub-label">Cultural Activity (約2日間)</p>
                            <p>現地の歴史的建造物や文化遺産を訪問し、その国の歴史や文化背景を肌で感じます。多角的な視点から支援国を理解する重要な機会です。</p>
                        </div>
                        <div class="pillar-card">
                            <div class="pillar-icon"><i class="fas fa-hands-helping"></i></div>
                            <h3>SLEA</h3>
                            <p class="sub-label">Social Learning & Exchange</p>
                            <p>現地コミュニティとの交流や、過去の支援先訪問、災害教育などを通じて、現地の社会課題を深く学び、私たちにできることを考えます。</p>
                        </div>
                    </div>
                </div>

                <!-- 3 Reasons -->
                <div class="content-section bg-accent-light p-4 rounded">
                    <h2 class="section-title"><span>WHABITATのGVに参加する意義</span></h2>
                    <div class="reason-grid centered-grid">
                        <div class="reason-item">
                            <div class="reason-num">01</div>
                            <h3>一生ものの仲間</h3>
                            <p>多様なバックグラウンドを持つメンバーとの共同生活や、現地の人々との交流を通じて、表面的な付き合いではない、生涯続く深い信頼関係を築くことができます。</p>
                        </div>
                        <div class="reason-item">
                            <div class="reason-num">02</div>
                            <h3>忘れられない思い出</h3>
                            <p>観光旅行では決して味わえない、現地の人々の生活に深く入り込む体験は、貧困問題の現実を肌で感じる機会となり、一生の財産となる原体験をもたらします。</p>
                        </div>
                        <div class="reason-item">
                            <div class="reason-num">03</div>
                            <h3>圧倒的な成長</h3>
                            <p>異文化環境下での予期せぬ課題や、チームでの合意形成プロセスを通じて、実践的な課題解決能力やリーダーシップ、多角的な視点を養います。</p>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="content-section">
                    <h2 class="section-title"><span>GVの流れ</span></h2>
                    <div class="timeline-horizontal">
                        <!-- Step 1 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>チーム結成</h4>
                                <p>メンバー選考・決定。</p>
                            </div>
                        </div>
                        <!-- Step 2 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>事前MTG</h4>
                                <p>約12回のミーティング。<br>現地の課題学習や安全管理。</p>
                            </div>
                        </div>
                        <!-- Step 3 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>事前合宿</h4>
                                <p>チームビルディング。<br>渡航に向けた最終確認。</p>
                            </div>
                        </div>
                        <!-- Step 4 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>現地活動</h4>
                                <p>建築支援(Work)と<br>文化学習(CA)。</p>
                            </div>
                        </div>
                        <!-- Step 5 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>事後MTG</h4>
                                <p>活動の振り返り。<br>学びの言語化。</p>
                            </div>
                        </div>
                        <!-- Step 6 -->
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>報告会</h4>
                                <p>活動成果の発表。<br>支援者への報告。</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History -->
                <div class="content-section">
                    <h2 class="section-title"><span>WHABITAT GV History</span></h2>
                    <p class="text-center mb-5">これまでに派遣されたチームの記録です。</p>
                    
                    <div class="history-timeline-container">
                        <!-- 2020 -->
                        <div class="history-year-group">
                            <h3 class="history-year-title">2020</h3>
                            <div class="history-grid">
                                <!-- Sakanto GV -->
                                <a href="https://www.instagram.com/sakanto_gv?igsh=OWx5MTNlZDZyaXB0" target="_blank" class="history-card">
                                    <img src="gv_sakanto.jpg" alt="Sakanto GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">India</span>
                                        <h4 class="history-team">さかんとGV</h4>
                                    </div>
                                </a>
                                <!-- Paruparu GV -->
                                <a href="https://www.instagram.com/habitat_paruparu_gv/" target="_blank" class="history-card">
                                    <img src="gv_paruparu.jpg" alt="Paruparu GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Vietnam</span>
                                        <h4 class="history-team">ぱるぱるGV</h4>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 2023 -->
                        <div class="history-year-group">
                            <h3 class="history-year-title">2023</h3>
                            <div class="history-grid">
                                <!-- Tantangood GV -->
                                <a href="https://www.instagram.com/tantangood_whabitat2023?igsh=MTNqaHZtdDJueGhqMg%3D%3D" target="_blank" class="history-card">
                                    <img src="gv_tantangood.jpg" alt="Tantangood GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Summer</span>
                                        <span class="history-country">Indonesia</span>
                                        <h4 class="history-team">たんたんぐGV</h4>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 2024 -->
                        <div class="history-year-group">
                            <h3 class="history-year-title">2024</h3>
                            <div class="history-grid">
                                <!-- Yupurumu GV -->
                                <a href="https://www.instagram.com/yupurumu_whabitat?igsh=MWF3MzMxdjNoMHlkMg%3D%3D" target="_blank" class="history-card">
                                    <img src="gv_yupurumu.jpg" alt="Yupurumu GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Cambodia</span>
                                        <h4 class="history-team">ゆぷるむGV</h4>
                                    </div>
                                </a>
                                <!-- Magkarawn GV -->
                                <a href="https://www.instagram.com/magkarawn_gv?igsh=MWZpejRraHRuaTVtbw%3D%3D" target="_blank" class="history-card">
                                    <img src="gv_magkarawn.jpg" alt="Magkarawn GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Philippines</span>
                                        <h4 class="history-team">マカランGV</h4>
                                    </div>
                                </a>
                                <!-- Sukairu GV -->
                                <a href="https://www.instagram.com/sukairu.gv_whabitat?igsh=bjRjMnhpYXE0M3hz" target="_blank" class="history-card">
                                    <img src="gv_sukairu.jpg" alt="Sukairu GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Summer</span>
                                        <span class="history-country">Cambodia</span>
                                        <h4 class="history-team">すかいるGV</h4>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- 2025 -->
                        <div class="history-year-group">
                            <h3 class="history-year-title">2025</h3>
                            <div class="history-grid">
                                <!-- Bangal GV -->
                                <a href="https://www.instagram.com/bangalgv?igsh=MXc1aWhqOGVuMjZubQ%3D%3D" target="_blank" class="history-card">
                                    <img src="gv_bangal.jpg" alt="Bangal GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Nepal</span>
                                        <h4 class="history-team">ばんがるGV</h4>
                                    </div>
                                </a>
                                <!-- Wabarumah GV -->
                                <a href="https://www.instagram.com/wabarumahgv?igsh=MnBweGFrNGV3NHI0" target="_blank" class="history-card">
                                    <img src="gv_wabarumah.jpg" alt="Wabarumah GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Indonesia</span>
                                        <h4 class="history-team">わばるまGV</h4>
                                    </div>
                                </a>
                                <!-- Dangan GV -->
                                <a href="https://www.instagram.com/dangan_gv?igsh=eGh5OHFnbHdqOXp0" target="_blank" class="history-card">
                                    <img src="gv_dangan.jpg" alt="Dangan GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Spring</span>
                                        <span class="history-country">Vietnam</span>
                                        <h4 class="history-team">ダンガンGV</h4>
                                    </div>
                                </a>
                                <!-- Erumela GV -->
                                <a href="https://www.instagram.com/erumela_gv?igsh=MWQwbjcybWR6YXN0cg%3D%3D" target="_blank" class="history-card">
                                    <img src="gv_erumela.jpg" alt="Erumela GV" style="width: 100%; height: 200px; object-fit: cover;">
                                    <div class="history-info">
                                        <span class="history-season">Summer</span>
                                        <span class="history-country">Indonesia</span>
                                        <h4 class="history-team">エルメラGV</h4>
                                    </div>
                                </a>
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
                                大丈夫です！チームメンバーで助け合います。大切なのは「伝えようとする気持ち」です。
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
                                渡航先や航空券の価格によりますが、おおよそ20〜30万円程度です（航空券、滞在費、保険、寄付金含む）。
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 3rem;">
                    <a href="index.php#activities" class="btn-secondary">一覧に戻る</a>
                </div>
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
