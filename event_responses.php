<?php
require_once 'config.php';
requireLogin();

$event_id = $_GET['id'] ?? 0;
$pdo = getDB();

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: dashboard.php");
    exit;
}

// Fetch All Participants (Only those who joined)
// We might want to see 'maybe' or 'decline' too? Usually just 'join' is public.
// Admins might want to see all.
$is_admin = ($_SESSION['role'] === 'admin');

if ($is_admin) {
    // Admin sees everything
    $stmt = $pdo->prepare("
        SELECT u.name, u.student_id, u.line_name, u.grade, a.status, a.comment, a.response_data, a.updated_at
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回答一覧: <?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Roboto', 'Noto Sans JP', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background: white;
            box-shadow: none;
            border-bottom: 1px solid #e0e0e0;
        }
        .container {
            max-width: 900px; /* Wider for table/list */
            margin: 40px auto;
            padding: 0 1rem;
        }
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .event-title {
            font-size: 24px;
            font-weight: 500;
        }
        
        .p-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            padding: 20px;
        }
        
        /* Table Style for Admin or Desktop */
        .res-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .res-table th, .res-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .res-table th {
            background-color: #f1f3f4;
            font-weight: 500;
            color: #5f6368;
            font-size: 14px;
        }
        .res-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-join { background: #e6f4ea; color: #1e8e3e; }
        .status-decline { background: #fce8e6; color: #c5221f; }
        .status-maybe { background: #fef7e0; color: #f9ab00; }

        .custom-ans-block {
            margin-top: 8px;
            font-size: 13px;
            color: #444;
            background: #fafafa;
            padding: 8px;
            border-radius: 4px;
        }
        .q-label {
            font-weight: 600;
            color: #666;
            margin-right: 5px;
        }

    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo">WHABITAT</a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <div>
                <a href="event_view.php?id=<?php echo $event['id']; ?>" style="text-decoration: none; color: #1967d2; font-size: 14px;">&lt; フォームに戻る</a>
                <h1 class="event-title" style="margin-top: 5px;">回答一覧: <?php echo htmlspecialchars($event['title']); ?></h1>
                <p style="color: #666; font-size: 14px;">参加予定者数: <?php echo count($participants); ?>名</p>
            </div>
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
                        <th style="width: 80px;">ステータス</th>
                        <th>回答内容</th>
                        <?php if ($is_admin): ?>
                            <th style="width: 120px;">詳細 (Admin)</th>
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
                                <span class="status-badge status-<?php echo htmlspecialchars($p['status']); ?>">
                                    <?php echo getStatusLabel($p['status']); ?>
                                </span>
                            </td>
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
                                                // Schema is array of objects. We used index as key.
                                                if (isset($form_schema[$idx]['title'])) {
                                                     $qTitle = $form_schema[$idx]['title'];
                                                }
                                                
                                                $displayVal = $val;
                                                if (is_array($val)) $displayVal = implode(', ', $val);
                                                
                                                echo '<div class="custom-ans-block">';
                                                echo '<span class="q-label">' . htmlspecialchars($qTitle) . ':</span>';
                                                echo htmlspecialchars($displayVal);
                                                echo '</div>';
                                            }
                                        }
                                    }
                                ?>
                            </td>
                            <?php if ($is_admin): ?>
                                <td style="font-size: 12px; color: #666;">
                                    <?php echo htmlspecialchars($p['student_id']); ?><br>
                                    <?php echo htmlspecialchars($p['grade']); ?><br>
                                    <?php echo htmlspecialchars($p['line_name']); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
