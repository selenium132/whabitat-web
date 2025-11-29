<?php
require_once 'config.php';
requireLogin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = $_POST['secret'] ?? '';
    
    if ($secret === ADMIN_SECRET) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin', is_approved = 1 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        $_SESSION['role'] = 'admin'; // Update session
        $_SESSION['is_approved'] = 1; // Auto approve
        $message = '管理者権限を取得しました！ダッシュボードに戻ります...';
        header("Refresh: 2; url=dashboard.php");
    } else {
        $message = '合言葉が違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者権限取得 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 400px; margin: 100px auto; padding: 2rem; text-align: center; }
        .form-input { width: 100%; padding: 0.8rem; margin: 1rem 0; border: 1px solid #ddd; border-radius: 4px; }
        .btn-submit { width: 100%; padding: 1rem; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <main>
        <div class="container">
            <h1>管理者への昇格</h1>
            <p>管理者用の合言葉を入力してください。</p>
            <?php if ($message): ?>
                <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="secret" class="form-input" placeholder="管理者用パスワード">
                <button type="submit" class="btn-submit">権限を取得する</button>
            </form>
            <p style="margin-top: 1rem;"><a href="dashboard.php">戻る</a></p>
        </div>
    </main>
</body>
</html>
