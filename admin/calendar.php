<?php
require_once '../config.php';
requireLogin();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $color = $_POST['color'] ?? '#667eea';
        $is_all_day = isset($_POST['is_all_day']) ? 1 : 0;
        
        if ($is_all_day) {
            $event_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? $event_date;
            $start_time = null;
            $end_time = null;
        } else {
            $start_datetime = $_POST['start_datetime'] ?? '';
            $end_datetime = $_POST['end_datetime'] ?? '';
            $event_date = $start_datetime ? date('Y-m-d', strtotime($start_datetime)) : '';
            $end_date = $end_datetime ? date('Y-m-d', strtotime($end_datetime)) : $event_date;
            $start_time = $start_datetime ? date('H:i:s', strtotime($start_datetime)) : null;
            $end_time = $end_datetime ? date('H:i:s', strtotime($end_datetime)) : null;
        }
        
        if ($title && $event_date) {
            $stmt = $pdo->prepare("INSERT INTO calendar_events (title, event_date, start_time, end_time, is_all_day, description, color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $event_date, $start_time, $end_time, $is_all_day, $description ?: null, $color, $_SESSION['user_id']])) {
                $success = '予定を追加しました！';
            } else {
                $error = 'エラーが発生しました。';
            }
        } else {
            $error = 'タイトルと日時を入力してください。';
        }
    } elseif ($action === 'delete') {
        $event_id = $_POST['event_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ?");
        $stmt->execute([$event_id]);
        $success = '予定を削除しました。';
    }
}

// Fetch all calendar events
$events = [];
try {
    $stmt = $pdo->query("SELECT * FROM calendar_events ORDER BY event_date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カレンダー管理 | WHABITAT</title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .color-picker {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .color-option {
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            font-size: 0.85rem;
            font-weight: 500;
            color: white;
        }
        .color-option:hover, .color-option.selected {
            border-color: #333;
        }
        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .event-item:last-child { border-bottom: none; }
    </style>
    <link rel="stylesheet" href="../member.css?v=<?php echo @filemtime(__DIR__ . '/../member.css') ?: '1'; ?>">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="../dashboard.php" class="logo">
                <img src="../logo.png" alt="WHABITAT" height="50">
            </a>
        </div>
    </header>

    <main>
        <div class="dashboard-container" style="max-width: 700px;">
            <a href="../dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-color); text-decoration: none; font-weight: 500; margin-bottom: 1.5rem;">
                <i class="fas fa-chevron-left"></i> ダッシュボードに戻る
            </a>
            
            <div class="card" style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i>わびカレンダー管理
                </h1>
            </div>
            
            <?php if ($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Add Event Form -->
            <div class="card" style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus-circle" style="color: var(--primary-color);"></i>
                    予定を追加
                </h2>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="color" id="selectedColor" value="#667eea">
                    
                    <div class="form-group">
                        <label class="form-label">タイトル</label>
                        <input type="text" name="title" class="form-input" required placeholder="例: 定例ミーティング">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_all_day" id="isAllDay" style="width: 18px; height: 18px;" onchange="toggleTimeFields()">
                            <span>終日</span>
                        </label>
                    </div>
                    
                    <div id="dateOnlyField" style="display: none;">
                        <div style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">開始日</label>
                                <input type="date" name="start_date" id="startDate" class="form-input" onchange="autoFillEndDate()">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">終了日</label>
                                <input type="date" name="end_date" id="endDate" class="form-input">
                            </div>
                        </div>
                    </div>
                    
                    <div id="dateTimeFields">
                        <div style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">開始</label>
                                <input type="datetime-local" name="start_datetime" id="startDatetime" class="form-input" onchange="autoFillEndDatetime()">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">終了</label>
                                <input type="datetime-local" name="end_datetime" id="endDatetime" class="form-input">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">メモ（任意）</label>
                        <input type="text" name="description" class="form-input" placeholder="追加情報があれば...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">カテゴリ</label>
                        <div class="color-picker">
                            <?php 
                            $categories = [
                                ['color' => '#667eea', 'label' => 'イベント', 'admin_only' => false],
                                ['color' => '#28a745', 'label' => '派遣', 'admin_only' => false],
                                ['color' => '#17a2b8', 'label' => 'mtg', 'admin_only' => false],
                                ['color' => '#dc3545', 'label' => '幹部関連', 'admin_only' => true],
                                ['color' => '#6c757d', 'label' => 'その他', 'admin_only' => false],
                            ];
                            foreach ($categories as $i => $cat): 
                            ?>
                                <div class="color-option <?php echo $i === 0 ? 'selected' : ''; ?>" 
                                     style="background: <?php echo $cat['color']; ?>;" 
                                     data-color="<?php echo $cat['color']; ?>"
                                     onclick="selectColor(this, '<?php echo $cat['color']; ?>')"><?php if ($cat['admin_only']): ?><i class="fas fa-lock" style="margin-right: 4px;"></i><?php endif; ?><?php echo $cat['label']; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> 追加
                    </button>
                </form>
            </div>
            
            <!-- Event List -->
            <div class="card">
                <h2 style="font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list" style="color: var(--primary-color);"></i>
                    登録済みの予定
                </h2>
                
                <?php if (empty($events)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        予定が登録されていません
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                        <div class="event-item">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo htmlspecialchars($ev['color']); ?>; flex-shrink: 0;"></span>
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($ev['title']); ?></div>
                                    <div style="font-size: 0.85rem; color: #888;">
                                        <?php echo date('Y/m/d', strtotime($ev['event_date'])); ?>
                                        <?php if (!($ev['is_all_day'] ?? true) && $ev['start_time']): ?>
                                            <?php echo date('H:i', strtotime($ev['start_time'])); ?>
                                            <?php if ($ev['end_time']): ?>
                                                - <?php echo date('H:i', strtotime($ev['end_time'])); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            終日
                                        <?php endif; ?>
                                        <?php if ($ev['description']): ?>
                                            / <?php echo htmlspecialchars($ev['description']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('この予定を削除しますか？');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                <button type="submit" class="btn-danger" style="padding: 6px 10px; font-size: 0.75rem;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function selectColor(el, color) {
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedColor').value = color;
        }
        
        function toggleTimeFields() {
            const isAllDay = document.getElementById('isAllDay').checked;
            document.getElementById('dateOnlyField').style.display = isAllDay ? 'block' : 'none';
            document.getElementById('dateTimeFields').style.display = isAllDay ? 'none' : 'block';
        }
        
        function autoFillEndDatetime() {
            const start = document.getElementById('startDatetime').value;
            if (start) {
                const startDate = new Date(start);
                startDate.setMinutes(startDate.getMinutes() + 10);
                // Format as local datetime-local string (YYYY-MM-DDTHH:MM)
                const year = startDate.getFullYear();
                const month = String(startDate.getMonth() + 1).padStart(2, '0');
                const day = String(startDate.getDate()).padStart(2, '0');
                const hours = String(startDate.getHours()).padStart(2, '0');
                const mins = String(startDate.getMinutes()).padStart(2, '0');
                document.getElementById('endDatetime').value = `${year}-${month}-${day}T${hours}:${mins}`;
            }
        }
        
        function autoFillEndDate() {
            const start = document.getElementById('startDate').value;
            if (start) {
                document.getElementById('endDate').value = start;
            }
        }
    </script>
</body>
</html>
