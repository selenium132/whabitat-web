<?php
require_once '../config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    if (isset($_POST['users']) && is_array($_POST['users'])) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, name_kana = ? WHERE id = ?");
        $updated = 0;
        foreach ($_POST['users'] as $id => $data) {
            $name = $data['name'] ?? '';
            $kana = $data['name_kana'] ?? '';
            // Only update if not empty
            if ($name) {
                $stmt->execute([$name, $kana, $id]);
                $updated++;
            }
        }
        $message = "全メンバーの氏名を一括保存しました。";
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT id, name, name_kana, grade, admission_year FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>氏名手動一括修正 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .bulk-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .bulk-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="members.php" class="logo" style="font-size: 1rem;">
                ← メンバー管理に戻る
            </a>
        </div>
    </header>
    <main>
        <div class="dashboard-container" style="max-width: 900px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1 style="margin: 0;">氏名手動一括修正ツール</h1>
                <button type="submit" form="bulkEditForm" class="btn-primary" style="padding: 0.8rem 2rem; font-size: 1.1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    一括で保存する
                </button>
            </div>
            
            <?php if ($message): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <p style="margin-bottom: 1.5rem; color: #666; font-size: 0.9rem;">
                ここから全メンバーの名前とふりがなを連続してタイピング（スペースキー等）でサクサク修正できます。<br>
                修正が終わったら必ず右上か一番下の「一括で保存する」ボタンを押してください。
            </p>

            <div class="card" style="padding: 0;">
                <form id="bulkEditForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 15%;">代 / 入学</th>
                                    <th style="width: 40%;">氏名（正式名） ※スペース確認用</th>
                                    <th style="width: 45%;">ふりがな</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $m): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight:bold; color:var(--primary-color);"><?php echo htmlspecialchars($m['grade'] ?? '-'); ?></span><br>
                                            <span style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars($m['admission_year'] ?? ''); ?></span>
                                        </td>
                                        <td>
                                            <input type="text" name="users[<?php echo $m['id']; ?>][name]" value="<?php echo htmlspecialchars($m['name']); ?>" class="bulk-input" required>
                                        </td>
                                        <td>
                                            <input type="text" name="users[<?php echo $m['id']; ?>][name_kana]" value="<?php echo htmlspecialchars($m['name_kana']); ?>" class="bulk-input">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding: 1.5rem; text-align: center; background: #fafafa; border-top: 1px solid #ddd;">
                        <button type="submit" class="btn-primary" style="padding: 1rem 3rem; font-size: 1.2rem;">
                            一括で保存する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
