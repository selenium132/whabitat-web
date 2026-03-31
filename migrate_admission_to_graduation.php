<?php
require_once 'config.php';
requireLogin();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDB();
$csrf_token = generateCsrfToken();
$message = '';
$migrated = [];
$preview = [];

// Fetch all users with admission_year set
$stmt = $pdo->query("SELECT id, name, grade, admission_year FROM users WHERE admission_year IS NOT NULL AND admission_year != '' ORDER BY admission_year ASC, name COLLATE utf8mb4_unicode_ci ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preview: show what will change
foreach ($users as $u) {
    $old_year_str = $u['admission_year'];
    $old_year_num = (int)str_replace('年', '', $old_year_str);
    
    // Only convert if it looks like an enrollment year (2020-2026 range)
    // Graduation years would be 2027+ so we skip those
    if ($old_year_num >= 2020 && $old_year_num <= 2026) {
        $new_year_num = $old_year_num + 4;
        $new_year_str = $new_year_num . '年';
        $new_grade = ($new_year_num > 2000) ? ($new_year_num - 2028 + 18) . 'th' : '';
        
        $preview[] = [
            'id' => $u['id'],
            'name' => $u['name'],
            'old_admission_year' => $old_year_str,
            'new_admission_year' => $new_year_str,
            'old_grade' => $u['grade'],
            'new_grade' => $new_grade,
        ];
    }
}

// Execute migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $count = 0;
    foreach ($users as $u) {
        $old_year_str = $u['admission_year'];
        $old_year_num = (int)str_replace('年', '', $old_year_str);
        
        if ($old_year_num >= 2020 && $old_year_num <= 2026) {
            $new_year_num = $old_year_num + 4;
            $new_year_str = $new_year_num . '年';
            $new_grade = ($new_year_num > 2000) ? ($new_year_num - 2028 + 18) . 'th' : '';
            
            $update = $pdo->prepare("UPDATE users SET admission_year = ?, grade = ? WHERE id = ?");
            $update->execute([$new_year_str, $new_grade, $u['id']]);
            
            $migrated[] = [
                'name' => $u['name'],
                'old' => $old_year_str,
                'new' => $new_year_str,
                'grade' => $new_grade,
            ];
            $count++;
        }
    }
    
    $message = "{$count}名の卒業年を変換しました。";
    
    // Re-fetch for updated preview
    $preview = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入学年→卒業年 変換ツール | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> ダッシュボードに戻る
            </a>
        </div>
    </header>
    <main>
        <div class="dashboard-container" style="max-width: 800px;">
            <div class="card">
                <h1 style="text-align: center; margin-bottom: 1rem;"><i class="fas fa-exchange-alt"></i> 入学年→卒業年 一括変換</h1>
                <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">
                    既存メンバーの「入学年」を「卒業予定年」（+4年）に変換します。<br>
                    <small>※対象: 2020年〜2026年の値のみ（既に卒業年に変換済みの値はスキップ）</small>
                </p>

                <?php if ($message): ?>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                    
                    <?php if (!empty($migrated)): ?>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 0.6rem; text-align: left; border-bottom: 2px solid #ddd;">名前</th>
                                    <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">変換前</th>
                                    <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;"></th>
                                    <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">変換後</th>
                                    <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">代</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($migrated as $m): ?>
                                    <tr>
                                        <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($m['name']); ?></td>
                                        <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee; color: #e74c3c; text-decoration: line-through;"><?php echo htmlspecialchars($m['old']); ?></td>
                                        <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee;">→</td>
                                        <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee; color: #27ae60; font-weight: bold;"><?php echo htmlspecialchars($m['new']); ?></td>
                                        <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($m['grade']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <div style="text-align: center;">
                        <a href="admin/members.php" class="btn-primary" style="display: inline-block;">メンバー管理で確認する</a>
                    </div>

                <?php elseif (!empty($preview)): ?>
                    <h2 style="font-size: 1.1rem; margin-bottom: 1rem;">変換プレビュー（<?php echo count($preview); ?>名）</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 0.6rem; text-align: left; border-bottom: 2px solid #ddd;">名前</th>
                                <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">現在の値（入学年）</th>
                                <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;"></th>
                                <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">変換後（卒業年）</th>
                                <th style="padding: 0.6rem; text-align: center; border-bottom: 2px solid #ddd;">代</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview as $p): ?>
                                <tr>
                                    <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($p['old_admission_year']); ?></td>
                                    <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee;">→</td>
                                    <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee; color: #27ae60; font-weight: bold;"><?php echo htmlspecialchars($p['new_admission_year']); ?></td>
                                    <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($p['new_grade']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="POST" onsubmit="return confirm('本当に全員の入学年を卒業年に変換しますか？\nこの操作は元に戻せません。');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="migrate">
                        <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; background-color: #e67e22;">
                            <i class="fas fa-sync-alt"></i> <?php echo count($preview); ?>名の卒業年を一括変換する
                        </button>
                    </form>

                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #999;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #27ae60; margin-bottom: 1rem; display: block;"></i>
                        <p>変換対象のメンバーはいません。<br>すべてのデータは既に卒業年形式です。</p>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="admin/members.php" class="btn-primary" style="display: inline-block;">メンバー管理で確認する</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
