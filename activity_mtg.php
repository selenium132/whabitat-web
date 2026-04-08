<?php require_once 'config.php';
$pdo = getDB();

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS mtg_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    year_group INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Fetch MTG history grouped by year
$entries = $pdo->query("SELECT * FROM mtg_history ORDER BY year_group DESC, event_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($entries as $entry) {
    $grouped[$entry['year_group']][] = $entry;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
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
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2 class="section-title" style="margin: 0;"><span>MTG HISTORY</span></h2>
                        <?php if ($is_admin): ?>
                            <a href="admin/mtg_history.php" style="background: var(--primary-color); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 0.9rem;">
                                <i class="fas fa-plus"></i> 履歴追加
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($grouped)): ?>
                        <div class="card" style="text-align: center; color: #666;">
                            MTG履歴はまだありません。
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped as $year => $yearEntries): ?>
                            <div class="history-year-group">
                                <h3 style="font-size: 1.5rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; margin-bottom: 2rem; color: var(--primary-color);"><?php echo $year; ?></h3>
                                
                                <div class="history-grid">
                                    <?php foreach ($yearEntries as $entry): ?>
                                        <article class="history-card" style="display: flex; flex-direction: column; height: 100%;">
                                            <?php if ($entry['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($entry['image_path']); ?>" alt="<?php echo htmlspecialchars($entry['title']); ?>" style="width: 100%; height: 180px; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 180px; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="history-info" style="flex-grow: 1; display: flex; flex-direction: column;">
                                                <span class="history-season" style="color: var(--accent-blue); font-weight: bold;"><?php echo date('Y.m.d', strtotime($entry['event_date'])); ?></span>
                                                <h4 style="margin: 0.5rem 0; font-size: 1.1rem; line-height: 1.4;">
                                                    <?php echo htmlspecialchars($entry['title']); ?>
                                                    <?php if ($entry['subtitle']): ?>
                                                        <br><span style="font-size: 0.9rem; font-weight: normal;"><?php echo htmlspecialchars($entry['subtitle']); ?></span>
                                                    <?php endif; ?>
                                                </h4>
                                                <?php if ($entry['description']): ?>
                                                    <p style="font-size: 0.85rem; line-height: 1.6; color: var(--text-color);">
                                                        <?php echo nl2br(htmlspecialchars($entry['description'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
