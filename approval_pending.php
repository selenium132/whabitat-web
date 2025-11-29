<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// If already approved, go to dashboard
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
    <style>
        .container { max-width: 500px; margin: 100px auto; padding: 2rem; text-align: center; }
        .icon { font-size: 4rem; color: #f39c12; margin-bottom: 1rem; }
        .message { margin-bottom: 2rem; line-height: 1.6; }
        .btn-logout { display: inline-block; padding: 0.8rem 1.5rem; background: #eee; color: #333; text-decoration: none; border-radius: 4px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="#" class="logo">WHABITAT</a>
        </div>
    </header>
    <main>
        <div class="container">
            <div class="icon"><i class="fas fa-clock"></i></div>
            <h1>承認待ちです</h1>
            <div class="message">
                <p>現在、管理者の承認待ちです。</p>
                <p>管理者があなたのアカウントを確認し、承認するまで<br>しばらくお待ちください。</p>
            </div>
            <a href="logout.php" class="btn-logout">ログアウト</a>
        </div>
    </main>
</body>
</html>
