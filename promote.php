<?php
require_once 'config.php';
requireLogin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $secret = $_POST['secret'] ?? '';
    
    if ($secret === ADMIN_SECRET) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin', is_approved = 1 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        $_SESSION['role'] = 'admin';
        $_SESSION['is_approved'] = 1;
        $message = '管理者権限を取得しました！ダッシュボードに戻ります...';
        header("Refresh: 2; url=dashboard.php");
    } else {
        $error = '合言葉が違います。';
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者権限取得 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main>
        <div class="dashboard-container" style="max-width: 500px; margin-top: 150px;">
            <div class="card" style="text-align: center;">
                <h1 style="font-size: 1.5rem;">管理者への昇格</h1>
                <p style="margin-bottom: 2rem; color: var(--text-light);">管理者用の合言葉を入力してください。</p>
                
                <?php if ($message): ?>
                    <p style="color: #2ecc71; font-weight: bold; margin-bottom: 1rem;"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>
                <?php if ($error): ?>
                    <p style="color: #e74c3c; font-weight: bold; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-group">
                        <input type="password" name="secret" class="form-input" placeholder="管理者用パスワード">
                    </div>
                    <button type="submit" class="btn-danger" style="width: 100%;">権限を取得する</button>
                </form>
                <div style="margin-top: 1.5rem;">
                    <a href="dashboard.php" style="color: var(--text-light); font-size: 0.9rem;">戻る</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
