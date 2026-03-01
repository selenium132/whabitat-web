<?php
require_once 'config.php';
requireLogin();

$pdo = getDB();

// Check if profile is complete - redirect if any required field is missing
$stmt = $pdo->prepare("SELECT name, student_id, grade, faculty, gender FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($user_profile['name']) || empty($user_profile['student_id']) || empty($user_profile['grade']) || empty($user_profile['faculty']) || empty($user_profile['gender'])) {
    header("Location: register_profile.php");
    exit;
}

// Ensure is_archived column exists
try {
    $pdo->exec("ALTER TABLE events ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // Column already exists
}

// Fetch Upcoming Events (exclude archived)
$stmt = $pdo->query("SELECT * FROM events WHERE is_archived = 0 AND (event_date >= CURDATE() OR (type = 'survey' AND (close_at IS NULL OR close_at >= NOW()))) ORDER BY event_date ASC, open_at ASC");
$all_upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attend_checks = [];
$surveys = [];

// Fetch event_admins for the user to check if they are survey admin
$user_admin_events = [];
try {
    $admin_stmt = $pdo->prepare("SELECT event_id FROM event_admins WHERE user_id = ?");
    $admin_stmt->execute([$_SESSION['user_id']]);
    $user_admin_events = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist
}

foreach ($all_upcoming as $ev) {
    // Default to 'event' if type is not set (backwards compatibility)
    $type = $ev['type'] ?? 'event';
    if ($type === 'survey') {
        // Surveys visibility: admin, creator, event_admin, target_users, or has viewed
        $can_see = false;
        
        if ($_SESSION['role'] === 'admin') {
            $can_see = true;
        } elseif ($ev['created_by'] == $_SESSION['user_id']) {
            $can_see = true;
        } elseif (in_array($ev['id'], $user_admin_events)) {
            $can_see = true;
        } elseif (!empty($ev['target_users'])) {
            // If target_users is set, show to those users
            $targets = json_decode($ev['target_users'], true);
            if (is_array($targets) && in_array($_SESSION['user_id'], $targets)) {
                $can_see = true;
            }
        }
        
        if ($can_see) {
            $surveys[] = $ev;
        }
    } else {
        $attend_checks[] = $ev;
    }
}

// Fetch user's responses for all upcoming events
$user_responses = [];
try {
    $event_ids = array_column($all_upcoming, 'id');
    if (!empty($event_ids)) {
        $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
        $resp_stmt = $pdo->prepare("SELECT event_id, status FROM attendance WHERE user_id = ? AND event_id IN ($placeholders)");
        $resp_stmt->execute(array_merge([$_SESSION['user_id']], $event_ids));
        foreach ($resp_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $user_responses[$r['event_id']] = $r['status'];
        }
    }
} catch (Exception $e) {}

// Fetch Past Events (include archived even if date is future)
$stmt = $pdo->query("SELECT * FROM events WHERE (event_date < CURDATE() OR is_archived = 1) ORDER BY event_date DESC LIMIT 5");
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

// Calendar: show 12 months from current month (or past if requested)
$view_past = isset($_GET['view_past']) ? true : false;

// Fetch calendar events for 12 months
$calendar_events_all = [];
try {
    $start_date = $view_past ? date('Y-m-d', strtotime('-12 months')) : date('Y-m-01');
    $end_date = $view_past ? date('Y-m-d') : date('Y-m-d', strtotime('+12 months'));
    
    $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE event_date <= ? AND COALESCE(end_date, event_date) >= ? ORDER BY event_date ASC, start_time ASC");
    $stmt->execute([$end_date, $start_date]);
    $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter: hide 幹部関連 (red #dc3545) events for non-admin users
    foreach ($all_events as $ev) {
        if ($ev['color'] === '#dc3545' && $_SESSION['role'] !== 'admin') {
            continue;
        }
        $calendar_events_all[] = $ev;
    }
} catch (Exception $e) {
    $calendar_events_all = [];
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
            
            <!-- Mobile menu toggle -->
            <button class="menu-toggle" aria-label="メニュー">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- Nav (shared for desktop/mobile via CSS) -->
            <nav>
                <ul class="nav-list">
                    <li><a href="#events" class="nav-link">出欠確認</a></li>
                    <li><a href="#surveys" class="nav-link">アンケート</a></li>
                    <li><a href="#calendar" class="nav-link">カレンダー</a></li>
                    <li><a href="#suggestion" class="nav-link">目安箱</a></li>
                    <li><a href="logout.php" class="nav-link" style="color: var(--text-color);"><i class="fas fa-sign-out-alt"></i></a></li>
                </ul>
            </nav>
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
                    <a href="form_create.php?type=survey" class="btn-primary">
                        <i class="fas fa-plus"></i> アンケート作成
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="form_create.php" class="btn-primary">
                            <i class="fas fa-plus"></i> 出欠確認作成
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

            <div id="events" style="display: flex; align-items: center; gap: 16px; margin-bottom: 1.5rem; scroll-margin-top: 80px;">
                <h2 class="section-title" style="text-align: left; margin: 0;">出欠確認</h2>
                <a href="past_events.php" style="font-size: 0.85rem; color: #888; text-decoration: none;">過去の出欠 →</a>
            </div>
            <?php if (empty($attend_checks)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">
                    予定されている出欠確認はありません。
                </div>
            <?php else: ?>
                <?php foreach ($attend_checks as $event): ?>
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
                        <div class="event-actions-wrap">
                            <div class="event-actions">
                                <?php $answered = isset($user_responses[$event['id']]); ?>
                                <a href="form_view.php?id=<?php echo $event['id']; ?>" class="<?php echo $answered ? 'btn-secondary' : 'btn-primary'; ?> btn-answer" <?php if ($answered) echo 'style="border:2px solid #28a745;color:#28a745;background:#f0fff4;"'; ?>>
                                    <?php echo $answered ? '<i class="fas fa-check" style="margin-right:4px"></i>回答を変更' : '回答する'; ?>
                                </a>
                                <a href="form_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-status">
                                    回答状況
                                </a>
                            </div>
                            <div class="event-icons">
                                <?php if (isEventAdmin($event['id'])): ?>
                                    <a href="form_create.php?id=<?php echo $event['id']; ?>" class="icon-btn" title="編集">
                                        <i class="far fa-edit"></i><span>編集</span>
                                    </a>
                                    <form method="POST" action="form_archive.php" style="display:contents;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="return" value="dashboard.php#events">
                                        <button type="submit" class="icon-btn" title="過去のイベントに移動" onclick="return confirm('このイベントを過去のイベントに移動しますか？')">
                                            <i class="fas fa-archive"></i><span>アーカイブ</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="icon-btn" title="URLをコピー" onclick="copyEventUrl(<?php echo $event['id']; ?>)">
                                    <i class="fas fa-link"></i><span>コピー</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- アンケート Section -->
            <div id="surveys" style="display: flex; align-items: center; gap: 16px; margin: 3rem 0 1.5rem; scroll-margin-top: 80px;">
                <h2 class="section-title" style="text-align: left; margin: 0;">アンケート</h2>
            </div>
            <?php if (empty($surveys)): ?>
                <div class="card" style="text-align: center; color: var(--text-light);">
                    現在実施中のアンケートはありません。
                </div>
            <?php else: ?>
                <?php foreach ($surveys as $event): ?>
                    <div class="card event-card">
                        <div class="event-info">
                            <div class="event-date">
                                <i class="fas fa-poll-h"></i> アンケート
                            </div>
                            <h3 class="event-title-text"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                        <div class="event-actions-wrap">
                            <div class="event-actions">
                                <?php $answered_s = isset($user_responses[$event['id']]); ?>
                                <a href="form_view.php?id=<?php echo $event['id']; ?>" class="<?php echo $answered_s ? 'btn-secondary' : 'btn-primary'; ?> btn-answer" <?php if ($answered_s) echo 'style="border:2px solid #28a745;color:#28a745;background:#f0fff4;"'; ?>>
                                    <?php echo $answered_s ? '<i class="fas fa-check" style="margin-right:4px"></i>回答を変更' : '回答する'; ?>
                                </a>
                                <?php if ($_SESSION['role'] === 'admin' || $event['created_by'] == $_SESSION['user_id'] || in_array($event['id'], $user_admin_events)): ?>
                                <a href="form_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-status">
                                    回答状況
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="event-icons">
                                <?php if (isEventAdmin($event['id'])): ?>
                                    <a href="form_create.php?id=<?php echo $event['id']; ?>" class="icon-btn" title="編集">
                                        <i class="far fa-edit"></i><span>編集</span>
                                    </a>
                                    <form method="POST" action="form_archive.php" style="display:contents;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="return" value="dashboard.php#surveys">
                                        <button type="submit" class="icon-btn" title="過去のアンケートに移動" onclick="return confirm('このアンケートをアーカイブしますか？')">
                                            <i class="fas fa-archive"></i><span>アーカイブ</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="icon-btn" title="URLをコピー" onclick="copyEventUrl(<?php echo $event['id']; ?>)">
                                    <i class="fas fa-link"></i><span>コピー</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- わびカレンダー -->
            <h2 id="calendar" class="section-title" style="text-align: left; margin: 3rem 0 1rem; scroll-margin-top: 80px;">📅 わびカレンダー</h2>
            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 1rem; font-size: 0.75rem;">
                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #667eea; border-radius: 2px;"></span>イベント</span>
                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #28a745; border-radius: 2px;"></span>派遣</span>
                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #17a2b8; border-radius: 2px;"></span>mtg</span>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #dc3545; border-radius: 2px;"></span>🔒幹部関連</span>
                <?php endif; ?>
                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #6c757d; border-radius: 2px;"></span>その他</span>
            </div>
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee;">
                    <?php if ($view_past): ?>
                        <a href="dashboard.php#calendar" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                            <i class="fas fa-arrow-left"></i> 今後の予定へ
                        </a>
                        <span style="font-weight: 600;">過去のカレンダー</span>
                    <?php else: ?>
                        <a href="?view_past=1#calendar" style="font-size: 0.8rem; color: #888; text-decoration: none;">
                            <i class="fas fa-history"></i> 過去のカレンダー
                        </a>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button onclick="openCalendarModal()" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; border: none; cursor: pointer;">
                            <i class="fas fa-plus"></i> 予定追加
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php
                // Generate 12 months
                $base_year = (int)date('Y');
                $base_month = (int)date('n');
                
                if ($view_past) {
                    // Past: from 12 months ago to last month
                    $months_to_show = [];
                    for ($i = 12; $i >= 1; $i--) {
                        $m = $base_month - $i;
                        $y = $base_year;
                        while ($m < 1) { $m += 12; $y--; }
                        $months_to_show[] = ['year' => $y, 'month' => $m];
                    }
                } else {
                    // Future: from current month to 11 months ahead
                    $months_to_show = [];
                    for ($i = 0; $i < 12; $i++) {
                        $m = $base_month + $i;
                        $y = $base_year;
                        while ($m > 12) { $m -= 12; $y++; }
                        $months_to_show[] = ['year' => $y, 'month' => $m];
                    }
                }
                
                // Index all events by date (Y-m-d) - handling multi-day events
                $events_by_full_date = [];
                foreach ($calendar_events_all as $ev) {
                    $start_str = $ev['event_date'];
                    $end_str = $ev['end_date'] ?? $start_str;
                    
                    try {
                        $start = new DateTime($start_str);
                        $end = new DateTime($end_str);
                        $end->modify('+1 day'); // inclusive end date
                        
                        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
                        
                        foreach ($period as $dt) {
                            $d_key = $dt->format('Y-m-d');
                            if (!isset($events_by_full_date[$d_key])) {
                                $events_by_full_date[$d_key] = [];
                            }
                            
                            // Add flags for styling
                            $day_ev = $ev;
                            $day_ev['is_start_day'] = ($d_key === $start_str);
                            $day_ev['is_end_day'] = ($d_key === $end_str);
                            
                            $events_by_full_date[$d_key][] = $day_ev;
                        }
                    } catch (Exception $e) {
                        // Fallback for invalid dates
                        if (!isset($events_by_full_date[$start_str])) $events_by_full_date[$start_str] = [];
                        $ev['is_start_day'] = true;
                        $ev['is_end_day'] = true;
                        $events_by_full_date[$start_str][] = $ev;
                    }
                }
                ?>
                
                <div id="calendarScroll" style="max-height: 450px; overflow-y: auto;">
                <?php
                foreach ($months_to_show as $mon):
                    $cal_year = $mon['year'];
                    $cal_month = $mon['month'];
                    $first_day = mktime(0, 0, 0, $cal_month, 1, $cal_year);
                    $days_in_month = date('t', $first_day);
                    $start_day = date('w', $first_day);
                    $is_current_month = ($cal_year == date('Y') && $cal_month == date('n'));
                    
                    // Build weeks
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
                    
                    // Get events for this month (checking overlap for multi-day events)
                    $month_events = [];
                    $month_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
                    $month_end = date('Y-m-t', strtotime($month_start));
                    
                    foreach ($calendar_events_all as $ev) {
                        $ev_start = $ev['event_date'];
                        $ev_end = $ev['end_date'] ?? $ev['event_date'];
                        
                        // Check overlap: Event Start <= Month End AND Event End >= Month Start
                        if ($ev_start <= $month_end && $ev_end >= $month_start) {
                            $month_events[] = $ev;
                        }
                    }
                ?>
                
                <div style="padding: 1rem 1.5rem; border-bottom: 2px solid #eee;">
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0;"><?php echo $cal_month; ?>月 <span style="font-size: 0.9rem; color: #888; font-weight: 400;"><?php echo $cal_year; ?>年</span></h3>
                </div>
                
                <!-- Day headers -->
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); background: #f8f9fa; border-bottom: 1px solid #eee;">
                    <?php foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $d): ?>
                        <div style="padding: 6px 4px; text-align: center; font-size: 0.7rem; font-weight: 500; color: <?php echo $i === 0 ? '#dc3545' : ($i === 6 ? '#007bff' : '#888'); ?>;"><?php echo $d; ?></div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Week rows -->
                <?php foreach ($weeks as $week): ?>
                <?php
                    // --- Pre-calculate layout for this week ---
                    
                    // 1. Identify valid days and date mapping
                    $day_date_map = []; // [col_idx => 'YYYY-MM-DD']
                    $valid_cols = [];
                    foreach($week as $col_idx => $day_num) {
                        if($day_num !== null) {
                            $day_date_map[$col_idx] = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day_num);
                            $valid_cols[] = $col_idx;
                        }
                    }
                    
                    if (empty($valid_cols)) continue;

                    // 2. Find all events overlapping with this week's visible days
                    $week_events_list = [];
                    // Optimization: Filter from $calendar_events_all
                    // Note: This relies on $calendar_events_all being populated correctly
                    foreach ($calendar_events_all as $ev) {
                        $ev_start = $ev['event_date'];
                        $ev_end = $ev['end_date'] ?? $ev['event_date'];
                        
                        // Check overlap with week range
                        $week_start_date = reset($day_date_map);
                        $week_end_date = end($day_date_map);
                        
                        // Overlap: StartA <= EndB AND EndA >= StartB
                        if ($ev_start <= $week_end_date && $ev_end >= $week_start_date) {
                            $week_events_list[] = $ev;
                        }
                    }
                    
                    // 3. Sort events: Earlier start first, then longer duration
                    usort($week_events_list, function($a, $b) {
                        if ($a['event_date'] != $b['event_date']) {
                            return strcmp($a['event_date'], $b['event_date']);
                        }
                        $a_len = strtotime($a['end_date'] ?? $a['event_date']) - strtotime($a['event_date']);
                        $b_len = strtotime($b['end_date'] ?? $b['event_date']) - strtotime($b['event_date']);
                        return $b_len - $a_len; // Descending duration
                    });

                    // 4. Assign slots (Greedy packing)
                    $slots = []; // [row][col] = {event, span, is_start...}
                    // Initialize empty slots for max distinct events (safe upper bound 50)
                    for($r=0; $r<50; $r++) { 
                         for($c=0; $c<7; $c++) $slots[$r][$c] = null;
                    }
                    
                    $max_row_used = -1;

                    foreach($week_events_list as $ev) {
                        $ev_start = $ev['event_date'];
                        $ev_end = $ev['end_date'] ?? $ev['event_date'];

                        // Determine occupied columns in this week
                        $cols_occupied = [];
                        foreach($day_date_map as $c => $d_date) {
                            if ($d_date >= $ev_start && $d_date <= $ev_end) {
                                $cols_occupied[] = $c;
                            }
                        }
                        
                        if (empty($cols_occupied)) continue;
                        
                        $c_start = min($cols_occupied);
                        $c_end = max($cols_occupied);
                        
                        // Find first available row
                        $row = 0;
                        while(true) {
                            $fit = true;
                            for($c=$c_start; $c<=$c_end; $c++) {
                                if ($slots[$row][$c] !== null) {
                                    $fit = false;
                                    break;
                                }
                            }
                            if ($fit) break;
                            $row++;
                        }
                        
                        $max_row_used = max($max_row_used, $row);
                        
                        // Record slot data
                        $total_span = $c_end - $c_start + 1;
                        for($c=$c_start; $c<=$c_end; $c++) {
                            $slots[$row][$c] = [
                                'id' => $ev['id'],
                                'title' => $ev['title'],
                                'color' => $ev['color'] ?? 'var(--primary-color)',
                                'is_visual_start' => ($c == $c_start),
                                'total_span' => $total_span,
                                'is_real_start' => ($day_date_map[$c] === $ev['event_date']),
                                'is_real_end' => ($day_date_map[$c] === $ev['end_date'] ?? $ev['event_date']),
                            ];
                        }
                    }
                ?>
                
                <div style="border-bottom: 1px solid #f0f0f0;">
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr);">
                        <?php foreach ($week as $i => $day): ?>
                            <div style="padding: 6px 0; text-align: center; min-height: 28px; position: relative; z-index: <?php echo 20 - $i; ?>; <?php if ($day && $_SESSION['role'] === 'admin'): ?>cursor: pointer;<?php endif; ?>"
                                 <?php if ($day && $_SESSION['role'] === 'admin'): ?>onclick="openCalendarModalWithDate(<?php echo $cal_year; ?>, <?php echo $cal_month; ?>, <?php echo $day; ?>)"<?php endif; ?>>
                                <?php if ($day): 
                                    $is_today = ($is_current_month && $day == date('j'));
                                    ?>
                                    <span style="<?php if ($is_today): ?>background: var(--primary-color); color: white; border-radius: 50%; padding: 3px 7px; font-weight: 600;<?php endif; ?> <?php echo $i === 0 ? 'color: #dc3545;' : ($i === 6 ? 'color: #007bff;' : ''); ?> font-size: 0.85rem;"><?php echo $day; ?></span>
                                    
                                    <div style="margin-top: 2px; display: flex; flex-direction: column; gap: 1px;">
                                        <?php for($r=0; $r<=$max_row_used; $r++): ?>
                                            <?php 
                                            $slot = $slots[$r][$i] ?? null; 
                                            ?>
                                            <?php if ($slot): ?>
                                                <?php if ($slot['is_visual_start']): ?>
                                                    <div onclick="event.stopPropagation(); <?php if ($_SESSION['role'] === 'admin'): ?>editCalendarEvent(<?php echo $slot['id']; ?>)<?php endif; ?>" 
                                                         class="event-bar <?php echo ($slot['is_real_start']?'is-start':'') . ' ' . ($slot['is_real_end']?'is-end':''); ?>"
                                                         style="background: <?php echo htmlspecialchars($slot['color']); ?>; height: 16px; position: relative; width: <?php echo $slot['total_span'] * 100; ?>%; z-index: 10; <?php if ($_SESSION['role'] === 'admin'): ?>cursor: pointer;<?php endif; ?>" 
                                                         title="<?php echo htmlspecialchars($slot['title']); ?>">
                                                        
                                                        <div style="position: absolute; left: 0; top: 0; width: 100%; text-align: center; height: 100%; line-height: 16px; pointer-events: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: white;">
                                                            <?php echo htmlspecialchars($slot['title']); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Occupied by span from left, keep height but render nothing to allow overflow -->
                                                    <div style="height: 16px;"></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Empty spacer to maintain alignment -->
                                                <div style="height: 16px;"></div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Empty cell (no day number) -->
                                    &nbsp;
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Month event list -->
                <?php if (!empty($month_events)): ?>
                <div style="padding: 0.75rem 1rem; background: #fafafa;">
                    <?php foreach ($month_events as $ev): ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0;">
                            <span style="width: 6px; height: 6px; border-radius: 2px; background: <?php echo htmlspecialchars($ev['color'] ?? 'var(--primary-color)'); ?>; flex-shrink: 0;"></span>
                            <span style="font-size: 0.75rem; color: #888;">
                                <?php 
                                echo date('n/j', strtotime($ev['event_date']));
                                if (!empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date']) {
                                    echo '〜' . date('n/j', strtotime($ev['end_date']));
                                }
                                ?>
                            </span>
                            <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($ev['title']); ?></span>
                            <?php if (!($ev['is_all_day'] ?? true) && !empty($ev['start_time'])): ?>
                                <span style="font-size: 0.7rem; color: #aaa;"><?php echo date('H:i', strtotime($ev['start_time'])); ?></span>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <button onclick="editCalendarEvent(<?php echo $ev['id']; ?>)" style="background: none; border: none; cursor: pointer; padding: 4px 8px; color: #888; border-radius: 4px;" onmouseover="this.style.background='#eee'" onmouseout="this.style.background='none'">
                                    <i class="fas fa-pencil-alt" style="font-size: 0.75rem;"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php endforeach; ?>
                </div><!-- end calendarScroll -->
            </div>

            <!-- 目安箱 (Suggestion Box) -->
            <h2 id="suggestion" class="section-title" style="text-align: left; margin: 3rem 0 1.5rem; scroll-margin-top: 80px;">📮 目安箱</h2>
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

    <style>
        /* ヘッダーの調整: コンテンツ幅に合わせる */
        @media (min-width: 769px) {
            .header-inner {
                max-width: 900px; /* dashboard-containerと同じ幅に */
                padding: 0 2rem; /* paddingもdashboard-containerに合わせる */
            }
        }

        /* ログアウトボタンのスタイル調整 */
        .nav-list .nav-link[href="logout.php"] {
            background-color: #f0f2f5; /* 薄いグレーの背景 */
            color: #555 !important;
            width: 40px;
            height: 40px;
            border-radius: 50%; /* 正円 */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important; /* paddingをリセット */
            margin-left: 10px;
            transition: all 0.2s ease;
        }

        .nav-list .nav-link[href="logout.php"]:hover {
            background-color: #e4e6eb; /* ホバー時は少し濃く */
            color: #333 !important;
            transform: translateY(-1px);
        }
        
        /* モバイルメニュー内のログアウトボタン調整 */
        @media (max-width: 768px) {
             .nav-list .nav-link[href="logout.php"] {
                width: auto;
                height: auto;
                border-radius: 8px;
                justify-content: flex-start;
                padding: 1rem 1.5rem !important;
                background-color: #f8f9fa; /* 薄いグレーの背景を追加 */
                color: #555 !important;
                margin-left: 0;
                margin-top: 0.5rem; /* 上に少し余白 */
            }
            .nav-list .nav-link[href="logout.php"]:hover {
                background-color: #e9ecef;
                transform: none;
            }
        }
        
        .event-bar {
            color: white; 
            font-size: 0.55rem; 
            padding: 0; /* padding 0 for container */
            border-radius: 0; 
            margin-bottom: 1px; 
            overflow: visible; /* Allowing title to overflow */
            width: 100%; 
            box-sizing: border-box;
        }
        .event-bar.is-start {
            border-top-left-radius: 3px;
            border-bottom-left-radius: 3px;
            margin-left: 1px; /* Slight gap from cell edge */
        }
        .event-bar.is-end {
            border-top-right-radius: 3px;
            border-bottom-right-radius: 3px;
            margin-right: 1px; /* Slight gap from cell edge */
        }
    </style>
    
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Calendar Modal -->
    <div id="calendarModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div style="padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 id="modalTitle" style="margin: 0; font-size: 1.2rem;">予定を追加</h3>
                <button onclick="closeCalendarModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888;">&times;</button>
            </div>
            <form id="calendarForm" style="padding: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="id" id="eventId" value="">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">タイトル</label>
                    <input type="text" name="title" id="eventTitle" class="form-input" required placeholder="例: 定例ミーティング">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_all_day" id="eventAllDay" style="width: 18px; height: 18px;" onchange="toggleModalTimeFields()">
                        <span>終日</span>
                    </label>
                </div>
                
                <div id="modalDateOnly" style="display: none; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">開始日</label>
                            <input type="date" name="start_date" id="eventStartDate" class="form-input">
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">終了日</label>
                            <input type="date" name="end_date" id="eventEndDate" class="form-input">
                        </div>
                    </div>
                </div>
                
                <div id="modalDateTime" style="margin-bottom: 1rem;">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">開始</label>
                            <input type="datetime-local" name="start_datetime" id="eventStartDatetime" class="form-input" onchange="autoFillModalEnd()">
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">終了</label>
                            <input type="datetime-local" name="end_datetime" id="eventEndDatetime" class="form-input">
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.3rem;">メモ</label>
                    <input type="text" name="description" id="eventDescription" class="form-input" placeholder="追加情報があれば...">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">カテゴリ</label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="colorPicker">
                        <span class="cat-btn selected" data-color="#667eea" style="background: #667eea; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; cursor: pointer;">イベント</span>
                        <span class="cat-btn" data-color="#28a745" style="background: #28a745; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; cursor: pointer;">派遣</span>
                        <span class="cat-btn" data-color="#17a2b8" style="background: #17a2b8; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; cursor: pointer;">mtg</span>
                        <span class="cat-btn" data-color="#dc3545" style="background: #dc3545; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; cursor: pointer;"><i class="fas fa-lock" style="margin-right: 4px;"></i>幹部関連</span>
                        <span class="cat-btn" data-color="#6c757d" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; cursor: pointer;">その他</span>
                    </div>
                    <input type="hidden" name="color" id="eventColor" value="#667eea">
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">保存</button>
                    <button type="button" id="deleteBtn" onclick="deleteCalendarEvent()" class="btn-danger" style="display: none; padding: 0.8rem 1.5rem;">削除</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Copy event/survey URL to clipboard
        function copyEventUrl(eventId) {
            const url = window.location.origin + '/form_view.php?id=' + eventId;
            navigator.clipboard.writeText(url).then(function() {
                alert('URLをコピーしました！');
            }).catch(function(err) {
                prompt('URLをコピーしてください:', url);
            });
        }
        
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            this.classList.toggle('active');
            document.querySelector('.nav-list').classList.toggle('nav-open');
        });
        
        // Close mobile menu when clicking a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelector('.menu-toggle').classList.remove('active');
                document.querySelector('.nav-list').classList.remove('nav-open');
            });
        });
        
        // Scroll position preservation
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('cal_year') || urlParams.has('cal_month')) {
                const savedScroll = sessionStorage.getItem('calendarScroll');
                if (savedScroll) {
                    window.scrollTo(0, parseInt(savedScroll));
                    sessionStorage.removeItem('calendarScroll');
                }
            }
            
            document.querySelectorAll('a[href*="cal_year"]').forEach(link => {
                link.addEventListener('click', () => {
                    sessionStorage.setItem('calendarScroll', window.scrollY);
                });
            });
        })();
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        // Calendar Modal Functions
        let currentEventId = null;
        
        function openCalendarModal() {
            currentEventId = null;
            document.getElementById('modalTitle').textContent = '予定を追加';
            document.getElementById('calendarForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('deleteBtn').style.display = 'none';
            document.querySelectorAll('.cat-btn').forEach(btn => btn.classList.remove('selected'));
            document.querySelector('.cat-btn[data-color="#667eea"]').classList.add('selected');
            document.getElementById('eventColor').value = '#667eea';
            toggleModalTimeFields();
            document.getElementById('calendarModal').style.display = 'flex';
        }
        
        function openCalendarModalWithDate(year, month, day) {
            openCalendarModal();
            const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            document.getElementById('eventStartDatetime').value = dateStr + 'T12:00';
            document.getElementById('eventEndDatetime').value = dateStr + 'T12:10';
        }
        
        function closeCalendarModal() {
            document.getElementById('calendarModal').style.display = 'none';
        }
        
        function editCalendarEvent(id) {
            currentEventId = id;
            fetch('calendar_api.php?action=get&id=' + id)
                .then(r => r.json())
                .then(ev => {
                    if (ev.error) return alert(ev.error);
                    document.getElementById('modalTitle').textContent = '予定を編集';
                    document.getElementById('eventId').value = ev.id;
                    document.getElementById('eventTitle').value = ev.title;
                    document.getElementById('eventDescription').value = ev.description || '';
                    document.getElementById('eventAllDay').checked = !!parseInt(ev.is_all_day);
                    
                    if (ev.is_all_day) {
                        document.getElementById('eventStartDate').value = ev.event_date;
                        document.getElementById('eventEndDate').value = ev.end_date || ev.event_date;
                    } else {
                        const startDt = ev.event_date + 'T' + (ev.start_time || '00:00').substring(0,5);
                        const endDt = (ev.end_date || ev.event_date) + 'T' + (ev.end_time || '00:00').substring(0,5);
                        document.getElementById('eventStartDatetime').value = startDt;
                        document.getElementById('eventEndDatetime').value = endDt;
                    }
                    
                    document.querySelectorAll('.cat-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.dataset.color === ev.color);
                    });
                    document.getElementById('eventColor').value = ev.color;
                    
                    toggleModalTimeFields();
                    document.getElementById('deleteBtn').style.display = 'block';
                    document.getElementById('calendarModal').style.display = 'flex';
                });
        }
        
        function toggleModalTimeFields() {
            const isAllDay = document.getElementById('eventAllDay').checked;
            const currentStartDateTime = document.getElementById('eventStartDatetime').value;
            const currentEndDateTime = document.getElementById('eventEndDatetime').value;
            const currentStartDate = document.getElementById('eventStartDate').value;
            const currentEndDate = document.getElementById('eventEndDate').value;

            // Preserve values when switching modes
            if (isAllDay) {
                // Switching TO All Day
                // Copy date part from datetime inputs if they exist and date inputs are empty or different
                if (currentStartDateTime) {
                    document.getElementById('eventStartDate').value = currentStartDateTime.split('T')[0];
                }
                if (currentEndDateTime) {
                    document.getElementById('eventEndDate').value = currentEndDateTime.split('T')[0];
                }
            } else {
                // Switching FROM All Day
                // Apply date from date inputs to datetime inputs
                if (currentStartDate) {
                    const timePart = currentStartDateTime ? currentStartDateTime.split('T')[1] : '12:00';
                    document.getElementById('eventStartDatetime').value = currentStartDate + 'T' + timePart;
                }
                if (currentEndDate) {
                    const timePart = currentEndDateTime ? currentEndDateTime.split('T')[1] : '13:00';
                    document.getElementById('eventEndDatetime').value = currentEndDate + 'T' + timePart;
                }
            }

            document.getElementById('modalDateOnly').style.display = isAllDay ? 'block' : 'none';
            document.getElementById('modalDateTime').style.display = isAllDay ? 'none' : 'block';
        }
        
        // Auto-fill end date when start date changes
        document.getElementById('eventStartDate').addEventListener('change', function() {
            const end = document.getElementById('eventEndDate');
            if (!end.value) {
                end.value = this.value;
            }
        });
        
        function autoFillModalEnd() {
            const start = document.getElementById('eventStartDatetime').value;
            if (start) {
                const d = new Date(start);
                d.setMinutes(d.getMinutes() + 10);
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const h = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                document.getElementById('eventEndDatetime').value = `${y}-${m}-${day}T${h}:${min}`;
            }
        }
        
        function deleteCalendarEvent() {
            if (!currentEventId || !confirm('この予定を削除しますか？')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', currentEventId);
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
            
            fetch('calendar_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert(res.error || 'エラー');
                });
        }
        
        // Form submit
        document.getElementById('calendarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', currentEventId ? 'update' : 'add');
            
            fetch('calendar_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                    else alert(res.error || 'エラー');
                });
        });
        
        // Color picker
        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('eventColor').value = this.dataset.color;
            });
        });
        
        // Close modal on backdrop click
        document.getElementById('calendarModal').addEventListener('click', function(e) {
            if (e.target === this) closeCalendarModal();
        });
        <?php endif; ?>
    </script>
    
    <style>
        .cat-btn { opacity: 0.7; border: 2px solid transparent; }
        .cat-btn.selected { opacity: 1; border-color: #333; }
        .cat-btn:hover { opacity: 1; }
        .btn-danger { background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</body>
</html>
