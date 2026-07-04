<?php
require_once 'config.php';
require_once 'room_common.php';
requireLogin();

$pdo = getDB();
ensureRoomTables($pdo);

// 部室の在室状況・予約（初期表示。以降はroom_api.phpのポーリングで更新）
$room_occupants = getCurrentOccupants($pdo);
$room_my_presence = false;
foreach ($room_occupants as $ro) {
    if ((int)$ro['id'] === (int)$_SESSION['user_id']) {
        $room_my_presence = true;
        break;
    }
}
$room_active_reservation = getActiveReservation($pdo);
$room_csrf_token = generateCsrfToken();

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
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="member.css?v=<?php echo @filemtime(__DIR__ . '/member.css') ?: '1'; ?>">
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
                    <li><a href="#room" class="nav-link">部室</a></li>
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
                    <a href="form_create.php" class="btn-primary">
                        <i class="fas fa-plus"></i> 出欠確認作成
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
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

            <!-- 部室 在室状況・入退室・予約 -->
            <h2 id="room" class="section-title" style="text-align: left; margin: 0 0 1.5rem; scroll-margin-top: 80px;"><span aria-hidden="true">🚪</span> 部室</h2>
            <input type="hidden" id="roomCsrfToken" value="<?php echo htmlspecialchars($room_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card room-status-card">
                <div class="room-status-top">
                    <div class="room-status-info">
                        <div id="roomCountBadge" class="room-count-badge<?php echo empty($room_occupants) ? ' is-zero' : ''; ?>"><?php echo count($room_occupants); ?></div>
                        <div>
                            <div id="roomOccupantCount" class="room-count-label">
                                <?php echo empty($room_occupants) ? '現在、部室には誰もいません' : count($room_occupants) . '人が在室中'; ?>
                            </div>
                            <div id="roomActiveReservation" class="room-reservation-note">
                                <?php if ($room_active_reservation): ?>
                                    予約中 <?php echo substr($room_active_reservation['start_time'], 0, 5) . '〜' . substr($room_active_reservation['end_time'], 0, 5); ?>（<?php echo htmlspecialchars($room_active_reservation['name'], ENT_QUOTES, 'UTF-8'); ?>さん）
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="roomToggleBtn" class="room-toggle-btn<?php echo $room_my_presence ? ' is-present' : ''; ?>" onclick="roomToggle()">
                        <i id="roomToggleIcon" class="fas <?php echo $room_my_presence ? 'fa-right-from-bracket' : 'fa-door-open'; ?>"></i>
                        <span id="roomToggleLabel"><?php echo $room_my_presence ? '退室する' : '入室する'; ?></span>
                    </button>
                </div>
                <div id="roomError" class="room-error"></div>
                <div id="roomOccupantList" class="room-occupant-list">
                    <?php foreach ($room_occupants as $occ): ?>
                        <div class="room-avatar-wrap">
                            <?php if (!empty($occ['avatar_url'])): ?>
                                <img class="room-avatar" src="<?php echo htmlspecialchars($occ['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            <?php else: ?>
                                <div class="room-avatar room-avatar-fallback"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <span class="room-avatar-name"><?php echo htmlspecialchars($occ['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card room-reserve-card">
                <div class="room-reserve-header">
                    <h3 class="room-reserve-title"><i class="far fa-calendar-plus"></i> 部室予約</h3>
                    <button type="button" class="room-toggle-btn" onclick="openRoomReserveModal()"><i class="fas fa-plus"></i> 予約する</button>
                </div>
                <div id="roomReservationList" class="room-reservation-list"></div>
            </div>

            <!-- 部室予約モーダル -->
            <div id="roomReserveModal" class="room-reserve-modal">
                <div class="room-reserve-modal-box">
                    <div class="room-reserve-modal-header">
                        <h3 id="roomReserveModalTitle">部室を予約</h3>
                        <button type="button" class="room-reserve-modal-close" onclick="closeRoomReserveModal()" aria-label="閉じる">&times;</button>
                    </div>
                    <form id="roomReserveForm" class="room-reserve-form">
                        <div class="room-reserve-field">
                            <label>日付</label>
                            <input type="date" name="reserved_date" required class="form-input" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="room-reserve-field">
                            <label>開始</label>
                            <input type="time" name="start_time" required class="form-input">
                        </div>
                        <div class="room-reserve-field">
                            <label>終了</label>
                            <input type="time" name="end_time" required class="form-input">
                        </div>
                        <div class="room-reserve-field room-reserve-field-purpose">
                            <label>目的（任意）</label>
                            <input type="text" name="purpose" class="form-input" placeholder="例: mtg">
                        </div>
                        <div id="roomReserveError" class="room-error"></div>
                        <button type="submit" id="roomReserveSubmitBtn" class="room-toggle-btn room-reserve-submit">予約する</button>
                    </form>
                </div>
            </div>

            <div id="events" style="display: flex; align-items: center; gap: 16px; margin: 3rem 0 1.5rem; scroll-margin-top: 80px;">
                <h2 class="section-title" style="text-align: left; margin: 0;"><span aria-hidden="true">✅</span> 出欠確認</h2>
                <a href="past_events.php" style="font-size: 0.85rem; color: #555; text-decoration: none; padding: 8px 4px; display: inline-block;">過去の出欠 <span aria-hidden="true">→</span></a>
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
                                        <i class="fas fa-pen-to-square"></i><span>編集</span>
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
                <h2 class="section-title" style="text-align: left; margin: 0;"><span aria-hidden="true">📋</span> アンケート</h2>
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
                                <a href="form_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-status">
                                    回答状況
                                </a>
                            </div>
                            <div class="event-icons">
                                <?php if (isEventAdmin($event['id'])): ?>
                                    <a href="form_create.php?id=<?php echo $event['id']; ?>" class="icon-btn" title="編集">
                                        <i class="fas fa-pen-to-square"></i><span>編集</span>
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
            <h2 id="calendar" class="section-title" style="text-align: left; margin: 3rem 0 1rem; scroll-margin-top: 80px;"><span aria-hidden="true">📅</span> わびカレンダー</h2>
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
            <h2 id="suggestion" class="section-title" style="text-align: left; margin: 3rem 0 1.5rem; scroll-margin-top: 80px;"><span aria-hidden="true">📮</span> 目安箱</h2>
            <div class="card" style="padding: 2rem;">
                <?php if (isset($_SESSION['suggestion_success']) && $_SESSION['suggestion_success']): ?>
                    <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        ✅ 送信しました！ご意見ありがとうございます。
                    </div>
                    <?php unset($_SESSION['suggestion_success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['suggestion_error'])): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        ⚠️ <?php echo htmlspecialchars($_SESSION['suggestion_error'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php unset($_SESSION['suggestion_error']); ?>
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
            font-size: 0.7rem;
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

        /* ===== 部室セクション ===== */
        .room-status-card { padding: 1.8rem; }
        .room-status-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.2rem;
        }
        .room-status-info { display: flex; align-items: center; gap: 1.1rem; }
        .room-count-badge {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--primary-color, #1a1a1a);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background 0.15s ease;
        }
        .room-count-badge.is-zero { background: #e6e2d9; color: #9a9488; }
        .room-count-label { font-weight: 600; font-size: 1.05rem; }
        .room-reservation-note {
            font-size: 0.83rem;
            color: var(--text-light, #8d877c);
            margin-top: 4px;
            min-height: 1.2em;
        }

        .room-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 0.75rem 1.7rem;
            border-radius: 50px;
            border: none;
            background: var(--primary-color, #1a1a1a);
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            font-family: inherit;
            cursor: pointer;
            transition: opacity 0.15s ease, background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
            white-space: nowrap;
        }
        .room-toggle-btn:hover { opacity: 0.85; }
        .room-toggle-btn.is-present {
            background: transparent;
            border: 1.5px solid var(--border-color, #e6e2d9);
            color: var(--text-color, #2a2a2a);
        }
        .room-toggle-btn:disabled { opacity: 0.5; cursor: default; }

        .room-error {
            color: var(--accent-red, #b0453a);
            font-size: 0.85rem;
            margin-top: 10px;
            display: none;
        }

        /* 在室者アイコン: PCはhover、スマホはtapで名前表示 */
        .room-occupant-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 1.6rem;
            padding-top: 1.4rem;
            border-top: 1px solid var(--border-color, #e6e2d9);
        }
        .room-occupant-list:empty { display: none; }
        .room-avatar-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        .room-avatar, .room-avatar-fallback {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color, #e6e2d9);
        }
        .room-avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f2f0ea;
            color: var(--text-light, #8d877c);
            font-size: 1.1rem;
        }
        .room-avatar-name {
            display: none;
            position: absolute;
            bottom: -1.7rem;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            background: var(--primary-color, #1a1a1a);
            color: #fff;
            font-size: 0.72rem;
            padding: 3px 9px;
            border-radius: 4px;
            z-index: 5;
        }
        @media (min-width: 769px) {
            .room-avatar-wrap:hover .room-avatar-name { display: block; }
        }
        .room-avatar-wrap.show .room-avatar-name { display: block; }

        /* 部室予約カード */
        .room-reserve-card { padding: 1.8rem; margin-top: 1rem; }
        .room-reserve-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .room-reserve-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 予約フォームのモーダル */
        .room-reserve-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            box-sizing: border-box;
        }
        .room-reserve-modal.show { display: flex; }
        .room-reserve-modal-box {
            background: #fff;
            border-radius: 16px;
            max-width: 420px;
            width: 100%;
            padding: 1.6rem;
            box-shadow: var(--shadow-lg, 0 20px 40px rgba(0, 0, 0, 0.15));
            max-height: 90vh;
            overflow-y: auto;
            box-sizing: border-box;
        }
        .room-reserve-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        .room-reserve-modal-header h3 { margin: 0; font-size: 1.1rem; }
        .room-reserve-modal-close {
            background: none;
            border: none;
            font-size: 1.6rem;
            line-height: 1;
            cursor: pointer;
            color: #888;
            padding: 0;
        }

        .room-reserve-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .room-reserve-field label {
            display: block;
            font-size: 0.78rem;
            color: var(--text-light, #8d877c);
            margin-bottom: 5px;
        }
        .room-reserve-field input { width: 100%; box-sizing: border-box; }
        .room-reserve-submit { padding: 0.75rem 1.5rem; justify-content: center; margin-top: 4px; }

        .room-reservation-list {
            margin-top: 1.4rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .room-reservation-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border: 1px solid var(--border-color, #e6e2d9);
            border-radius: 10px;
            font-size: 0.88rem;
            flex-wrap: wrap;
        }
        .room-reservation-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #f2f0ea;
            flex-shrink: 0;
            line-height: 1.15;
        }
        .room-reservation-date .month { font-size: 0.6rem; color: var(--text-light, #8d877c); font-weight: 500; }
        .room-reservation-date .day { font-size: 1.1rem; font-weight: 700; }
        .room-reservation-info { flex: 1; min-width: 150px; }
        .room-reservation-time { font-weight: 600; }
        .room-reservation-who { color: var(--text-light, #8d877c); font-size: 0.8rem; margin-top: 2px; }
        .room-reservation-cancel {
            padding: 5px 12px;
            font-size: 0.78rem;
            border-radius: 50px;
            border: 1px solid var(--border-color, #e6e2d9);
            background: transparent;
            color: var(--text-color, #2a2a2a);
            cursor: pointer;
        }
        .room-reservation-cancel:hover { border-color: var(--primary-color, #1a1a1a); }
        .room-reservation-empty { color: var(--text-light, #8d877c); font-size: 0.85rem; }

        @media (max-width: 768px) {
            .room-status-card, .room-reserve-card { padding: 1.25rem; }
            .room-status-top { flex-direction: column; align-items: stretch; gap: 0.9rem; }
            .room-status-info { gap: 0.8rem; }
            .room-count-badge { width: 42px; height: 42px; font-size: 1.05rem; }
            .room-count-label { font-size: 0.95rem; }
            .room-reservation-note { font-size: 0.78rem; }

            .room-toggle-btn {
                justify-content: center;
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }

            .room-occupant-list { gap: 16px; margin-top: 1.2rem; padding-top: 1rem; }
            .room-avatar, .room-avatar-fallback { width: 42px; height: 42px; }

            .room-reserve-header { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .room-reserve-title { font-size: 0.92rem; }
            .room-reserve-modal-box { padding: 1.3rem; }

            /* iOS Safariはdate/time inputにform-inputの1remパディングを適用すると
               縦に間延びして見た目が崩れる上、UAスタイルがCSSのheight/paddingに
               優先することがあるため、appearanceをリセットした上で!importantで固定する */
            .room-reserve-field input,
            .room-reserve-field-purpose input {
                width: 100% !important;
                box-sizing: border-box !important;
                height: 44px !important;
                min-height: 44px !important;
                max-height: 44px !important;
                padding: 0 0.8rem !important;
                font-size: 0.92rem !important;
                line-height: 44px !important;
                -webkit-appearance: none;
                appearance: none;
            }
            .room-reserve-submit { justify-content: center; padding: 0.65rem 1.2rem; font-size: 0.9rem; }

            .room-reservation-row { padding: 8px 12px; gap: 10px; }
            .room-reservation-date { width: 42px; height: 42px; }
            .room-reservation-time { font-size: 0.92rem; }
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

        // ===== 部室: 在室状況・入退室・予約 =====
        const roomCsrfToken = document.getElementById('roomCsrfToken').value;

        function roomToggle() {
            const btn = document.getElementById('roomToggleBtn');
            const errBox = document.getElementById('roomError');
            errBox.style.display = 'none';
            const action = btn.classList.contains('is-present') ? 'checkout' : 'checkin';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', roomCsrfToken);

            fetch('room_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    if (res.success) {
                        loadRoomStatus();
                    } else {
                        errBox.textContent = res.error || 'エラーが発生しました';
                        errBox.style.display = 'block';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    errBox.textContent = '通信に失敗しました。通信状況をご確認ください。';
                    errBox.style.display = 'block';
                });
        }

        function renderRoomStatus(data) {
            const count = data.occupants.length;
            document.getElementById('roomOccupantCount').textContent =
                count === 0 ? '現在、部室には誰もいません' : count + '人が在室中';

            const badge = document.getElementById('roomCountBadge');
            badge.textContent = count;
            badge.classList.toggle('is-zero', count === 0);

            const resEl = document.getElementById('roomActiveReservation');
            resEl.textContent = '';
            if (data.active_reservation) {
                const r = data.active_reservation;
                resEl.textContent = '予約中 ' + r.start_time.slice(0, 5) + '〜' + r.end_time.slice(0, 5) + '（' + r.name + 'さん）';
            }

            const listEl = document.getElementById('roomOccupantList');
            listEl.innerHTML = '';
            data.occupants.forEach(o => {
                const wrap = document.createElement('div');
                wrap.className = 'room-avatar-wrap';

                let avatarEl;
                if (o.avatar_url) {
                    avatarEl = document.createElement('img');
                    avatarEl.className = 'room-avatar';
                    avatarEl.src = o.avatar_url;
                    avatarEl.alt = '';
                } else {
                    avatarEl = document.createElement('div');
                    avatarEl.className = 'room-avatar room-avatar-fallback';
                    avatarEl.innerHTML = '<i class="fas fa-user"></i>';
                }

                const nameEl = document.createElement('span');
                nameEl.className = 'room-avatar-name';
                nameEl.textContent = o.name; // XSS対策: 必ずtextContentで挿入する

                wrap.appendChild(avatarEl);
                wrap.appendChild(nameEl);
                listEl.appendChild(wrap);
            });

            const toggleBtn = document.getElementById('roomToggleBtn');
            const toggleIcon = document.getElementById('roomToggleIcon');
            const toggleLabel = document.getElementById('roomToggleLabel');
            toggleBtn.classList.toggle('is-present', data.my_presence);
            toggleIcon.className = 'fas ' + (data.my_presence ? 'fa-right-from-bracket' : 'fa-door-open');
            toggleLabel.textContent = data.my_presence ? '退室する' : '入室する';
        }

        function loadRoomStatus() {
            fetch('room_api.php?action=status')
                .then(r => r.json())
                .then(renderRoomStatus)
                .catch(() => {});
        }

        // 在室アイコンのタップ表示（スマホ用。PCはCSSのhoverで表示される）
        document.addEventListener('click', function(e) {
            const wrap = e.target.closest('.room-avatar-wrap');
            document.querySelectorAll('.room-avatar-wrap.show').forEach(w => {
                if (w !== wrap) w.classList.remove('show');
            });
            if (wrap) wrap.classList.toggle('show');
        });

        // 20秒間隔でポーリング。タブが非表示の間は止めてサーバー負荷を抑える。
        let roomPollTimer = null;
        function startRoomPolling() {
            if (roomPollTimer) return;
            roomPollTimer = setInterval(loadRoomStatus, 20000);
        }
        function stopRoomPolling() {
            if (roomPollTimer) {
                clearInterval(roomPollTimer);
                roomPollTimer = null;
            }
        }
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopRoomPolling();
            } else {
                loadRoomStatus();
                startRoomPolling();
            }
        });
        startRoomPolling();

        // 部室予約
        const roomMonthNames = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

        function renderRoomReservations(rows) {
            const listEl = document.getElementById('roomReservationList');
            listEl.innerHTML = '';
            if (rows.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'room-reservation-empty';
                empty.textContent = '現在予約はありません。';
                listEl.appendChild(empty);
                return;
            }
            rows.forEach(r => {
                const row = document.createElement('div');
                row.className = 'room-reservation-row';

                const [, m, d] = r.reserved_date.split('-');
                const dateBadge = document.createElement('div');
                dateBadge.className = 'room-reservation-date';
                const monthEl = document.createElement('span');
                monthEl.className = 'month';
                monthEl.textContent = roomMonthNames[parseInt(m, 10) - 1];
                const dayEl = document.createElement('span');
                dayEl.className = 'day';
                dayEl.textContent = parseInt(d, 10);
                dateBadge.appendChild(monthEl);
                dateBadge.appendChild(dayEl);
                row.appendChild(dateBadge);

                const info = document.createElement('div');
                info.className = 'room-reservation-info';
                const timeEl = document.createElement('div');
                timeEl.className = 'room-reservation-time';
                timeEl.textContent = r.start_time.slice(0, 5) + '〜' + r.end_time.slice(0, 5);
                const whoEl = document.createElement('div');
                whoEl.className = 'room-reservation-who';
                whoEl.textContent = r.name + 'さん' + (r.purpose ? '・' + r.purpose : '');
                info.appendChild(timeEl);
                info.appendChild(whoEl);
                row.appendChild(info);

                if (r.is_mine) {
                    const editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'room-reservation-cancel';
                    editBtn.textContent = '編集';
                    editBtn.onclick = function() { openRoomReserveModal(r); };
                    row.appendChild(editBtn);

                    const cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.className = 'room-reservation-cancel';
                    cancelBtn.textContent = 'キャンセル';
                    cancelBtn.onclick = function() { cancelRoomReservation(r.id); };
                    row.appendChild(cancelBtn);
                }

                listEl.appendChild(row);
            });
        }

        function loadRoomReservations() {
            fetch('room_api.php?action=reservations')
                .then(r => r.json())
                .then(rows => { if (Array.isArray(rows)) renderRoomReservations(rows); })
                .catch(() => {});
        }
        loadRoomReservations();

        function cancelRoomReservation(id) {
            if (!confirm('この予約をキャンセルしますか？')) return;
            const formData = new FormData();
            formData.append('action', 'cancel_reservation');
            formData.append('id', id);
            formData.append('csrf_token', roomCsrfToken);

            fetch('room_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        loadRoomReservations();
                    } else {
                        alert(res.error || 'キャンセルに失敗しました');
                    }
                })
                .catch(() => alert('通信に失敗しました。'));
        }

        let editingReservationId = null;

        function openRoomReserveModal(reservation) {
            const form = document.getElementById('roomReserveForm');
            const title = document.getElementById('roomReserveModalTitle');
            const submitBtn = document.getElementById('roomReserveSubmitBtn');

            if (reservation) {
                editingReservationId = reservation.id;
                title.textContent = '予約を編集';
                form.reserved_date.value = reservation.reserved_date;
                form.start_time.value = reservation.start_time.slice(0, 5);
                form.end_time.value = reservation.end_time.slice(0, 5);
                form.purpose.value = reservation.purpose || '';
                submitBtn.textContent = '更新する';
            } else {
                editingReservationId = null;
                title.textContent = '部室を予約';
                form.reset();
                submitBtn.textContent = '予約する';
            }
            document.getElementById('roomReserveError').style.display = 'none';
            document.getElementById('roomReserveModal').classList.add('show');
        }
        function closeRoomReserveModal() {
            document.getElementById('roomReserveModal').classList.remove('show');
            document.getElementById('roomReserveError').style.display = 'none';
            editingReservationId = null;
        }
        document.getElementById('roomReserveModal').addEventListener('click', function(e) {
            if (e.target === this) closeRoomReserveModal();
        });

        document.getElementById('roomReserveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const errBox = document.getElementById('roomReserveError');
            errBox.style.display = 'none';

            const isEditing = !!editingReservationId;
            const formData = new FormData(this);
            formData.append('action', isEditing ? 'update_reservation' : 'reserve');
            if (isEditing) formData.append('id', editingReservationId);
            formData.append('csrf_token', roomCsrfToken);

            const submitBtn = document.getElementById('roomReserveSubmitBtn');
            const originalLabel = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.textContent = isEditing ? '更新中...' : '予約中...';

            fetch('room_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalLabel;
                    if (res.success) {
                        document.getElementById('roomReserveForm').reset();
                        closeRoomReserveModal();
                        loadRoomReservations();
                        loadRoomStatus();
                    } else {
                        errBox.textContent = res.error || 'エラーが発生しました';
                        errBox.style.display = 'block';
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalLabel;
                    errBox.textContent = '通信に失敗しました。通信状況をご確認ください。';
                    errBox.style.display = 'block';
                });
        });

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

            const deleteBtn = document.getElementById('deleteBtn');
            const originalLabel = deleteBtn ? deleteBtn.textContent : null;
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.textContent = '削除中...';
            }

            fetch('calendar_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.error || 'エラー');
                        if (deleteBtn) {
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = originalLabel;
                        }
                    }
                })
                .catch(function() {
                    alert('削除に失敗しました。通信状況をご確認ください。');
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = originalLabel;
                    }
                });
        }
        
        // Form submit
        document.getElementById('calendarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', currentEventId ? 'update' : 'add');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalLabel = submitBtn ? submitBtn.textContent : null;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '保存中...';
            }

            fetch('calendar_api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.error || 'エラー');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalLabel;
                        }
                    }
                })
                .catch(function() {
                    alert('保存に失敗しました。通信状況をご確認ください。');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalLabel;
                    }
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
