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
                
                <div class="card">
                    <h3>海外住居建築ボランティア</h3>
                    <p style="line-height: 1.8; margin-bottom: 1.5rem;">
                        開発途上国へ渡航し、約2週間かけて「家を建てる」手伝いをします。<br>
                        現地のホームオーナーさんと共に汗を流し、言葉の壁を越えた絆を築きます。
                    </p>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--accent-red);">主な渡航先:</strong> ネパール、カンボジア、ベトナム、インドネシアなど
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
