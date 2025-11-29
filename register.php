<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $secret = $_POST['secret'] ?? '';

    if ($email && $password && $secret) {
        $role = '';
        if ($secret === ADMIN_SECRET) {
            $role = 'admin';
        } elseif ($secret === CIRCLE_SECRET) {
            $role = 'member';
        } else {
            $error = 'サークル員用パスワードが間違っています。';
        }

        if ($role) {
            $pdo = getDB();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'このメールアドレスは既に登録されています。';
            } else {
                // Register user
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
                if ($stmt->execute([$email, $passwordHash, $role])) {
                    // Auto login
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['role'] = $role;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = '登録中にエラーが発生しました。';
                }
            }
        }
    } else {
        $error = '全ての項目を入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 120px auto 60px;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .auth-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .btn-auth {
            width: 100%;
            padding: 1rem;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-auth:hover {
            background-color: #555;
        }
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .error-message {
            color: #e74c3c;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }
        .secret-note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.2rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.html" class="logo">WHABITAT</a>
        </div>
    </header>

    <main>
        <div class="auth-container">
            <h1 class="auth-title">メンバー新規登録</h1>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">メールアドレス</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">パスワード</label>
                    <input type="password" name="password" class="form-input" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">サークル員用パスワード</label>
                    <input type="text" name="secret" class="form-input" required>
                    <p class="secret-note">※一般メンバー用または管理者用の合言葉を入力</p>
                </div>
                <button type="submit" class="btn-auth">登録する</button>
            </form>
            <div class="auth-links">
                <p>すでにアカウントをお持ちの方は <a href="login.php">ログイン</a></p>
                <p><a href="index.html">トップページに戻る</a></p>
            </div>
        </div>
    </main>
</body>
</html>
