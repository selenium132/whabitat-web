<?php
require_once 'config.php';
requireLogin();

$pdo = getDB();

// Ensure is_archived column exists
try {
    $pdo->exec("ALTER TABLE events ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // Column already exists
}

// Fetch past events (date passed OR manually archived)
$stmt = $pdo->prepare("SELECT * FROM events WHERE (event_date < NOW() OR is_archived = 1) ORDER BY event_date DESC");
$stmt->execute();
$past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>過去のイベント | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <div class="user-menu">
                <a href="dashboard.php" class="header-logout-btn" title="マイページに戻る">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 1.5rem;">
                <h2 class="section-title" style="margin: 0;">過去のイベント</h2>
                <a href="dashboard.php#events" style="font-size: 0.85rem; color: #888; text-decoration: none;">← マイページに戻る</a>
            </div>
            
            <?php if (empty($past_events)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">
                    過去のイベントはありません。
                </div>
            <?php else: ?>
                <?php foreach ($past_events as $event): ?>
                    <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; margin-bottom: 1rem; opacity: 0.9;">
                        <div>
                            <div style="color: var(--text-light); font-size: 0.9rem;">
                                <?php if (($event['type'] ?? 'event') === 'survey'): ?>
                                    <span style="background: #6c5ce7; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-right: 8px;">アンケート</span>
                                <?php else: ?>
                                    <span style="background: #0984e3; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-right: 8px;">出欠確認</span>
                                    <?php echo date('Y年m月d日', strtotime($event['event_date'])); ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['is_archived']) && strtotime($event['event_date']) >= strtotime('today')): ?>
                                    <span style="background: #6c757d; color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">アーカイブ済</span>
                                <?php endif; ?>
                            </div>
                            <h3 style="margin: 0.2rem 0 0; font-size: 1.1rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <a href="form_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem;">
                                回答一覧
                            </a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <?php if (!empty($event['is_archived'])): ?>
                                    <form method="POST" action="form_archive.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <input type="hidden" name="action" value="unarchive">
                                        <input type="hidden" name="return" value="past_events.php">
                                        <?php $unarchiveMsg = (($event['type'] ?? 'event') === 'survey') ? 'このアンケートを一覧に戻しますか？' : 'この出欠確認を一覧に戻しますか？'; ?>
                                        <button type="submit" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #28a745; color: white; border: none; cursor: pointer;" title="一覧に戻す" onclick="return confirm('<?php echo $unarchiveMsg; ?>')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="form_delete.php" style="display: inline;" onsubmit="return confirm('このイベントを削除しますか？\nこの操作は取り消せません。');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn-secondary" 
                                       style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #dc3545; color: white; border-color: #dc3545; border: none; cursor: pointer;"
                                       title="削除">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
