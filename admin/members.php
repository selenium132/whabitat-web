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
        } elseif ($action === 'update_role') {
            $new_role = $_POST['role'] ?? '';
            if ($new_role === 'member' || $new_role === 'admin') {
                // Prevent removing own admin rights
                if ($target_id != $_SESSION['user_id'] || $new_role === 'admin') {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_id]);
                }
            }
        }
    }
}

// Fetch All Members
$stmt = $pdo->query("SELECT * FROM users ORDER BY is_approved ASC, created_at DESC");
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
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo">WHABITAT Admin</a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 1100px;">
            <a href="../dashboard.php" style="display: inline-block; margin-bottom: 1rem;">&lt; ダッシュボードに戻る</a>
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
                                        <?php if (!$m['is_approved']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">承認</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($m['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: flex; gap: 5px;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <select name="role" class="form-select" style="padding: 0.3rem; width: auto; font-size: 0.9rem;">
                                                        <option value="member" <?php echo $m['role'] === 'member' ? 'selected' : ''; ?>>一般</option>
                                                        <option value="admin" <?php echo $m['role'] === 'admin' ? 'selected' : ''; ?>>管理者</option>
                                                    </select>
                                                    <button type="submit" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">更新</button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
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
</body>
</html>
