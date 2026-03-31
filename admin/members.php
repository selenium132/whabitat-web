<?php
require_once '../config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? ''); // CSRF Check

    $target_id = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($target_id) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$target_id]);
        } elseif ($action === 'disapprove') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
            $stmt->execute([$target_id]);
        } elseif ($action === 'delete') {
            // Prevent deleting self
            if ($target_id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_id]);
            }
        } elseif ($action === 'update_role') {
            $new_role = $_POST['role'] ?? '';
            if ($new_role === 'member' || $new_role === 'admin') {
                // Prevent removing own admin rights
                if ($target_id != $_SESSION['user_id'] || $new_role === 'admin') {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_id]);
                }
            }
        } elseif ($action === 'update_profile') {
            // Admin Update Profile
            $name = $_POST['name'] ?? '';
            $name_kana = $_POST['name_kana'] ?? '';
            $sid = $_POST['student_id'] ?? '';
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
            
            // Calculate grade from graduation year
            $grade = '';
            if ($admission_year) {
                $grad_year_num = (int)str_replace('年', '', $admission_year);
                if ($grad_year_num > 2000) {
                    $grade = ($grad_year_num - 2028 + 18) . 'th';
                }
            }

            if ($name && $admission_year) {
                // Use Prepared Statements to prevent SQL injection
                $stmt = $pdo->prepare("UPDATE users SET 
                    name = ?, name_kana = ?, student_id = ?, grade = ?, faculty = ?, 
                    department = ?, admission_year = ?, gender = ?, zipcode = ?, address = ?, 
                    phone = ?, birthdate = ?, other_circles = ?, allergies = ?, notes = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $name, $name_kana, $sid, $grade, $faculty, 
                    $department, $admission_year, $gender, $zipcode, $address, 
                    $phone, empty($birthdate) ? null : $birthdate, $other_circles, $allergies, $notes, 
                    $target_id
                ]);
            }
        }
    }
}

// Fetch All Members
$stmt = $pdo->query("SELECT * FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count members by grade (only approved members)
$grade_counts = [];
$total_approved = 0;
$total_all = count($members);
foreach ($members as $m) {
    if ($m['is_approved']) {
        $total_approved++;
        $grade = $m['grade'] ?: '未設定';
        if (!isset($grade_counts[$grade])) {
            $grade_counts[$grade] = 0;
        }
        $grade_counts[$grade]++;
    }
}
ksort($grade_counts);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="apple-touch-icon" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メンバー管理 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        function confirmAction(message) {
            return confirm(message);
        }

        // Modal Logic
        function openEditModal(userObj) {
            document.getElementById('edit_user_id').value = userObj.id || '';
            document.getElementById('edit_name').value = userObj.name || '';
            document.getElementById('edit_name_kana').value = userObj.name_kana || '';
            document.getElementById('edit_sid').value = userObj.student_id || '';
            document.getElementById('edit_faculty').value = userObj.faculty || '';
            document.getElementById('edit_department').value = userObj.department || '';
            document.getElementById('edit_admission_year').value = userObj.admission_year || '';
            document.getElementById('edit_gender').value = userObj.gender || '';
            document.getElementById('edit_zipcode').value = userObj.zipcode || '';
            document.getElementById('edit_address').value = userObj.address || '';
            document.getElementById('edit_phone').value = userObj.phone || '';
            document.getElementById('edit_birthdate').value = userObj.birthdate || '';
            document.getElementById('edit_other_circles').value = userObj.other_circles || '';
            document.getElementById('edit_allergies').value = userObj.allergies || '';
            document.getElementById('edit_notes').value = userObj.notes || '';
            
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
    <style>
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000;
            display: none; align-items: center; justify-content: center;
        }
        .modal-content {
            background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .edit-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .edit-section-title {
            font-size: 1rem;
            margin-bottom: 0.8rem;
            border-bottom: 1px solid #ddd;
            padding-bottom: 0.4rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 1100px;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
                <h1 style="margin: 0;">メンバー管理</h1>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="members_export_sheet.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fas fa-file-excel"></i> シートに出力
                    </a>
                </div>
            </div>
            
            <!-- Member Statistics -->
            <div style="margin-bottom: 1rem; padding: 0.8rem 1rem; background: #f8f9fa; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 0.8rem; align-items: center; font-size: 0.9rem;">
                <span style="font-weight: 600;">👥 <?php echo $total_approved; ?>名</span>
                <span style="color: #999;">|</span>
                <?php foreach ($grade_counts as $grade => $count): ?>
                    <span style="color: #666;"><?php echo htmlspecialchars($grade); ?>: <?php echo $count; ?></span>
                <?php endforeach; ?>
                <?php if ($total_all > $total_approved): ?>
                    <span style="color: #999;">|</span>
                    <span style="color: #f39c12;">未承認: <?php echo $total_all - $total_approved; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="card" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>名前</th>
                                <th>ふりがな</th>
                                <th>学籍番号</th>
                                <th>LINE名</th>
                                <th>代</th>
                                <th>卒業予定年</th>
                                <th>今の学年</th>
                                <th>学部</th>
                                <th>学科</th>
                                <th>性別</th>
                                <th>ステータス</th>
                                <th>権限</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <tr>
                                    <td>
                                        <?php if ($m['avatar_url']): ?>
                                            <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: #eee; display: flex; align-items: center; justify-content: center; color: #ccc;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['name_kana'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($m['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($m['line_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['grade']); ?></td>
                                    <td><?php echo htmlspecialchars($m['admission_year'] ?? ''); ?></td>
                                    <td>
                                        <?php 
                                        $uni_year_str = "-";
                                        if (!empty($m['admission_year'])) {
                                            $grad_year_num = (int)str_replace('年', '', $m['admission_year']);
                                            $current_year = (int)date('Y');
                                            $current_month = (int)date('n');
                                            $current_academic_year = ($current_month >= 4) ? $current_year : $current_year - 1;
                                            if ($grad_year_num > 2000) {
                                                // 卒業年から今の学年を計算: 4 - (卒業年 - 現在学年度 - 1)
                                                $uni_year = 4 - ($grad_year_num - $current_academic_year - 1);
                                                if ($uni_year < 1) $uni_year_str = "入学前";
                                                elseif ($uni_year > 4) $uni_year_str = "OB/OG";
                                                else $uni_year_str = $uni_year . "年生";
                                            }
                                        }
                                        echo $uni_year_str;
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['faculty'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($m['department'] ?? ''); ?></td>
                                    <td><?php 
                                        $gen = $m['gender'] ?? '';
                                        if ($gen === 'male') echo '男';
                                        elseif ($gen === 'female') echo '女';
                                        elseif ($gen === 'no_answer') echo '未回答';
                                        else echo '-';
                                    ?></td>
                                    <td>
                                        <?php if ($m['is_approved']): ?>
                                            <span style="color: #2ecc71; font-weight: bold;">承認済</span>
                                        <?php else: ?>
                                            <span style="color: #f39c12; font-weight: bold;">未承認</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $m['role'] === 'admin' ? '管理者' : '一般'; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['id'] != $_SESSION['user_id']): ?>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <?php
                                                    $userJson = json_encode([
                                                        'id' => $m['id'],
                                                        'name' => $m['name'],
                                                        'name_kana' => $m['name_kana'] ?? '',
                                                        'student_id' => $m['student_id'],
                                                        'grade' => $m['grade'],
                                                        'faculty' => $m['faculty'] ?? '',
                                                        'department' => $m['department'] ?? '',
                                                        'admission_year' => $m['admission_year'] ?? '',
                                                        'gender' => $m['gender'] ?? '',
                                                        'zipcode' => $m['zipcode'] ?? '',
                                                        'address' => $m['address'] ?? '',
                                                        'phone' => $m['phone'] ?? '',
                                                        'birthdate' => $m['birthdate'] ?? '',
                                                        'other_circles' => $m['other_circles'] ?? '',
                                                        'allergies' => $m['allergies'] ?? '',
                                                        'notes' => $m['notes'] ?? ''
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                                ?>
                                                <button type="button" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;" 
                                                    onclick='openEditModal(<?php echo $userJson; ?>)'>
                                                    編集
                                                </button>

                                                <?php if (!$m['is_approved']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">承認</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('本当に承認を取り消しますか？');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="disapprove">
                                                        <button type="submit" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem; background-color: #f39c12; color: white;">取消</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" style="display: inline;" onsubmit="return confirmAction('本当にこのユーザーを削除しますか？\nこの操作は取り消せません。');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-danger" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">削除</button>
                                                </form>

                                                <?php if ($m['is_approved']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                        <input type="hidden" name="action" value="update_role">
                                                        <select name="role" class="form-select" style="padding: 0.3rem; width: auto; font-size: 0.9rem;" onchange="this.form.submit()">
                                                            <option value="member" <?php echo $m['role'] === 'member' ? 'selected' : ''; ?>>一般</option>
                                                            <option value="admin" <?php echo $m['role'] === 'admin' ? 'selected' : ''; ?>>管理者</option>
                                                        </select>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #ccc;">(自分)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target === this) closeEditModal()">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem; text-align: center;">メンバー情報を編集</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="action" value="update_profile">

                <div class="edit-section">
                    <div class="edit-section-title">基本情報</div>
                    <div class="form-group">
                        <label class="form-label">名前</label>
                        <input type="text" name="name" id="edit_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ふりがな</label>
                        <input type="text" name="name_kana" id="edit_name_kana" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">生年月日</label>
                        <input type="date" name="birthdate" id="edit_birthdate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">性別</label>
                        <select name="gender" id="edit_gender" class="form-select">
                            <option value="">選択してください</option>
                            <option value="male">男性</option>
                            <option value="female">女性</option>
                            <option value="no_answer">回答しない</option>
                        </select>
                    </div>
                </div>

                <div class="edit-section">
                    <div class="edit-section-title">大学情報</div>
                    <div class="form-group">
                        <label class="form-label">卒業予定年</label>
                        <select name="admission_year" id="edit_admission_year" class="form-select" required>
                            <option value="">選択してください</option>
                            <?php 
                            $cy = (int)date('Y');
                            $cm = (int)date('n');
                            $eg = ($cm >= 4) ? $cy + 1 : $cy;
                            for ($y = $eg; $y <= $eg + 4; $y++) {
                                echo '<option value="' . $y . '年">' . $y . '年</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">学部</label>
                        <select name="faculty" id="edit_faculty" class="form-select">
                            <option value="">選択してください</option>
                            <?php 
                            $waseda_faculties = ['政治経済学部','法学部','教育学部','商学部','社会科学部','国際教養学部','文化構想学部','文学部','基幹理工学部','創造理工学部','先進理工学部','人間科学部','スポーツ科学部'];
                            foreach ($waseda_faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">学科</label>
                        <input type="text" name="department" id="edit_department" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">学籍番号</label>
                        <input type="text" name="student_id" id="edit_sid" class="form-input">
                    </div>
                </div>

                <div class="edit-section">
                    <div class="edit-section-title">連絡先・その他</div>
                    <div class="form-group">
                        <label class="form-label">郵便番号</label>
                        <input type="text" name="zipcode" id="edit_zipcode" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">住所</label>
                        <input type="text" name="address" id="edit_address" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">携帯電話番号</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">他サークル</label>
                        <input type="text" name="other_circles" id="edit_other_circles" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">アレルギー</label>
                        <textarea name="allergies" id="edit_allergies" class="form-input" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">その他</label>
                        <textarea name="notes" id="edit_notes" class="form-input" rows="2"></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 1.5rem;">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">キャンセル</button>
                    <button type="submit" class="btn-primary" style="flex: 1;">更新</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
