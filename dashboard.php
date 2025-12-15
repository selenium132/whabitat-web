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

// Count unread messages (for admin badge)
$unread_count = 0;
if ($_SESSION['role'] === 'admin') {
    try {
        $unread_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    } catch (Exception $e) {
        // Column might not exist yet, ignore
        $unread_count = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="WHABITAT" height="50">
            </a>
            <div class="user-menu">
                <a href="logout.php" class="header-logout-btn" title="ログアウト">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="card welcome-card">
                <div class="welcome-text">
                    <div class="welcome-label">ようこそ</div>
                    <h1 class="welcome-user"><?php echo htmlspecialchars($_SESSION['name'] ?? 'メンバー'); ?> さん</h1>
                </div>
                <div class="welcome-actions">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="event_create.php" class="btn-primary">
                            <i class="fas fa-plus"></i> イベント作成
                        </a>
                        <a href="admin/members.php" class="btn-secondary">
                            <i class="fas fa-users"></i> メンバー管理
                        </a>
                        <a href="admin/messages.php" class="btn-secondary" style="position: relative;">
                            <i class="fas fa-envelope"></i> お問い合わせ
                            <?php if ($unread_count > 0): ?>
                                <span style="position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: 600;"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    
                    <a href="register_profile.php" class="btn-secondary">
                        <i class="fas fa-user-edit"></i> プロフィール編集
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
                    <div class="card event-card">
                        <div class="event-info">
                            <div class="event-date">
                                <i class="far fa-calendar-alt"></i> 
                                <?php echo date('Y年m月d日 H:i', strtotime($event['event_date'])); ?>
                            </div>
                            <h3 class="event-title-text"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                        <div class="event-actions">
                            <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn-primary btn-answer">
                                回答する
                            </a>
                            <a href="event_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-status">
                                回答状況
                            </a>
                            
                            <?php if (isEventAdmin($event['id'])): ?>
                                <a href="event_create.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-edit" title="編集">
                                    <i class="far fa-edit"></i>
                                </a>
                            <?php endif; ?>
                        </div>
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
                        <div style="display: flex; gap: 5px;">
                            <a href="event_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem;">
                                回答一覧
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 目安箱 (Suggestion Box) -->
            <h2 class="section-title" style="text-align: left; margin: 3rem 0 1.5rem;">📮 目安箱</h2>
            <div class="card" style="padding: 2rem;">
                <?php if (isset($_SESSION['suggestion_success']) && $_SESSION['suggestion_success']): ?>
                    <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        ✅ 送信しました！ご意見ありがとうございます。
                    </div>
                    <?php unset($_SESSION['suggestion_success']); ?>
                <?php endif; ?>
                <p style="color: var(--text-light); margin-bottom: 1rem; font-size: 0.95rem;">
                    サークルへのご意見・ご要望があればお気軽にどうぞ！<br>
                    <span style="font-size: 0.85rem;">💡 名前を書かなければ「匿名」で送信されます。</span>
                </p>
                <form action="suggestion_submit.php" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div>
                        <label for="suggestion_name" style="font-weight: 500; display: block; margin-bottom: 0.3rem;">名前（任意）</label>
                        <input type="text" id="suggestion_name" name="name" placeholder="書かなければ匿名で送信されます" 
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label for="suggestion_content" style="font-weight: 500; display: block; margin-bottom: 0.3rem;">内容 <span style="color: #dc3545;">*</span></label>
                        <textarea id="suggestion_content" name="message" rows="4" required placeholder="ご意見・ご要望をお書きください..."
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; resize: vertical; box-sizing: border-box;"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="align-self: flex-start; padding: 0.8rem 2rem; border-radius: 50px; border: none; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-paper-plane"></i> 送信する
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
