<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全体ミーティング (MTG) | WHABITAT</title>
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
            <h1 class="section-title"><span>全体ミーティング (MTG)</span></h1>
            <div class="activity-detail-content">
                <img src="daily.jpg?v=<?php echo time(); ?>" alt="MTG" style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 2rem; box-shadow: var(--shadow-md);">
                
                <div class="card">
                    <h3>週に一度の交流と学びの場</h3>
                    <p style="line-height: 1.8; margin-bottom: 1.5rem;">
                        毎週水曜日の6限後に集まり、全体ミーティングを行っています。<br>
                        アイスブレイクで学年を超えた交流を深めたり、貧困問題や環境問題、ボランティアの意義について学ぶワークショップを行ったりしています。
                    </p>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--accent-blue);">日時:</strong> 毎週水曜 6限（19:00〜）<br>
                        <strong style="color: var(--text-color);">場所:</strong> 早稲田キャンパス15号館、または奉仕園
                    </div>
                </div>
                </div>

                <!-- MTG History -->
                <section style="margin-top: 5rem;">
                    <h2 class="section-title"><span>MTG HISTORY</span></h2>
                    
                    <div class="history-year-group">
                        <h3 style="font-size: 1.5rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; margin-bottom: 2rem; color: var(--primary-color);">2025</h3>
                        
                        <div class="history-grid">
                            <!-- 2025.11.12 Wabi-Tune -->
                            <article class="history-card" style="display: flex; flex-direction: column; height: 100%;">
                                <img src="mtg_2024_11_12.png" alt="わびチューン" style="width: 100%; height: 180px; object-fit: cover;">
                                <div class="history-info" style="flex-grow: 1; display: flex; flex-direction: column;">
                                    <span class="history-season" style="color: var(--accent-blue); font-weight: bold;">2025.11.12</span>
                                    <h4 style="margin: 0.5rem 0; font-size: 1.1rem; line-height: 1.4;">わびチューン<br><span style="font-size: 0.9rem; font-weight: normal;">〜3人の美食家を添えて〜</span></h4>
                                    <p style="font-size: 0.85rem; line-height: 1.6; color: var(--text-color);">
                                        3人のクセ強審査員（論理派・アホ・のりお）を攻略せよ！「おでんの具は大根か卵か？」「初デートはイタリアンか居酒屋か？」などをテーマに、各班で白熱の議論とプレゼンを行いました。
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

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
