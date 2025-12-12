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
            $sid = $_POST['student_id'] ?? '';
            $grade = $_POST['grade'] ?? '';
            
            if ($name && $sid && $grade) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, grade = ? WHERE id = ?");
                $stmt->execute([$name, $sid, $grade, $target_id]);
            }
        }
    }
}

// Fetch All Members (Sorted by Grade ASC, then Name ASC)
$stmt = $pdo->query("SELECT * FROM users ORDER BY grade ASC, name ASC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csrf_token = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
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
        function openEditModal(id, name, sid, grade) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_sid').value = sid;
            document.getElementById('edit_grade').value = grade;
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
            background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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

            <h1 style="margin-bottom: 2rem;">メンバー管理</h1>
            
            <div class="card" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>名前</th>
                                <th>学籍番号</th>
                                <th>LINE名</th>
                                <th>代</th>
                                <th>ステータス</th>
                                <th>権限</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if ($m['avatar_url']): ?>
                                                <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" style="width: 32px; height: 32px; border-radius: 50%;">
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($m['line_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['grade']); ?></td>
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
                                                <button type="button" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;" 
                                                    onclick="openEditModal('<?php echo $m['id']; ?>', '<?php echo htmlspecialchars($m['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($m['student_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($m['grade'], ENT_QUOTES); ?>')">
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

                <div class="form-group">
                    <label class="form-label">お名前</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">学籍番号</label>
                    <input type="text" name="student_id" id="edit_sid" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">代（学年）</label>
                    <select name="grade" id="edit_grade" class="form-select" required>
                        <?php foreach (AVAILABLE_GRADES as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                        <?php endforeach; ?>
                    </select>
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
