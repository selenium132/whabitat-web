<?php
require_once 'config.php';
require_once 'sheet_sync.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!empty($_SESSION['is_approved'])) {
    header("Location: dashboard.php");
    exit;
}

// Re-check DB status
$pdo = getDB();
$stmt = $pdo->prepare("SELECT is_approved FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && $user['is_approved']) {
    $_SESSION['is_approved'] = true;
    header("Location: dashboard.php");
    exit;
}

// Handle Secret Keyword
$error_msg = '';
$csrf_token = generateCsrfToken(); // Generate Token

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secret_keyword'])) {
    validateCsrfToken($_POST['csrf_token'] ?? ''); // Validate Token

    $input_keyword = trim($_POST['secret_keyword']);
    // Check against circle secret for mass registration bypass
    if ($input_keyword === CIRCLE_SECRET) {
        // Approve User
        $update_stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
        $update_stmt->execute([$_SESSION['user_id']]);
        // 承認を名簿スプシに自動反映（連携済みの場合のみ）
        syncMembersToSheetSafe($pdo);

        // Update Session
        $_SESSION['is_approved'] = 1;
        
        // Redirect to Profile Registration
        header("Location: register_profile.php");
        exit;
    } else {
        $error_msg = '合言葉が間違っています。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>承認待ち | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .divider-or {
            display: flex;
            align-items: center;
            color: #999;
            margin: 2rem 0;
            font-size: 0.9rem;
        }
        .divider-or::before, .divider-or::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #eee;
        }
        .divider-or::before { margin-right: 10px; }
        .divider-or::after { margin-left: 10px; }
        
        .secret-form {
            background-color: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
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
                
                <div class="divider-or">または</div>
                
                <div class="secret-form">
                    <p style="margin-bottom: 1rem; font-weight: 600; color: var(--primary-color);">合言葉をお持ちの方はこちら</p>
                    
                    <?php if ($error_msg): ?>
                        <div style="color: #d93025; font-size: 0.9rem; margin-bottom: 10px;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <input type="text" name="secret_keyword" placeholder="合言葉を入力" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; flex: 1; max-width: 250px;" required>
                            <button type="submit" class="btn-primary" style="padding: 10px 20px; font-size: 0.9rem;">送信</button>
                        </div>
                    </form>
                </div>

                <a href="logout.php" class="btn-secondary">ログアウト</a>
            </div>
        </div>
    </main>
</body>
</html>
