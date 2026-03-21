<?php
require_once 'config.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $name = $_POST['name'] ?? '';
    $name_kana = $_POST['name_kana'] ?? '';
    $student_id = $_POST['student_id'] ?? '';
    $grade = $_POST['grade'] ?? '';
    $faculty = $_POST['faculty'] ?? '';
    $department = $_POST['department'] ?? '';
    $admission_year = $_POST['admission_year'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $other_circles = $_POST['other_circles'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Calculate grade
    $grade = '';
    if ($admission_year) {
        $adm_year_num = (int)str_replace('年', '', $admission_year);
        if ($adm_year_num > 2000) {
            $grade = ($adm_year_num - 2024 + 18) . 'th';
        }
    }

    // Required fields validation
    if ($name && $name_kana && $faculty && $gender && $admission_year && $zipcode && $address && $phone && $birthdate) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET name = ?, name_kana = ?, student_id = ?, grade = ?, faculty = ?, department = ?, admission_year = ?, gender = ?, zipcode = ?, address = ?, phone = ?, birthdate = ?, other_circles = ?, allergies = ?, notes = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $name_kana, $student_id, $grade, $faculty, $department, $admission_year, $gender, $zipcode, $address, $phone, $birthdate, $other_circles, $allergies, $notes, $_SESSION['user_id']])) {
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
        $error = '必須項目をすべて入力してください。';
    }
}

$csrf_token = generateCsrfToken();

// Fetch current data for pre-filling
$current_user = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name, name_kana, student_id, grade, faculty, department, admission_year, gender, zipcode, address, phone, birthdate, other_circles, allergies, notes FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Use POST data if available (e.g. after error), otherwise DB data, otherwise empty
$name_val = $_POST['name'] ?? $current_user['name'] ?? '';
$name_kana_val = $_POST['name_kana'] ?? $current_user['name_kana'] ?? '';
$sid_val = $_POST['student_id'] ?? $current_user['student_id'] ?? '';
$grade_val = $_POST['grade'] ?? $current_user['grade'] ?? '';
$faculty_val = $_POST['faculty'] ?? $current_user['faculty'] ?? '';
$department_val = $_POST['department'] ?? $current_user['department'] ?? '';
$admission_year_val = $_POST['admission_year'] ?? $current_user['admission_year'] ?? '';
$gender_val = $_POST['gender'] ?? $current_user['gender'] ?? '';
$zipcode_val = $_POST['zipcode'] ?? $current_user['zipcode'] ?? '';
$address_val = $_POST['address'] ?? $current_user['address'] ?? '';
$phone_val = $_POST['phone'] ?? $current_user['phone'] ?? '';
$birthdate_val = $_POST['birthdate'] ?? $current_user['birthdate'] ?? '';
$other_circles_val = $_POST['other_circles'] ?? $current_user['other_circles'] ?? '';
$allergies_val = $_POST['allergies'] ?? $current_user['allergies'] ?? '';
$notes_val = $_POST['notes'] ?? $current_user['notes'] ?? '';

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        <div class="dashboard-container" style="max-width: 600px;">
            <?php if (!$is_first_registration): ?>
                <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--text-light);text-decoration:none;font-size:0.9rem;margin-bottom:1rem;">
                    <i class="fas fa-arrow-left"></i>ダッシュボードに戻る
                </a>
            <?php endif; ?>
            <div class="card">
                <?php if ($is_first_registration): ?>
                    <h1 style="text-align: center; font-size: 1.8rem; margin-bottom: 2rem;">プロフィール登録</h1>
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">以下の情報を登録してください。</p>
                <?php else: ?>
                    <h1 style="text-align: center; font-size: 1.8rem; margin-bottom: 2rem;">プロフィール編集</h1>
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">設定している個人情報を変更できます。</p>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <p style="color: #e74c3c; text-align: center; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_GET['return'] ?? ''); ?>">
                    
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;"><i class="fas fa-user"></i> 基本情報</h2>
                        
                        <div class="form-group">
                            <label class="form-label">氏名（正式名フルネーム） <span style="color: #e74c3c;">*</span></label>
                            <p style="font-size: 0.8rem; color: #666; margin-bottom: 0.3rem;">※姓と名の間は全角スペースを1文字空ける</p>
                            <input type="text" name="name" class="form-input" required placeholder="例：早稲田　太郎" value="<?php echo htmlspecialchars($name_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">氏名（ふりがな） <span style="color: #e74c3c;">*</span></label>
                            <p style="font-size: 0.8rem; color: #666; margin-bottom: 0.3rem;">※姓と名の間は全角スペースを1文字空ける</p>
                            <input type="text" name="name_kana" class="form-input" required placeholder="例：わせだ　たろう" value="<?php echo htmlspecialchars($name_kana_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">性別 <span style="color: #e74c3c;">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">選択してください</option>
                                <option value="male" <?php echo $gender_val === 'male' ? 'selected' : ''; ?>>男</option>
                                <option value="female" <?php echo $gender_val === 'female' ? 'selected' : ''; ?>>女</option>
                                <option value="no_answer" <?php echo $gender_val === 'no_answer' ? 'selected' : ''; ?>>回答しない</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">生年月日 <span style="color: #e74c3c;">*</span></label>
                            <input type="date" name="birthdate" class="form-input" required value="<?php echo htmlspecialchars($birthdate_val); ?>">
                        </div>
                    </div>

                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;"><i class="fas fa-graduation-cap"></i> 大学情報</h2>

                        <div class="form-group">
                            <label class="form-label">入学年 <span style="color: #e74c3c;">*</span></label>
                            <select name="admission_year" class="form-select" required>
                                <option value="">選択してください</option>
                                <?php 
                                $current_year = (int)date('Y');
                                // Include current_year - 3 up to current_year
                                for ($y = $current_year - 3; $y <= $current_year; $y++) {
                                    $val = $y . '年';
                                    $sel = ($admission_year_val === $val) ? 'selected' : '';
                                    echo "<option value=\"$val\" $sel>$val</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">学部 <span style="color: #e74c3c;">*</span></label>
                            <select name="faculty" class="form-select" required>
                                <option value="">選択してください</option>
                                <?php foreach ($waseda_faculties as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $faculty_val === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">学科</label>
                            <input type="text" name="department" class="form-input" placeholder="任意" value="<?php echo htmlspecialchars($department_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">学籍番号（ハイフン以下不要）</label>
                            <p style="font-size: 0.8rem; color: #666; margin-bottom: 0.3rem;">※現在学籍番号がない方は空欄で構いません。後日入力をお願いします。</p>
                            <input type="text" name="student_id" class="form-input" placeholder="例：1A234567" value="<?php echo htmlspecialchars($sid_val); ?>">
                        </div>
                    </div>

                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;"><i class="fas fa-home"></i> 連絡先・その他</h2>

                        <div class="form-group">
                            <label class="form-label">郵便番号（ハイフンあり） <span style="color: #e74c3c;">*</span></label>
                            <input type="text" name="zipcode" class="form-input" required placeholder="例：169-8050" value="<?php echo htmlspecialchars($zipcode_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">住所 <span style="color: #e74c3c;">*</span></label>
                            <input type="text" name="address" class="form-input" required placeholder="例：東京都新宿区戸塚町1-104" value="<?php echo htmlspecialchars($address_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">携帯電話番号 <span style="color: #e74c3c;">*</span></label>
                            <p style="font-size: 0.8rem; color: #666; margin-bottom: 0.3rem;">例: 000-0000-0000</p>
                            <input type="text" name="phone" class="form-input" required placeholder="例：090-1234-5678" value="<?php echo htmlspecialchars($phone_val); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">他サークル・団体</label>
                            <input type="text" name="other_circles" class="form-input" placeholder="任意" value="<?php echo htmlspecialchars($other_circles_val); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">アレルギー等</label>
                            <textarea name="allergies" class="form-input" rows="2" placeholder="任意"><?php echo htmlspecialchars($allergies_val); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">その他何かあればどうぞ！</label>
                            <textarea name="notes" class="form-input" rows="3" placeholder="任意"><?php echo htmlspecialchars($notes_val); ?></textarea>
                        </div>
                    </div>

                    <p style="font-size: 0.85rem; color: #666; text-align: center; margin-bottom: 1.5rem;">
                        <i class="fas fa-shield-alt"></i> ご記入いただいた個人情報は厳重に管理され、サークル運営以外の目的には使用されません。
                    </p>

                    <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                        <?php echo $is_first_registration ? '登録して始める' : '更新する'; ?>
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
