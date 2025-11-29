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
    <style>
        .dashboard-container {
            max-width: 800px;
            margin: 100px auto 60px;
            padding: 2rem;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        .dashboard-title {
            font-size: 1.8rem;
            color: #333;
        }
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        .btn-create {
            background-color: #333;
            color: white;
        }
        .btn-logout {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin: 2rem 0 1rem;
            color: #555;
            border-left: 4px solid #333;
            padding-left: 10px;
        }
        
        .event-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            transition: transform 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .event-info h3 {
            margin: 0 0 0.5rem;
            font-size: 1.2rem;
        }
        .event-date {
            color: #666;
            font-size: 0.9rem;
        }
        .btn-view {
            background-color: #e6f7ff;
            color: #0050b3;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
        }
        .no-data {
            color: #888;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.html" class="logo">WHABITAT</a>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">マイページ</h1>
                <div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="event_create.php" class="btn-action btn-create">
                            <i class="fas fa-plus"></i> イベント作成
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-action btn-logout">
                        <i class="fas fa-sign-out-alt"></i> ログアウト
                    </a>
                </div>
            </div>

            <h2 class="section-title">これからのイベント</h2>
            <?php if (empty($upcoming_events)): ?>
                <div class="no-data">予定されているイベントはありません。</div>
            <?php else: ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="event-card">
                        <div class="event-info">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-date">
                                <i class="far fa-calendar-alt"></i> 
                                <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn-view">詳細・回答</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2 class="section-title">過去のイベント</h2>
            <?php if (empty($past_events)): ?>
                <div class="no-data">過去のイベントはありません。</div>
            <?php else: ?>
                <?php foreach ($past_events as $event): ?>
                    <div class="event-card" style="opacity: 0.7;">
                        <div class="event-info">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-date">
                                <?php echo date('Y年m月d日', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn-view">確認</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
