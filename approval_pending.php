<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!empty($_SESSION['is_approved'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>承認待ち | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="#" class="logo">WHABITAT</a>
        </div>
    </header>
    <main>
        <div class="dashboard-container" style="max-width: 600px; margin-top: 150px;">
            <div class="card" style="text-align: center; padding: 3rem 2rem;">
                <div style="font-size: 4rem; color: var(--accent-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-clock"></i>
                </div>
                <h1 style="font-size: 2rem; margin-bottom: 1.5rem;">承認待ちです</h1>
                <p style="line-height: 1.8; color: var(--text-color); margin-bottom: 2rem;">
                    現在、管理者の承認待ちです。<br>
                    管理者があなたのアカウントを確認し、承認するまで<br>しばらくお待ちください。
                </p>
                <a href="logout.php" class="btn-secondary">ログアウト</a>
            </div>
        </div>
    </main>
</body>
</html>
