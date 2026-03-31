<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $line_name = $_POST['line_name'] ?? '';
    $admission_year = $_POST['admission_year'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $secret = $_POST['secret'] ?? '';

    if ($name && $line_name && $admission_year && $email && $password && $secret) {
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
            
            // Calculate grade from graduation year
            $grad_year_num = (int)str_replace('年', '', $admission_year);
            $grade = ($grad_year_num > 2000) ? ($grad_year_num - 2028 + 18) . 'th' : '';

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'このメールアドレスは既に登録されています。';
            } else {
                // Register user
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, student_id, line_name, grade, admission_year, email, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $student_id, $line_name, $grade, $admission_year, $email, $passwordHash, $role])) {
                    // Auto login
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['role'] = $role;
                    $_SESSION['name'] = $name;
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
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
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
        .form-input, .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-select {
            background-color: white;
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
                    <label class="form-label">名前 <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="例：早稲田 太郎" required value="<?php echo htmlspecialchars($name); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">学籍番号</label>
                    <input type="text" name="student_id" class="form-input" placeholder="例：1A234567">
                </div>
                <div class="form-group">
                    <label class="form-label">LINE名 <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="line_name" class="form-input" placeholder="例：Taro Waseda" required>
                </div>
                <div class="form-group">
                    <label class="form-label">卒業予定年 <span style="color: #e74c3c;">*</span></label>
                    <select name="admission_year" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php 
                        $current_year = (int)date('Y');
                        $current_month = (int)date('n');
                        $earliest_grad = ($current_month >= 4) ? $current_year + 1 : $current_year;
                        for ($y = $earliest_grad; $y <= $earliest_grad + 3; $y++) {
                            echo '<option value="' . $y . '年">' . $y . '年</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">メールアドレス <span style="color: #e74c3c;">*</span></label>
                    <input type="email" name="email" class="form-input" placeholder="例：waseda@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">パスワード <span style="color: #e74c3c;">*</span></label>
                    <input type="password" name="password" class="form-input" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">自動承認用合言葉 <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="secret" class="form-input" placeholder="サークルで共有された合言葉" required>
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
