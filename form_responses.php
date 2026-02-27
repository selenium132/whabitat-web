<?php
require_once 'config.php';
requireLogin();

$event_id = $_GET['id'] ?? 0;
$pdo = getDB();

$pdo = getDB();

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "イベントが見つかりません。";
    exit;
}

// For surveys: restrict response viewing to admin, creator, event_admin only
if (($event['type'] ?? 'event') === 'survey') {
    $can_view_responses = false;
    if ($_SESSION['role'] === 'admin') {
        $can_view_responses = true;
    } elseif ($event['created_by'] == $_SESSION['user_id']) {
        $can_view_responses = true;
    } else {
        // Check event_admins
        try {
            $admin_check = $pdo->prepare("SELECT 1 FROM event_admins WHERE event_id = ? AND user_id = ?");
            $admin_check->execute([$event_id, $_SESSION['user_id']]);
            if ($admin_check->fetch()) {
                $can_view_responses = true;
            }
        } catch (Exception $e) {}
    }
    if (!$can_view_responses) {
        header("Location: dashboard.php");
        exit;
    }
}

// Fetch All Participants (Only those who joined)
// We might want to see 'maybe' or 'decline' too? Usually just 'join' is public.
// Admins might want to see all.
$is_admin = ($_SESSION['role'] === 'admin');

if ($is_admin) {
    // Admin sees everything
    $stmt = $pdo->prepare("
        SELECT u.name, u.student_id, u.line_name, u.grade, u.faculty, u.gender, a.status, a.comment, a.response_data, a.updated_at
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.event_id = ?
        ORDER BY FIELD(a.status, 'join', 'maybe', 'decline'), a.updated_at DESC
    ");
} else {
    // Members see only 'join'
    $stmt = $pdo->prepare("
        SELECT u.name, u.student_id, u.line_name, u.grade, a.status, a.comment, a.response_data, a.updated_at
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.event_id = ? AND a.status = 'join'
        ORDER BY a.updated_at DESC
    ");
}
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count only 'join' status for participant count (fix for admin view including all)
$join_count = 0;
foreach ($participants as $p) {
    if ($p['status'] === 'join') {
        $join_count++;
    }
}
// If not admin, all participants are 'join' anyway
if (!$is_admin) {
    $join_count = count($participants);
}

// Parse Schema for Headers if needed (to show custom answers columns?)
// For now, let's just list them nicely.
$form_schema = [];
if (!empty($event['form_schema'])) {
    $decoded = json_decode($event['form_schema'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $form_schema = $decoded;
    }
}

function getStatusLabel($status) {
    global $event;
    if (($event['type'] ?? 'event') === 'survey' && $status === 'join') return '回答済';
    if ($status === 'join') return '参加';
    if ($status === 'decline') return '不参加';
    if ($status === 'maybe') return '未定';
    return $status;
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回答一覧: <?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-color);
            font-family: 'Noto Sans JP', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background: white;
            box-shadow: none;
            border-bottom: 1px solid #e0e0e0;
        }
        .container {
            max-width: 900px;
            margin: 100px auto 40px;
            padding: 0 1rem;
        }
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .event-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .p-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
            padding: 24px;
        }
        
        /* Table Style */
        .res-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .res-table th, .res-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .res-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        .res-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-join { background: #e6f4ea; color: #1e8e3e; }
        .status-decline { background: #fce8e6; color: #c5221f; }
        .status-maybe { background: #fef7e0; color: #f9ab00; }

        .custom-ans-block {
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--text-color);
            background: #f9f9f9;
            padding: 10px;
            border-radius: 8px;
        }
        .q-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-right: 5px;
            font-size: 0.85rem;
        }

    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
             <a href="dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="event-title" style="margin-top: 5px;">回答一覧: <?php echo htmlspecialchars($event['title']); ?></h1>
                <p style="color: var(--text-light); font-size: 14px;">参加予定者数: <?php echo $join_count; ?>名</p>
                <?php 
                    $is_manager = $is_admin || isEventAdmin($event['id']);
                ?>
            </div>
            <?php if ($is_manager): ?>
                <button onclick="copyForSpreadsheet()" class="btn-primary" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-copy"></i> シート用にコピー
                </button>
                <?php if (!empty($event['spreadsheet_id'])): ?>
                    <a href="form_google_sheet.php?id=<?php echo $event['id']; ?>" target="_blank" class="btn-secondary" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-left: 10px; background-color: #0f9d58; color: white;">
                        <i class="fas fa-sync-alt"></i> シートを開く (同期)
                    </a>
                <?php else: ?>
                    <a href="form_google_sheet.php?id=<?php echo $event['id']; ?>" target="_blank" class="btn-secondary" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-left: 10px; background-color: #0f9d58; color: white;">
                        <i class="fas fa-file-excel"></i> シート連携
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (empty($participants)): ?>
            <div class="p-card" style="text-align: center; color: #666;">
                まだ回答はありません。
            </div>
        <?php else: ?>
            <!-- Desktop/Table View -->
            <table class="res-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">名前</th>
                        <th style="width: 60px;">学年</th>
                        <th style="width: 80px;"><?php echo (($event['type'] ?? 'event') === 'survey') ? '回答状況' : 'ステータス'; ?></th>
                        <?php if ($is_admin): ?>
                            <th style="background-color: #f0f4f8;">回答内容 <i class="fas fa-lock" style="font-size:12px; color:#888;" title="管理者のみ表示"></i></th>
                            <th style="width: 100px; background-color: #f0f4f8;">学部 <i class="fas fa-lock" style="font-size:12px; color:#888;" title="管理者のみ表示"></i></th>
                            <th style="width: 60px; background-color: #f0f4f8;">性別 <i class="fas fa-lock" style="font-size:12px; color:#888;" title="管理者のみ表示"></i></th>
                            <th style="width: 100px; background-color: #f0f4f8;">学籍番号 <i class="fas fa-lock" style="font-size:12px; color:#888;" title="管理者のみ表示"></i></th>
                            <th style="width: 120px; background-color: #f0f4f8;">LINE名 <i class="fas fa-lock" style="font-size:12px; color:#888;" title="管理者のみ表示"></i></th>
                        <?php else: ?>
                            <?php 
                                // Check if there are any public questions
                                $has_any_public = false;
                                if (!empty($form_schema)) {
                                    foreach ($form_schema as $q) {
                                        if (!empty($q['public'])) {
                                            $has_any_public = true;
                                            break;
                                        }
                                    }
                                }
                                if ($has_any_public):
                            ?>
                                <th>公開回答</th>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($p['grade']); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($p['status']); ?>">
                                    <?php echo getStatusLabel($p['status']); ?>
                                </span>
                            </td>
                            <?php if ($is_admin): ?>
                                <td>
                                    <?php if ($p['comment']): ?>
                                        <div style="font-style: italic; margin-bottom: 5px;">"<?php echo htmlspecialchars($p['comment']); ?>"</div>
                                    <?php endif; ?>

                                    <?php 
                                        if (!empty($p['response_data'])) {
                                            $ans = json_decode($p['response_data'], true);
                                            if ($ans) {
                                                foreach ($ans as $idx => $val) {
                                                    // Find question title from schema if possible
                                                    $qTitle = "Q".($idx+1); 
                                                    // Try to match index to schema
                                                    if (isset($form_schema[$idx]['title'])) {
                                                         $qTitle = $form_schema[$idx]['title'];
                                                    }
                                                    
                                                    $displayVal = $val;
                                                    if (is_array($val)) $displayVal = implode(', ', $val);
                                                    
                                                    echo '<div class="custom-ans-block">';
                                                    echo '<span class="q-label">' . htmlspecialchars($qTitle) . ':</span>';
                                                    echo nl2br(htmlspecialchars($displayVal));
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['faculty']); ?></td>
                                <td><?php 
                                    $genderLabel = $p['gender'] ?? '';
                                    if ($genderLabel === 'male') echo '男';
                                    elseif ($genderLabel === 'female') echo '女';
                                    else echo '-';
                                ?></td>
                                <td><?php echo htmlspecialchars($p['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($p['line_name']); ?></td>
                            <?php else: ?>
                                <?php 
                                    // Non-admin: show only public question responses
                                    $has_public = false;
                                    if (!empty($p['response_data']) && !empty($form_schema)) {
                                        $ans = json_decode($p['response_data'], true);
                                        if ($ans) {
                                            foreach ($ans as $idx => $val) {
                                                if (isset($form_schema[$idx]) && !empty($form_schema[$idx]['public'])) {
                                                    $has_public = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    if ($has_public):
                                ?>
                                <td>
                                    <?php 
                                        $ans = json_decode($p['response_data'], true);
                                        foreach ($ans as $idx => $val) {
                                            if (isset($form_schema[$idx]) && !empty($form_schema[$idx]['public'])) {
                                                $qTitle = $form_schema[$idx]['title'] ?? "Q".($idx+1);
                                                $displayVal = is_array($val) ? implode(', ', $val) : $val;
                                                echo '<div class="custom-ans-block">';
                                                echo '<span class="q-label">' . htmlspecialchars($qTitle) . ':</span>';
                                                echo nl2br(htmlspecialchars($displayVal));
                                                echo '</div>';
                                            }
                                        }
                                    ?>
                                </td>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Copy to Clipboard Script -->
    <script>
        function copyForSpreadsheet() {
            const table = document.querySelector('.res-table');
            if (!table) return;

            let text = '';
            
            // 1. Headers
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.replace(/\n/g, ' ').trim());
            text += headers.join('\t') + '\n';

            // 2. Rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(tr => {
                const cells = Array.from(tr.querySelectorAll('td')).map(td => {
                    // Clean up text: remove newlines inside cells, trim whitespace
                    let val = td.innerText.replace(/[\n\r]+/g, ' ').trim();
                    return val;
                });
                text += cells.join('\t') + '\n';
            });

            // 3. Copy
            navigator.clipboard.writeText(text).then(() => {
                alert('コピーしました！\nスプレッドシートを開いてペーストしてください。');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                alert('コピーに失敗しました。');
            });
        }
    </script>
</html>
