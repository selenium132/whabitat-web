<?php
require_once 'config.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $name = $_POST['name'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $faculty = $_POST['faculty'] ?? '';
    $gender = $_POST['gender'] ?? '';

    // All fields are required
    if ($name && $student_id && $grade && $faculty && $gender) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, grade = ?, faculty = ?, gender = ? WHERE id = ?");
        if ($stmt->execute([$name, $student_id, $grade, $faculty, $gender, $_SESSION['user_id']])) {
            $_SESSION['name'] = $name;
            // Check if there's a return URL to redirect to
            $return_url = $_POST['return_url'] ?? '';
            if (!empty($return_url)) {
                header("Location: " . $return_url);
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = 'エラーが発生しました。';
        }
    } else {
        $error = '全ての項目を入力してください。';
    }
}

$csrf_token = generateCsrfToken();

// Fetch current data for pre-filling
$current_user = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name, student_id, grade, faculty, gender FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Use POST data if available (e.g. after error), otherwise DB data, otherwise empty
$name_val = $_POST['name'] ?? $current_user['name'] ?? '';
$sid_val = $_POST['student_id'] ?? $current_user['student_id'] ?? '';
$grade_val = $_POST['grade'] ?? $current_user['grade'] ?? '';
$faculty_val = $_POST['faculty'] ?? $current_user['faculty'] ?? '';
$gender_val = $_POST['gender'] ?? $current_user['gender'] ?? '';

// Waseda University faculties
$waseda_faculties = [
    '政治経済学部',
    '法学部',
    '教育学部',
    '商学部',
    '社会科学部',
    '国際教養学部',
    '文化構想学部',
    '文学部',
    '基幹理工学部',
    '創造理工学部',
    '先進理工学部',
    '人間科学部',
    'スポーツ科学部',
];

// Determine if this is initial registration or profile edit
// If name is empty in DB, it's first registration
$is_first_registration = empty($current_user['name']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
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
                <?php if ($is_first_registration): ?>
                    <h1 style="text-align: center; font-size: 1.8rem; margin-bottom: 2rem;">プロフィール登録</h1>
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">初回のみ、以下の情報を登録してください。</p>
                <?php else: ?>
                    <h1 style="text-align: center; font-size: 1.8rem; margin-bottom: 2rem;">プロフィール編集</h1>
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">情報を変更できます。</p>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <p style="color: #e74c3c; text-align: center; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_GET['return'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">お名前（本名） <span style="font-size: 0.8rem; color: #e74c3c;">※名字と名前の間にスペースは入れないでください</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="例：早稲田太郎" value="<?php echo htmlspecialchars($name_val); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">学籍番号</label>
                        <input type="text" name="student_id" class="form-input" required placeholder="例：1A234567" value="<?php echo htmlspecialchars($sid_val); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">代（学年）</label>
                        <select name="grade" class="form-select" required>
                            <option value="">選択してください</option>
                            <?php foreach (AVAILABLE_GRADES as $g): ?>
                                <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $grade_val === $g ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">学部</label>
                        <select name="faculty" class="form-select" required>
                            <option value="">選択してください</option>
                            <?php foreach ($waseda_faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $faculty_val === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">性別</label>
                        <select name="gender" class="form-select" required>
                            <option value="">選択してください</option>
                            <option value="male" <?php echo $gender_val === 'male' ? 'selected' : ''; ?>>男性</option>
                            <option value="female" <?php echo $gender_val === 'female' ? 'selected' : ''; ?>>女性</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <?php echo $is_first_registration ? '登録して始める' : '更新する'; ?>
                    </button>
                </form>
                <?php if (!$is_first_registration): ?>
                    <a href="dashboard.php" class="btn-secondary" style="display:block;text-align:center;margin-top:1rem;width:100%;">
                        <i class="fas fa-arrow-left" style="margin-right:6px;"></i>ダッシュボードに戻る
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
