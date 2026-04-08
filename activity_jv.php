<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JV (Japan Village) | WHABITAT</title>
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

    <main style="padding-top: 120px; padding-bottom: 50px;">
        <div class="container">
            <h1 class="section-title"><span>JV (Japan Village)</span></h1>
            <div class="activity-detail-content">
                <img src="jv.jpg?v=<?php echo time(); ?>" alt="JV" style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 4rem; box-shadow: var(--shadow-md);">
                
                <!-- Intro -->
                <div class="content-section text-center">
                    <p class="lead-text">
                        コロナ禍で海外への渡航が困難になった際、<br>
                        「GVに代わる大きなイベントを」という想いから生まれた、国内派遣型ボランティア。<br>
                        日本全国の豊かな自然と温かい人々に出会う、夏休みの1週間。
                    </p>
                </div>

                <!-- Overview -->
                <div class="content-section">
                    <h2 class="section-title"><span>JVとは？</span></h2>
                    <div class="three-pillars">
                        <div class="pillar-card">
                            <i class="fas fa-users pillar-icon"></i>
                            <div class="sub-label">TEAM</div>
                            <h3>チーム構成</h3>
                            <p>各チームは1〜3年生のメンバー8〜11人前後で構成。学年を超えた深い絆が生まれます。</p>
                        </div>
                        <div class="pillar-card">
                            <i class="fas fa-map-marked-alt pillar-icon"></i>
                            <div class="sub-label">LOCATION</div>
                            <h3>派遣先</h3>
                            <p>北は青森から南は九州まで。普段は訪れることのない、日本の原風景が残る地域へ。</p>
                        </div>
                        <div class="pillar-card">
                            <i class="fas fa-calendar-alt pillar-icon"></i>
                            <div class="sub-label">TIMING</div>
                            <h3>派遣時期</h3>
                            <p>夏休みの1週間。日常を離れ、地域の方々と共に汗を流し、語り合う特別な時間です。</p>
                        </div>
                    </div>
                </div>

                <!-- Activities -->
                <div class="content-section bg-light p-4 rounded">
                    <h2 class="section-title"><span>活動内容</span></h2>
                    <p class="text-center mb-4">派遣先によって内容は多岐にわたりますが、主に3つの柱があります。</p>
                    
                    <div class="reason-grid" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 2rem;">
                        <div class="reason-item" style="flex: 1 1 300px; max-width: 400px;">
                            <h3 style="color: var(--accent-green); margin-bottom: 1rem;"><i class="fas fa-seedling"></i> 農作業</h3>
                            <p>地域の名産品に関わる作業を通じて、農家さんの想いや苦労を肌で感じます。</p>
                            <ul style="text-align: left; margin-top: 1rem; padding-left: 1.5rem; color: var(--text-light);">
                                <li>らっきょう（畑の整備・根付け）</li>
                                <li>すだち・きくらげ（収穫）</li>
                                <li>赤かぶ（作付け）など</li>
                            </ul>
                        </div>
                        <div class="reason-item" style="flex: 1 1 300px; max-width: 400px;">
                            <h3 style="color: var(--accent-green); margin-bottom: 1rem;"><i class="fas fa-broom"></i> 環境整備</h3>
                            <p>過疎化が進む地域での保全活動。地域の美しい景観や生活環境を守ります。</p>
                            <ul style="text-align: left; margin-top: 1rem; padding-left: 1.5rem; color: var(--text-light);">
                                <li>森林保全（間伐作業）</li>
                                <li>空き家対策（掃除・遺品整理）</li>
                                <li>設備点検・地域清掃など</li>
                            </ul>
                        </div>
                        <div class="reason-item" style="flex: 1 1 300px; max-width: 400px;">
                            <h3 style="color: var(--accent-green); margin-bottom: 1rem;"><i class="fas fa-handshake"></i> 動物・文化交流</h3>
                            <p>作業だけでなく、その土地ならではの文化や生き物との触れ合いも大切にしています。</p>
                            <ul style="text-align: left; margin-top: 1rem; padding-left: 1.5rem; color: var(--text-light);">
                                <li>動物のお世話（牛・アルパカ・ヤギ）</li>
                                <li>伝統文化体験（合掌造り・伝統楽器）</li>
                                <li>地域学習（資料館訪問・里山保全）</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Merits -->
                <div class="content-section">
                    <h2 class="section-title"><span>JVに参加するメリット</span></h2>
                    <div class="reason-grid centered-grid">
                        <div class="reason-item">
                            <div class="reason-num">01</div>
                            <h3>参加のしやすさ</h3>
                            <p>海外（GV）に比べてハードルが低く、費用も抑えられるため、ボランティア初心者でも安心して参加できます。GVへのステップアップとしても最適です。</p>
                        </div>
                        <div class="reason-item">
                            <div class="reason-num">02</div>
                            <h3>豊かな環境と出会い</h3>
                            <p>普段行けない自然豊かな地域で、地元の方々やメンバーと濃い時間を過ごします。デジタルデトックスにもなり、心身ともにリフレッシュできます。</p>
                        </div>
                        <div class="reason-item">
                            <div class="reason-num">03</div>
                            <h3>自己成長と生活</h3>
                            <p>規則正しい生活と美味しいご飯。集団行動を通じて自分自身を見つめ直し、当たり前の生活のありがたさを再確認する機会になります。</p>
                        </div>
                    </div>
                </div>

                <!-- Flow -->
                <div class="content-section">
                    <h2 class="section-title"><span>JVの流れ</span></h2>
                    <div class="timeline-horizontal">
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>チーム結成</h4>
                                <p>メンバー決定。</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>事前MTG</h4>
                                <p>事前学習・レクリエーション。</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>派遣</h4>
                                <p>夏休みの1週間。<br>現地での活動。</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>事後MTG</h4>
                                <p>振り返り・レクリエーション。</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="timeline-point"></div>
                            <div class="timeline-text">
                                <h4>報告会</h4>
                                <p>活動の集大成。<br>学びの共有。</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History -->
                <div class="content-section">
                    <h2 class="section-title"><span>WHABITAT JV History</span></h2>
                    <p class="text-center mb-5">これまでに派遣されたチームの記録です。</p>
                    
                    <div class="history-timeline-container">
                        <!-- 2025 -->
                        <div class="history-year-group">
                            <h3 class="history-year-title">2025 Summer</h3>
                            <div class="history-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                                <!-- Misara-chi JV -->
                                <a href="https://www.instagram.com/oi.jv2025" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_misarachi.jpg" alt="Misara-chi JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Tokushima</span>
                                        <span class="history-country">大井</span>
                                        <h4 class="history-team">みさらーちJV</h4>
                                    </div>
                                </a>
                                <!-- Teyanoppe JV -->
                                <a href="https://www.instagram.com/teyanope_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_teyanoppe.jpg" alt="Teyanoppe JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Nagano</span>
                                        <span class="history-country">立屋</span>
                                        <h4 class="history-team">てやのっぺJV</h4>
                                    </div>
                                </a>
                                <!-- PepePON! JV -->
                                <a href="https://www.instagram.com/pepepon_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_pepepon.jpg" alt="PepePON! JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Tochigi</span>
                                        <span class="history-country">益子</span>
                                        <h4 class="history-team">ぺぺPON！JV</h4>
                                    </div>
                                </a>
                                <!-- Japparedan JV -->
                                <a href="https://www.instagram.com/pepepon_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_japparedan.jpg" alt="Japparedan JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Aomori</span>
                                        <span class="history-country">白神</span>
                                        <h4 class="history-team">じゃっぱーれ団</h4>
                                    </div>
                                </a>
                                <!-- Menkepokko JV -->
                                <a href="https://www.instagram.com/jyapparedan_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_menkepokko.jpg" alt="Menkepokko JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Akita</span>
                                        <span class="history-country">仙北</span>
                                        <h4 class="history-team">めんけぽっこJV</h4>
                                    </div>
                                </a>
                                <!-- Fukudeppora JV -->
                                <a href="https://www.instagram.com/fukudeppo" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_fukudeppora.jpg" alt="Fukudeppora JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Fukushima</span>
                                        <span class="history-country">田人</span>
                                        <h4 class="history-team">ふくでっぽらJV</h4>
                                    </div>
                                </a>
                                <!-- Kamaquran JV -->
                                <a href="https://www.instagram.com/kamaqran_jv2025" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_kamaquran.jpg" alt="Kamaquran JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Nagano</span>
                                        <span class="history-country">真木</span>
                                        <h4 class="history-team">かまきゅらんJV</h4>
                                    </div>
                                </a>
                                <!-- Gyabamiccha JV -->
                                <a href="https://www.instagram.com/kamaqran_jv2025" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_gyabamiccha.jpg" alt="Gyabamiccha JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Fukuoka</span>
                                        <span class="history-country">黒木</span>
                                        <h4 class="history-team">ぎゃばみっちゃJV</h4>
                                    </div>
                                </a>
                                <!-- Konorei48 JV -->
                                <a href="https://www.instagram.com/konorei48jv2025" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_konorei48.jpg" alt="Konorei48 JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Mie</span>
                                        <span class="history-country">赤目</span>
                                        <h4 class="history-team">このれい48JV</h4>
                                    </div>
                                </a>
                                <!-- Nacha JV -->
                                <a href="https://www.instagram.com/konorei48jv2025" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_nacha.jpg" alt="Nacha JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Toyama</span>
                                        <span class="history-country">五箇山</span>
                                        <h4 class="history-team">なちゃJV</h4>
                                    </div>
                                </a>
                                <!-- Tsumugururin JV -->
                                <a href="https://www.instagram.com/tsumugururin_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_tsumugururin.png" alt="Tsumugururin JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Niigata</span>
                                        <span class="history-country">山古志</span>
                                        <h4 class="history-team">つむぐるりんJV</h4>
                                    </div>
                                </a>
                                <!-- Rimochun JV -->
                                <a href="https://www.instagram.com/rimochunnn_jv" target="_blank" class="history-card">
                                    <div style="padding: 1rem; background: #fff;">
                                        <img src="jv_rimochun.jpg" alt="Rimochun JV" style="width: 100%; aspect-ratio: 1/1; object-fit: contain; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    </div>
                                    <div class="history-info">
                                        <span class="history-season">Shiga</span>
                                        <span class="history-country">高島</span>
                                        <h4 class="history-team">りもちゅんJV</h4>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
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
