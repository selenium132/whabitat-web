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

    // 総当たり対策: 直近10分間で5回失敗したら一時的にロックする。
    // 判定は「IP単位のDB記録」を主軸にする（ログアウト→再ログインで新セッションにしても
    // リセットされないようにするため）。従来のセッション単位カウントも併用する多層防御。
    $now = time();
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // IP単位の失敗記録テーブルを自動生成（audit_log と同じ流儀。無ければ作る）
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS secret_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // 古い記録（1日以上前）を掃除してテーブルの肥大化を防ぐ
        $pdo->prepare("DELETE FROM secret_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)")->execute();
    } catch (Exception $e) {
        error_log('ensure secret_attempts failed: ' . $e->getMessage());
    }

    if (!isset($_SESSION['approval_attempts']) || !is_array($_SESSION['approval_attempts'])) {
        $_SESSION['approval_attempts'] = [];
    }
    // 10分より古い試行記録を破棄
    $_SESSION['approval_attempts'] = array_values(array_filter(
        $_SESSION['approval_attempts'],
        function ($t) use ($now) { return $t > $now - 600; }
    ));

    // IP単位の直近10分の失敗回数
    $ip_attempts = 0;
    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM secret_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 10 MINUTE)");
        $cnt->execute([$client_ip]);
        $ip_attempts = (int)$cnt->fetchColumn();
    } catch (Exception $e) {
        error_log('count secret_attempts failed: ' . $e->getMessage());
    }

    if (count($_SESSION['approval_attempts']) >= 5 || $ip_attempts >= 5) {
        $error_msg = '試行回数が多すぎます。しばらく時間をおいてから再度お試しください。';
    } else {
        $input_keyword = trim($_POST['secret_keyword']);
        // Check against circle secret for mass registration bypass（タイミング攻撃対策にhash_equals）
        if (CIRCLE_SECRET !== '' && hash_equals(CIRCLE_SECRET, $input_keyword)) {
            // Approve User
            $update_stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            $update_stmt->execute([$_SESSION['user_id']]);

            // Update Session
            $_SESSION['is_approved'] = 1;
            unset($_SESSION['approval_attempts']);
            // 成功したらこのIPの失敗記録も消す（正規ユーザーを後で締め出さない）
            try {
                $del = $pdo->prepare("DELETE FROM secret_attempts WHERE ip = ?");
                $del->execute([$client_ip]);
            } catch (Exception $e) {}

            // Redirect to Profile Registration
            header("Location: register_profile.php");
            exit;
        } else {
            $_SESSION['approval_attempts'][] = $now;
            // 失敗をIP単位でも記録（セッションを捨てても残る）
            try {
                $ins = $pdo->prepare("INSERT INTO secret_attempts (ip) VALUES (?)");
                $ins->execute([$client_ip]);
            } catch (Exception $e) {}
            $error_msg = '合言葉が間違っています。';
        }
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
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
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
    <link rel="stylesheet" href="member.css?v=<?php echo @filemtime(__DIR__ . '/member.css') ?: '1'; ?>">
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
                <div style="font-size: 4rem; color: var(--primary-color); margin-bottom: 1.5rem;">
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
                        <div style="color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px;">
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
