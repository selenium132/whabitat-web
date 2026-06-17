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

// Fetch past events (当日いっぱいは残す: 日付が変わってから過去へ。手動アーカイブは即時)
$stmt = $pdo->prepare("SELECT e.*, u.name as creator_name FROM events e LEFT JOIN users u ON e.created_by = u.id WHERE (e.event_date < CURDATE() OR e.is_archived = 1) ORDER BY e.event_date DESC");
$stmt->execute();
$past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check which events the current user is admin for
$user_admin_events = [];
try {
    $admin_stmt = $pdo->prepare("SELECT event_id FROM event_admins WHERE user_id = ?");
    $admin_stmt->execute([$_SESSION['user_id']]);
    $user_admin_events = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist
}

$is_global_admin = ($_SESSION['role'] === 'admin');
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
    <style>
        .restore-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .restore-modal-overlay.active {
            display: flex;
        }
        .restore-modal {
            background: white;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .restore-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .restore-modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .restore-modal-body {
            padding: 1.5rem;
        }
        .restore-option {
            padding: 1rem;
            border: 2px solid #eee;
            border-radius: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .restore-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .restore-option.selected {
            border-color: #667eea;
            background: #f0f2ff;
        }
        .restore-option h4 {
            margin: 0 0 4px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .restore-option p {
            margin: 0;
            font-size: 0.8rem;
            color: #888;
        }
        .restore-date-field {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .restore-date-field.visible {
            display: block;
        }
        .restore-date-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .restore-date-field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .restore-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            padding: 0.6rem 1.2rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: #f5f5f5;
        }
        .btn-restore {
            padding: 0.6rem 1.2rem;
            border: none;
            background: #28a745;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-restore:hover {
            background: #218838;
        }
        .btn-restore:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .event-card-past {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            transition: opacity 0.2s, transform 0.2s;
        }
        .event-card-past:hover {
            opacity: 1;
            transform: translateY(-1px);
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            color: white;
            margin-right: 8px;
        }
        .badge-survey { background: #6c5ce7; }
        .badge-event { background: #0984e3; }
        .badge-archived { background: #6c757d; margin-left: 8px; font-size: 0.7rem; }
        .badge-past-date { background: #e17055; margin-left: 8px; font-size: 0.7rem; }

        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-shrink: 0;
        }

        .btn-icon {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-icon:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 600px) {
            .event-card-past {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .action-buttons {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
    <link rel="stylesheet" href="member.css?v=<?php echo @filemtime(__DIR__ . '/member.css') ?: '1'; ?>">
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
                <?php foreach ($past_events as $event): 
                    $is_event_admin = isEventAdmin($event['id']);
                    $can_manage = $is_global_admin || $is_event_admin;
                    $is_archived = !empty($event['is_archived']);
                    $is_past_date = strtotime($event['event_date']) < strtotime('today');
                    $event_type = $event['type'] ?? 'event';
                    $is_survey = ($event_type === 'survey');
                    
                    // Determine why it's in past events
                    $reason_archived = $is_archived && !$is_past_date; // Archived but date is future
                    $reason_past_date = $is_past_date && !$is_archived; // Date passed naturally
                    $reason_both = $is_past_date && $is_archived; // Both archived and date is past
                ?>
                    <div class="card event-card-past">
                        <div>
                            <div style="color: var(--text-light); font-size: 0.9rem;">
                                <?php if ($is_survey): ?>
                                    <span class="badge badge-survey">アンケート</span>
                                <?php else: ?>
                                    <span class="badge badge-event">出欠確認</span>
                                    <?php echo date('Y年m月d日', strtotime($event['event_date'])); ?>
                                <?php endif; ?>
                                
                                <?php if ($reason_archived || $reason_both): ?>
                                    <span class="badge badge-archived">アーカイブ済</span>
                                <?php endif; ?>
                                <?php if ($is_past_date && !$is_survey): ?>
                                    <span class="badge badge-past-date">日時超過</span>
                                <?php endif; ?>
                            </div>
                            <h3 style="margin: 0.2rem 0 0; font-size: 1.1rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <?php if (!empty($event['creator_name'])): ?>
                                <div style="font-size: 0.75rem; color: #aaa; margin-top: 4px;">
                                    <i class="fas fa-user" style="margin-right: 3px;"></i>作成者: <?php echo htmlspecialchars($event['creator_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <a href="form_responses.php?id=<?php echo $event['id']; ?>" class="btn-secondary btn-icon" style="color: var(--text-color);">
                                回答一覧
                            </a>
                            <?php if ($can_manage): ?>
                                <?php if ($is_archived || $is_past_date): ?>
                                    <!-- Restore Button -->
                                    <button type="button" class="btn-icon" 
                                        style="background: #28a745; color: white;" 
                                        title="ダッシュボードに戻す"
                                        onclick="openRestoreModal(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars(addslashes($event['title']), ENT_QUOTES); ?>', <?php echo $is_archived ? 'true' : 'false'; ?>, <?php echo $is_past_date ? 'true' : 'false'; ?>, '<?php echo $is_survey ? 'survey' : 'event'; ?>', '<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>')">
                                        <i class="fas fa-undo"></i> 戻す
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Edit Button -->
                                <a href="form_create.php?id=<?php echo $event['id']; ?>" class="btn-icon" 
                                   style="background: #007bff; color: white;" title="編集">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Delete Button -->
                                <form method="POST" action="form_delete.php" style="display: inline;" onsubmit="return confirm('このイベントを削除しますか？\nこの操作は取り消せません。');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn-icon" 
                                       style="background: #dc3545; color: white;"
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

    <!-- Restore Modal -->
    <div class="restore-modal-overlay" id="restoreOverlay" onclick="if(event.target===this)closeRestoreModal()">
        <div class="restore-modal">
            <div class="restore-modal-header">
                <h3><i class="fas fa-undo" style="color: #28a745;"></i> <span id="restoreModalTitle">イベントを復元</span></h3>
                <button onclick="closeRestoreModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888;">&times;</button>
            </div>
            <div class="restore-modal-body">
                <p style="font-size: 0.9rem; color: #666; margin: 0 0 1rem;">
                    「<strong id="restoreEventName"></strong>」をダッシュボードに戻します。
                </p>

                <!-- Option: Just unarchive (shown when archived but date is future) -->
                <div class="restore-option" id="optionUnarchive" onclick="selectRestoreOption('unarchive')">
                    <h4><i class="fas fa-box-open" style="color: #28a745;"></i> アーカイブ解除のみ</h4>
                    <p>アーカイブを解除してダッシュボードに戻します。日時はそのままです。</p>
                </div>

                <!-- Option: Change date (shown when date is past) -->
                <div class="restore-option" id="optionRedate" onclick="selectRestoreOption('restore')">
                    <h4><i class="fas fa-calendar-plus" style="color: #007bff;"></i> 日時を変更して戻す</h4>
                    <p>新しい日時を設定してダッシュボードに戻します。</p>
                </div>

                <!-- Date input (shown when "Change date" is selected) -->
                <div class="restore-date-field" id="restoreDateField">
                    <label for="restoreNewDate">新しいイベント日時:</label>
                    <input type="datetime-local" id="restoreNewDate" name="new_event_date">
                </div>
            </div>
            <div class="restore-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeRestoreModal()">キャンセル</button>
                <form method="POST" action="form_archive.php" id="restoreForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="event_id" id="restoreEventId" value="">
                    <input type="hidden" name="action" id="restoreAction" value="unarchive">
                    <input type="hidden" name="new_event_date" id="restoreNewDateHidden" value="">
                    <input type="hidden" name="return" value="past_events.php">
                    <button type="submit" class="btn-restore" id="restoreSubmitBtn" disabled>
                        <i class="fas fa-undo"></i> 復元する
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    let currentRestoreState = { isArchived: false, isPastDate: false, eventType: 'event' };

    function openRestoreModal(eventId, eventName, isArchived, isPastDate, eventType, currentDate) {
        currentRestoreState = { isArchived, isPastDate, eventType };
        
        document.getElementById('restoreEventId').value = eventId;
        document.getElementById('restoreEventName').textContent = eventName;
        
        const titleText = eventType === 'survey' ? 'アンケートを復元' : 'イベントを復元';
        document.getElementById('restoreModalTitle').textContent = titleText;
        
        // Show/hide options based on state
        const optUnarchive = document.getElementById('optionUnarchive');
        const optRedate = document.getElementById('optionRedate');
        
        // Reset
        optUnarchive.style.display = 'none';
        optRedate.style.display = 'none';
        optUnarchive.classList.remove('selected');
        optRedate.classList.remove('selected');
        document.getElementById('restoreDateField').classList.remove('visible');
        document.getElementById('restoreSubmitBtn').disabled = true;
        document.getElementById('restoreAction').value = '';
        document.getElementById('restoreNewDateHidden').value = '';
        
        if (isArchived && !isPastDate) {
            // Archived but future date: just unarchive
            optUnarchive.style.display = 'block';
            // Auto-select since it's the only option
            selectRestoreOption('unarchive');
        } else if (isPastDate && !isArchived) {
            // Past date, not archived: need to change date
            if (eventType === 'survey') {
                // For surveys, just unarchive is enough (they don't rely on date for display)
                optUnarchive.style.display = 'block';
                optUnarchive.querySelector('h4').innerHTML = '<i class="fas fa-box-open" style="color: #28a745;"></i> そのまま戻す';
                optUnarchive.querySelector('p').textContent = 'そのままダッシュボードに戻します。';
            }
            optRedate.style.display = 'block';
            
            // Pre-fill with a reasonable future date (tomorrow same time)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tzOffset = tomorrow.getTimezoneOffset() * 60000;
            const localISO = new Date(tomorrow.getTime() - tzOffset).toISOString().slice(0, 16);
            document.getElementById('restoreNewDate').value = localISO;
        } else if (isPastDate && isArchived) {
            // Both: show both options
            optUnarchive.style.display = 'block';
            optUnarchive.querySelector('h4').innerHTML = '<i class="fas fa-box-open" style="color: #28a745;"></i> アーカイブ解除のみ';
            optUnarchive.querySelector('p').textContent = 'アーカイブを解除します（日時は過去のままなので再度表示されない場合があります）。';
            optRedate.style.display = 'block';
            
            // Pre-fill with a reasonable future date
            const tomorrow2 = new Date();
            tomorrow2.setDate(tomorrow2.getDate() + 1);
            const tzOffset2 = tomorrow2.getTimezoneOffset() * 60000;
            const localISO2 = new Date(tomorrow2.getTime() - tzOffset2).toISOString().slice(0, 16);
            document.getElementById('restoreNewDate').value = localISO2;
        }
        
        document.getElementById('restoreOverlay').classList.add('active');
    }

    function closeRestoreModal() {
        document.getElementById('restoreOverlay').classList.remove('active');
        // Reset labels
        const optUnarchive = document.getElementById('optionUnarchive');
        optUnarchive.querySelector('h4').innerHTML = '<i class="fas fa-box-open" style="color: #28a745;"></i> アーカイブ解除のみ';
        optUnarchive.querySelector('p').textContent = 'アーカイブを解除してダッシュボードに戻します。日時はそのままです。';
    }

    function selectRestoreOption(action) {
        const optUnarchive = document.getElementById('optionUnarchive');
        const optRedate = document.getElementById('optionRedate');
        const dateField = document.getElementById('restoreDateField');
        const submitBtn = document.getElementById('restoreSubmitBtn');
        const actionInput = document.getElementById('restoreAction');
        
        optUnarchive.classList.remove('selected');
        optRedate.classList.remove('selected');
        dateField.classList.remove('visible');
        
        if (action === 'unarchive') {
            optUnarchive.classList.add('selected');
            actionInput.value = 'unarchive';
            document.getElementById('restoreNewDateHidden').value = '';
        } else if (action === 'restore') {
            optRedate.classList.add('selected');
            actionInput.value = 'restore';
            dateField.classList.add('visible');
        }
        
        submitBtn.disabled = false;
    }

    // Sync date field to hidden input
    document.getElementById('restoreNewDate').addEventListener('change', function() {
        document.getElementById('restoreNewDateHidden').value = this.value;
    });

    // Also sync on form submit
    document.getElementById('restoreForm').addEventListener('submit', function(e) {
        const action = document.getElementById('restoreAction').value;
        if (action === 'restore') {
            const newDate = document.getElementById('restoreNewDate').value;
            document.getElementById('restoreNewDateHidden').value = newDate;
            if (!newDate) {
                e.preventDefault();
                alert('新しい日時を入力してください。');
                return false;
            }
        }
    });
    </script>
</body>
</html>
