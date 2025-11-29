<?php
require_once 'config.php';
requireLogin();

$pdo = getDB();

// Fetch Upcoming Events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Past Events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC LIMIT 5");
$past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.html" class="logo">WHABITAT</a>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="color: var(--text-light); font-size: 0.9rem;">ようこそ</div>
                    <h1 style="font-size: 1.8rem; margin: 0;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'メンバー'); ?> さん</h1>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="event_create.php" class="btn-primary">
                            <i class="fas fa-plus"></i> イベント作成
                        </a>
                        <a href="admin/members.php" class="btn-secondary">
                            <i class="fas fa-users"></i> メンバー管理
                        </a>
                        <a href="admin/messages.php" class="btn-secondary">
                            <i class="fas fa-envelope"></i> お問い合わせ
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> ログアウト
                    </a>
                </div>
            </div>

            <h2 class="section-title" style="text-align: left; margin-bottom: 1.5rem;">これからのイベント</h2>
            <?php if (empty($upcoming_events)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">
                    予定されているイベントはありません。
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; margin-bottom: 1rem;">
                        <div>
                            <div style="color: var(--secondary-color); font-weight: 600; font-size: 0.9rem;">
                                <i class="far fa-calendar-alt"></i> 
                                <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?>
                            </div>
                            <h3 style="margin: 0.5rem 0 0; font-size: 1.3rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                        <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn-secondary" style="border-radius: 50px; padding: 0.5rem 1.5rem;">
                            詳細・回答
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2 class="section-title" style="text-align: left; margin: 3rem 0 1.5rem;">過去のイベント</h2>
            <?php if (empty($past_events)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">
                    過去のイベントはありません。
                </div>
            <?php else: ?>
                <?php foreach ($past_events as $event): ?>
                    <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; margin-bottom: 1rem; opacity: 0.8;">
                        <div>
                            <div style="color: var(--text-light); font-size: 0.9rem;">
                                <?php echo date('Y年m月d日', strtotime($event['event_date'])); ?>
                            </div>
                            <h3 style="margin: 0.2rem 0 0; font-size: 1.1rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                        <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem;">
                            確認
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
