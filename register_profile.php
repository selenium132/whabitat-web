<?php
require_once 'config.php';
requireLogin(); // Must be logged in via LINE first

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $grade = $_POST['grade'] ?? '';

    if ($name && $student_id && $grade) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, grade = ? WHERE id = ?");
        if ($stmt->execute([$name, $student_id, $grade, $_SESSION['user_id']])) {
            $_SESSION['name'] = $name; // Update session
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'エラーが発生しました。';
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
    <title>プロフィール登録 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 500px; margin: 100px auto; padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-input, .form-select { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px; }
        .btn-submit { width: 100%; padding: 1rem; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="#" class="logo">WHABITAT</a>
        </div>
    </header>
    <main>
        <div class="container">
            <h1>プロフィール登録</h1>
            <p>初回のみ、以下の情報を登録してください。</p>
            <?php if ($error): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">お名前（本名）</label>
                    <input type="text" name="name" class="form-input" required placeholder="例：早稲田 太郎">
                </div>
                <div class="form-group">
                    <label class="form-label">学籍番号</label>
                    <input type="text" name="student_id" class="form-input" required placeholder="例：1A234567">
                </div>
                <div class="form-group">
                    <label class="form-label">代（学年）</label>
                    <select name="grade" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach (AVAILABLE_GRADES as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit">登録して始める</button>
            </form>
        </div>
    </main>
</body>
</html>
