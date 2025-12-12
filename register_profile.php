<?php
require_once 'config.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $name = $_POST['name'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $grade = $_POST['grade'] ?? '';

    if ($name && $student_id && $grade) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, grade = ? WHERE id = ?");
        if ($stmt->execute([$name, $student_id, $grade, $_SESSION['user_id']])) {
            $_SESSION['name'] = $name;
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'エラーが発生しました。';
        }
    } else {
        $error = '全ての項目を入力してください。';
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィール登録 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
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
        <div class="dashboard-container" style="max-width: 500px;">
            <div class="card">
                <h1 style="text-align: center; font-size: 1.8rem; margin-bottom: 2rem;">プロフィール登録</h1>
                <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">初回のみ、以下の情報を登録してください。</p>
                
                <?php if ($error): ?>
                    <p style="color: #e74c3c; text-align: center; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">お名前（本名） <span style="font-size: 0.8rem; color: #e74c3c;">※名字と名前の間にスペースは入れないでください</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="例：早稲田太郎">
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
                    <button type="submit" class="btn-primary" style="width: 100%;">登録して始める</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
