<?php
require_once '../config.php';
requireLogin();

// Only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$success = '';

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS mtg_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    year_group INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $event_date = $_POST['event_date'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $year_group = intval($_POST['year_group'] ?? date('Y'));
        
        // Handle image upload
        $image_path = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = '../uploads/mtg/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'mtg_' . date('Y_m_d_His') . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $image_path = 'uploads/mtg/' . $filename;
            }
        }
        
        if ($title && $event_date) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO mtg_history (event_date, title, subtitle, description, image_path, year_group) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$event_date, $title, $subtitle, $description, $image_path, $year_group]);
                $success = 'MTG履歴を追加しました。';
            } else {
                $stmt = $pdo->prepare("UPDATE mtg_history SET event_date = ?, title = ?, subtitle = ?, description = ?, image_path = ?, year_group = ? WHERE id = ?");
                $stmt->execute([$event_date, $title, $subtitle, $description, $image_path, $year_group, $id]);
                $success = 'MTG履歴を更新しました。';
            }
        } else {
            $error = 'タイトルと日付は必須です。';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM mtg_history WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'MTG履歴を削除しました。';
    }
}

// Fetch all entries
$entries = $pdo->query("SELECT * FROM mtg_history ORDER BY year_group DESC, event_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Edit mode
$edit_entry = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM mtg_history WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_entry = $stmt->fetch(PDO::FETCH_ASSOC);
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTG履歴管理 | WHABITAT</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .admin-container { max-width: 900px; margin: 0 auto; padding: 20px; padding-top: 100px; }
        .form-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-input, .form-textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .form-textarea { min-height: 100px; resize: vertical; }
        .btn-submit { background: var(--primary-color); color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px; text-decoration: none; }
        .entry-list { display: grid; gap: 15px; }
        .entry-item { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 5px rgba(0,0,0,0.08); display: flex; gap: 15px; align-items: center; }
        .entry-item img { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; }
        .entry-info { flex: 1; }
        .entry-title { font-weight: 600; color: #333; }
        .entry-date { font-size: 0.85rem; color: #666; }
        .entry-actions { display: flex; gap: 8px; }
        .btn-edit { background: #667eea; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .btn-delete { background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
    </style>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo" style="font-size: 1rem;">
                <i class="fas fa-chevron-left"></i> ダッシュボード
            </a>
        </div>
    </header>

    <div class="admin-container">
        <h1 style="margin-bottom: 30px;">MTG履歴管理</h1>
        
        <?php if ($success): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <div class="form-card">
            <h3><?php echo $edit_entry ? 'MTG履歴を編集' : '新しいMTG履歴を追加'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="<?php echo $edit_entry ? 'edit' : 'add'; ?>">
                <?php if ($edit_entry): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_entry['id']; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_entry['image_path']); ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">日付 *</label>
                        <input type="date" name="event_date" class="form-input" required value="<?php echo htmlspecialchars($edit_entry['event_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">年度グループ *</label>
                        <input type="number" name="year_group" class="form-input" required value="<?php echo htmlspecialchars($edit_entry['year_group'] ?? date('Y')); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">タイトル *</label>
                    <input type="text" name="title" class="form-input" required placeholder="例: わびチューン" value="<?php echo htmlspecialchars($edit_entry['title'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">サブタイトル</label>
                    <input type="text" name="subtitle" class="form-input" placeholder="例: 〜3人の美食家を添えて〜" value="<?php echo htmlspecialchars($edit_entry['subtitle'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">説明</label>
                    <textarea name="description" class="form-textarea" placeholder="MTGの内容を記載..."><?php echo htmlspecialchars($edit_entry['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">画像<?php echo $edit_entry ? ' (変更する場合のみ)' : ''; ?></label>
                    <?php if ($edit_entry && $edit_entry['image_path']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="../<?php echo htmlspecialchars($edit_entry['image_path']); ?>" style="max-width: 150px; border-radius: 6px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*" class="form-input">
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-submit">
                        <?php echo $edit_entry ? '更新する' : '追加する'; ?>
                    </button>
                    <?php if ($edit_entry): ?>
                        <a href="mtg_history.php" class="btn-cancel">キャンセル</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Entry List -->
        <h3 style="margin-bottom: 15px;">登録済みMTG履歴</h3>
        <div class="entry-list">
            <?php if (empty($entries)): ?>
                <p style="color: #666;">まだMTG履歴がありません。</p>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <div class="entry-item">
                        <?php if ($entry['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($entry['image_path']); ?>" alt="">
                        <?php else: ?>
                            <div style="width: 80px; height: 60px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        <div class="entry-info">
                            <div class="entry-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                            <div class="entry-date"><?php echo $entry['year_group'] . ' / ' . date('Y.m.d', strtotime($entry['event_date'])); ?></div>
                        </div>
                        <div class="entry-actions">
                            <a href="?edit=<?php echo $entry['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="../activity_mtg.php" class="back-link"><i class="fas fa-external-link-alt"></i> MTGページを見る</a>
        </div>
    </div>
</body>
</html>
