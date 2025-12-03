<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
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
            <div class="activity-detail-content">
                <img src="domestic.jpg?v=<?php echo time(); ?>" alt="Domestic Volunteer" style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 2rem; box-shadow: var(--shadow-md);">
                
                <div class="card">
                    <h3>身近なところから始める社会貢献</h3>
                    <p style="line-height: 1.8; margin-bottom: 1.5rem;">
                        都内近郊を中心に、気軽に参加できるボランティア活動を行っています。<br>
                        ゴミ拾いや農業体験、地域のお祭りのお手伝いなど、地域に根ざした活動を大切にしています。
                    </p>
                    <ul style="margin-bottom: 1rem; padding-left: 1.5rem; line-height: 1.8;">
                        <li><strong>ゴミ拾い:</strong> 渋谷や海辺、早稲田周辺での清掃活動</li>
                        <li><strong>農業ボランティア:</strong> 「東京地球農園」などでの農作業体験</li>
                        <li><strong>地域連携:</strong> 早稲田周辺の町内会のお祭り手伝い、防災ボランティアなど</li>
                        <li><strong>短期合宿:</strong> 1泊〜3泊で地方へ行くプログラム（新潟県寺泊、山梨県丹波山など）</li>
                    </ul>
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
