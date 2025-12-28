<?php
require_once 'config.php';
requireLogin();

$pdo = getDB();

// Check if profile is complete - redirect if any required field is missing
$stmt = $pdo->prepare("SELECT name, student_id, grade, faculty FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($user_profile['name']) || empty($user_profile['student_id']) || empty($user_profile['grade']) || empty($user_profile['faculty'])) {
    header("Location: register_profile.php");
    exit;
}

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
        $unread_count = 0;
    }
}

// Calendar month navigation
$cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');
$cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');

// Validate and normalize month
if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1; $cal_year++; }

// Fetch calendar events for selected month
$calendar_events = [];
try {
    $month_str = sprintf('%04d-%02d', $cal_year, $cal_month);
    $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE DATE_FORMAT(event_date, '%Y-%m') = ? ORDER BY event_date ASC");
    $stmt->execute([$month_str]);
    $calendar_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $calendar_events = [];
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
                            <?php if (!empty($event['open_at']) || !empty($event['close_at']) || !empty($event['capacity'])): ?>
                                <div style="margin-top: 8px; font-size: 0.8rem; color: #666; display: flex; flex-wrap: wrap; gap: 12px;">
                                    <?php if (!empty($event['open_at'])): ?>
                                        <span><i class="fas fa-play-circle" style="color: #28a745;"></i> 開始: <?php echo date('m/d H:i', strtotime($event['open_at'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['close_at'])): ?>
                                        <span><i class="fas fa-stop-circle" style="color: #dc3545;"></i> 締切: <?php echo date('m/d H:i', strtotime($event['close_at'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['capacity'])): ?>
                                        <span><i class="fas fa-users" style="color: #007bff;"></i> 定員: <?php echo $event['capacity']; ?>名</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <a href="event_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem;">
                                回答一覧
                            </a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="event_delete.php?id=<?php echo $event['id']; ?>" 
                                   onclick="return confirm('このイベントを削除しますか？\nこの操作は取り消せません。');"
                                   class="btn-secondary" 
                                   style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #dc3545; color: white; border-color: #dc3545;"
                                   title="削除">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
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

            <!-- わびカレンダー -->
            <h2 id="calendar" class="section-title" style="text-align: left; margin: 3rem 0 1.5rem;">📅 わびカレンダー</h2>
            <div class="card" style="padding: 0; overflow: hidden;">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div style="padding: 1rem 1.5rem 0; text-align: right;">
                        <a href="admin/calendar.php" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                            <i class="fas fa-plus"></i> 予定追加
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php
                // Calculate prev/next month
                $prev_month = $cal_month - 1;
                $prev_year = $cal_year;
                if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                $next_month = $cal_month + 1;
                $next_year = $cal_year;
                if ($next_month > 12) { $next_month = 1; $next_year++; }
                $is_current_month = ($cal_year == date('Y') && $cal_month == date('n'));
                ?>
                
                <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <a href="?cal_year=<?php echo $prev_year; ?>&cal_month=<?php echo $prev_month; ?>#calendar" style="color: var(--primary-color); text-decoration: none; padding: 8px;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <div style="text-align: center;">
                        <h3 style="font-size: 1.5rem; font-weight: 700; margin: 0;"><?php echo $cal_month; ?>月</h3>
                        <p style="color: #888; font-size: 0.85rem; margin: 0;"><?php echo $cal_year; ?>年</p>
                    </div>
                    <a href="?cal_year=<?php echo $next_year; ?>&cal_month=<?php echo $next_month; ?>#calendar" style="color: var(--primary-color); text-decoration: none; padding: 8px;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <?php if (!$is_current_month): ?>
                <div style="text-align: center; padding-bottom: 0.5rem;">
                    <a href="dashboard.php#calendar" style="font-size: 0.8rem; color: var(--primary-color);">今月に戻る</a>
                </div>
                <?php endif; ?>
                
                <?php
                $first_day = mktime(0, 0, 0, $cal_month, 1, $cal_year);
                $days_in_month = date('t', $first_day);
                $start_day = date('w', $first_day);
                
                $events_by_date = [];
                foreach ($calendar_events as $ev) {
                    $day = (int)date('j', strtotime($ev['event_date']));
                    if (!isset($events_by_date[$day])) $events_by_date[$day] = [];
                    $events_by_date[$day][] = $ev;
                }
                
                // Build weeks array
                $weeks = [];
                $current_week = array_fill(0, 7, null);
                $day_counter = 1;
                
                for ($i = $start_day; $i < 7 && $day_counter <= $days_in_month; $i++) {
                    $current_week[$i] = $day_counter++;
                }
                $weeks[] = $current_week;
                
                while ($day_counter <= $days_in_month) {
                    $current_week = array_fill(0, 7, null);
                    for ($i = 0; $i < 7 && $day_counter <= $days_in_month; $i++) {
                        $current_week[$i] = $day_counter++;
                    }
                    $weeks[] = $current_week;
                }
                ?>
                
                <!-- Day headers -->
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); border-top: 1px solid #eee; border-bottom: 1px solid #eee; background: #f8f9fa;">
                    <?php foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $d): ?>
                        <div style="padding: 8px 4px; text-align: center; font-size: 0.75rem; font-weight: 500; color: <?php echo $i === 0 ? '#dc3545' : ($i === 6 ? '#007bff' : '#888'); ?>;"><?php echo $d; ?></div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Week rows -->
                <?php foreach ($weeks as $week): ?>
                <div style="border-bottom: 1px solid #eee;">
                    <!-- Date row -->
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr);">
                        <?php foreach ($week as $i => $day): ?>
                            <div style="padding: 8px 4px; text-align: center; min-height: 30px;">
                                <?php if ($day): 
                                    $is_today = ($is_current_month && $day == date('j'));
                                ?>
                                    <span style="<?php if ($is_today): ?>background: var(--primary-color); color: white; border-radius: 50%; padding: 4px 8px; font-weight: 600;<?php endif; ?> <?php echo $i === 0 ? 'color: #dc3545;' : ($i === 6 ? 'color: #007bff;' : ''); ?>"><?php echo $day; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Events row -->
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); padding-bottom: 6px;">
                        <?php foreach ($week as $i => $day): ?>
                            <div style="padding: 0 2px;">
                                <?php if ($day && isset($events_by_date[$day])): ?>
                                    <?php foreach ($events_by_date[$day] as $ev): ?>
                                        <div style="background: <?php echo htmlspecialchars($ev['color'] ?? 'var(--primary-color)'); ?>; color: white; font-size: 0.65rem; padding: 2px 4px; border-radius: 3px; margin-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($ev['title']); ?>">
                                            <?php echo htmlspecialchars(mb_substr($ev['title'], 0, 6)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Event list -->
                <div style="padding: 1rem 1.5rem; background: #f8f9fa; min-height: 120px;">
                    <h4 style="font-size: 0.85rem; margin-bottom: 0.5rem; color: #666;"><?php echo $cal_month; ?>月の予定</h4>
                    <?php if (!empty($calendar_events)): ?>
                        <?php foreach ($calendar_events as $ev): ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 6px 0;">
                                <span style="width: 8px; height: 8px; border-radius: 2px; background: <?php echo htmlspecialchars($ev['color'] ?? 'var(--primary-color)'); ?>; flex-shrink: 0;"></span>
                                <span style="font-size: 0.8rem; color: #666; min-width: 35px;"><?php echo date('n/j', strtotime($ev['event_date'])); ?></span>
                                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($ev['title']); ?></span>
                                <?php if (!($ev['is_all_day'] ?? true) && !empty($ev['start_time'])): ?>
                                    <span style="font-size: 0.75rem; color: #888;"><?php echo date('H:i', strtotime($ev['start_time'])); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; font-size: 0.85rem; margin: 0;">予定はありません</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
