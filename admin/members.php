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
$stmt = $pdo->query("SELECT * FROM users ORDER BY is_approved ASC, created_at DESC"); // Unapproved first
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メンバー管理 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .container { max-width: 1000px; margin: 100px auto; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; }
        .role-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        .role-admin { background: #e74c3c; color: white; }
        .role-member { background: #eee; color: #333; }
        .status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        .status-pending { background: #f39c12; color: white; }
        .status-approved { background: #2ecc71; color: white; }
        .btn-update { padding: 0.3rem 0.8rem; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-approve { padding: 0.3rem 0.8rem; background: #2ecc71; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo">WHABITAT Admin</a>
        </div>
    </header>

    <main>
        <div class="container">
            <a href="../dashboard.php">&lt; ダッシュボードに戻る</a>
            <h1>メンバー管理</h1>
            
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
                                <?php if ($m['avatar_url']): ?>
                                    <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" style="width: 30px; height: 30px; border-radius: 50%; vertical-align: middle; margin-right: 5px;">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($m['name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($m['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($m['line_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['grade']); ?></td>
                            <td>
                                <?php if ($m['is_approved']): ?>
                                    <span class="status-badge status-approved">承認済</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">未承認</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $m['role']; ?>">
                                    <?php echo $m['role'] === 'admin' ? '管理者' : '一般'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$m['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">承認する</button>
                                    </form>
                                <?php else: ?>
                                    <?php if ($m['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                            <input type="hidden" name="action" value="update_role">
                                            <select name="role" style="padding: 0.3rem;">
                                                <option value="member" <?php echo $m['role'] === 'member' ? 'selected' : ''; ?>>一般</option>
                                                <option value="admin" <?php echo $m['role'] === 'admin' ? 'selected' : ''; ?>>管理者</option>
                                            </select>
                                            <button type="submit" class="btn-update">更新</button>
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
    </main>
</body>
</html>
