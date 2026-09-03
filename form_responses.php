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
    echo "イベントが見つかりません。";
    exit;
}

// Determine manager status (Admin, Creator, or Event Admin)
$is_admin = ($_SESSION['role'] === 'admin');
$is_manager = $is_admin || ($event['created_by'] == $_SESSION['user_id']) || isEventAdmin($event_id);

// 回答者リストの公開可否（既定: 出欠確認=公開 / アンケート=非公開）。
// 非公開のときは個人を特定できるデータを一切取得せず、ステータス別の人数だけを集計する
// （リンクを隠すだけではURL直打ちで見えてしまうため、ページ側で遮断する）。
$can_view_list = $is_manager || isParticipantsVisible($event);

$status_counts = ['join' => 0, 'maybe' => 0, 'decline' => 0];
$participants = [];
$anon_public_answers = []; // 回答者非公開でも、質問ごとに「公開」指定された回答は匿名で見せる
if (!$can_view_list) {
    $cnt_stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM attendance WHERE event_id = ? GROUP BY status");
    $cnt_stmt->execute([$event_id]);
    foreach ($cnt_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = (int)$row['c'];
        }
    }
}
if ($can_view_list) {
    if ($is_manager) {
        // Managers see everything with detailed data
        $stmt = $pdo->prepare("
            SELECT u.name, u.student_id, u.line_name, u.grade, u.faculty, u.gender, a.status, a.comment, a.response_data, a.updated_at
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.event_id = ?
            ORDER BY FIELD(a.status, 'join', 'maybe', 'decline'), a.updated_at DESC
        ");
    } else {
        // Normal Members see only 'join' with minimal non-private detailed info
        $stmt = $pdo->prepare("
            SELECT u.name, u.grade, a.status, a.comment, a.response_data, a.updated_at
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.event_id = ? AND a.status = 'join'
            ORDER BY a.updated_at DESC
        ");
    }
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Count only 'join' status for participant count (fix for admin view including all)
$join_count = 0;
if (!$can_view_list) {
    // 非公開時は集計クエリの結果を使う（明細は取得していない）
    $join_count = $status_counts['join'];
} else {
    foreach ($participants as $p) {
        if ($p['status'] === 'join') {
            $join_count++;
        }
    }
    // If not manager, all participants are 'join' anyway
    if (!$is_manager) {
        $join_count = count($participants);
    }
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

$is_survey_event = (($event['type'] ?? 'event') === 'survey');

// 質問ごとの「公開」指定（回答者リストが非公開でも、この質問の回答は共有される）
$public_q_idx = [];
foreach ($form_schema as $idx => $q) {
    if (!empty($q['public'])) $public_q_idx[] = $idx;
}

// 公開質問の集計。選択式(radio/checkbox/dropdown)は「選択肢ごとに何人が選んだか」を数え、
// 回答者名を見せてよい場合（管理者、または回答者=公開）は誰が選んだかも並べる。
// $q_tally[$idx] = ['type'=>..., 'options'=>[label => ['count'=>n,'names'=>[...]]], 'texts'=>[...], 'answered'=>n]
$q_tally = [];
if ($public_q_idx) {
    $show_names = $can_view_list; // 回答者が非公開なら名前は取得しない
    if ($show_names) {
        $pa = $pdo->prepare("SELECT u.name, a.response_data FROM attendance a JOIN users u ON u.id = a.user_id
                             WHERE a.event_id = ? AND a.status = 'join' AND a.response_data IS NOT NULL
                             ORDER BY u.name COLLATE utf8mb4_unicode_ci ASC");
        $pa->execute([$event_id]);
        $rows = $pa->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 誰が答えたか分からないよう、名前は取得せず順序もランダムにする
        $pa = $pdo->prepare("SELECT response_data FROM attendance WHERE event_id = ? AND status = 'join' AND response_data IS NOT NULL ORDER BY RAND()");
        $pa->execute([$event_id]);
        $rows = array_map(fn($j) => ['name' => null, 'response_data' => $j], $pa->fetchAll(PDO::FETCH_COLUMN));
    }

    foreach ($public_q_idx as $idx) {
        $q = $form_schema[$idx];
        $type = $q['type'] ?? 'paragraph';
        $entry = ['type' => $type, 'options' => [], 'texts' => [], 'answered' => 0];
        if ($type !== 'paragraph') {
            foreach (($q['options'] ?? []) as $opt) {
                if ($opt === '' || $opt === null) continue;
                $entry['options'][(string)$opt] = ['count' => 0, 'names' => []];
            }
        }
        $q_tally[$idx] = $entry;
    }

    foreach ($rows as $row) {
        $ans = json_decode($row['response_data'], true);
        if (!is_array($ans)) continue;
        foreach ($public_q_idx as $idx) {
            $v = $ans[$idx] ?? null;
            if ($v === null || $v === '' || (is_array($v) && !$v)) continue;
            $q_tally[$idx]['answered']++;
            if ($q_tally[$idx]['type'] === 'paragraph') {
                $q_tally[$idx]['texts'][] = is_array($v) ? implode('、', $v) : (string)$v;
            } else {
                foreach ((array)$v as $picked) {
                    $picked = (string)$picked;
                    if (!isset($q_tally[$idx]['options'][$picked])) {
                        // 選択肢が編集で変わった等、スキーマに無い値も拾う
                        $q_tally[$idx]['options'][$picked] = ['count' => 0, 'names' => []];
                    }
                    $q_tally[$idx]['options'][$picked]['count']++;
                    if (!empty($row['name'])) $q_tally[$idx]['options'][$picked]['names'][] = $row['name'];
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/apple-touch-icon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回答一覧: <?php echo htmlspecialchars($event['title']); ?> | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
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
        /* 非公開時の集計表示（ミニマル・モノトーン） */
        .summary-rows { display: flex; flex-direction: column; }
        .summary-row {
            display: flex; justify-content: space-between; align-items: baseline;
            padding: 12px 4px; border-bottom: 1px solid #f0f0f0;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row span { color: var(--text-light); font-size: 0.95rem; }
        .summary-row strong { font-size: 1.15rem; font-weight: 700; color: var(--text-color); }
        .summary-total { border-top: 1px solid #e0e0e0; margin-top: 4px; }
        .summary-total span, .summary-total strong { color: var(--text-color); }

        .q-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-right: 5px;
            font-size: 0.85rem;
        }

    </style>
    <link rel="stylesheet" href="member.css?v=<?php echo @filemtime(__DIR__ . '/member.css') ?: '1'; ?>">
</head>
<body>
    <?php
    $mh_variant = 'back';
    $mh_label = '一覧に戻る';
    include 'partials/member_header.php';
    ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="event-title" style="margin-top: 5px;">回答一覧: <?php echo htmlspecialchars($event['title']); ?></h1>
                <p style="color: var(--text-light); font-size: 14px;"><?php echo (($event['type'] ?? 'event') === 'survey') ? '回答者数' : '参加予定者数'; ?>: <?php echo $join_count; ?>名</p>
                <?php 
                    // $is_manager is prepared at top
                ?>
            </div>
            <?php if ($is_manager): ?>
                <a href="form_create.php?id=<?php echo (int)$event['id']; ?>" class="btn-secondary" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-right: 10px;">
                    <i class="fas fa-pen-to-square"></i> 編集
                </a>
                <button onclick="copyForSpreadsheet()" class="btn-primary" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-copy"></i> シート用にコピー
                </button>
                <?php // シート同期は状態変更を伴うため POST + CSRF（GET リンクだと第三者に踏ませて発火できる） ?>
                <form method="POST" action="form_google_sheet.php" target="_blank" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$event['id']; ?>">
                    <?php if (!empty($event['spreadsheet_id'])): ?>
                        <button type="submit" class="btn-secondary btn-sheet"><i class="fas fa-sync-alt"></i> シートを開く (同期)</button>
                    <?php else: ?>
                        <button type="submit" class="btn-secondary btn-sheet"><i class="fas fa-file-excel"></i> シート連携</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($can_view_list && $q_tally): ?>
            <?php include __DIR__ . '/partials/response_tally.php'; ?>
        <?php endif; ?>

        <?php if (!$can_view_list): ?>
            <?php $is_survey_summary = (($event['type'] ?? 'event') === 'survey'); ?>
            <div class="p-card">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                    <i class="fas fa-chart-simple" style="color: #888;"></i>
                    <span style="font-weight: 600; color: var(--text-color);">回答状況</span>
                </div>

                <div class="summary-rows">
                    <?php if ($is_survey_summary): ?>
                        <div class="summary-row"><span>回答済み</span><strong><?php echo $status_counts['join']; ?>名</strong></div>
                    <?php else: ?>
                        <div class="summary-row"><span>参加</span><strong><?php echo $status_counts['join']; ?>名</strong></div>
                        <div class="summary-row"><span>不参加</span><strong><?php echo $status_counts['decline']; ?>名</strong></div>
                        <div class="summary-row"><span>未定</span><strong><?php echo $status_counts['maybe']; ?>名</strong></div>
                        <div class="summary-row summary-total">
                            <span>回答済み</span>
                            <strong><?php echo $status_counts['join'] + $status_counts['decline'] + $status_counts['maybe']; ?>名</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <p style="margin: 18px 0 0; font-size: 0.85rem; color: #888; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-lock" style="font-size: 12px;"></i>
                    <?php echo $is_survey_summary ? '誰が回答したかは主催者・管理者のみが確認できます。' : '誰が参加するかは主催者・管理者のみが確認できます。'; ?>
                </p>
            </div>

            <?php include __DIR__ . '/partials/response_tally.php'; ?>
        <?php elseif (empty($participants)): ?>
            <div class="p-card" style="text-align: center; color: #666;">
                まだ回答はありません。
            </div>
        <?php else: ?>

            <?php $is_survey = (($event['type'] ?? 'event') === 'survey'); ?>

            <?php if ($is_survey && !$is_manager): ?>
                <!-- ===== Survey: General Member View (Name + Grade only) ===== -->
                <div class="p-card" style="padding: 16px 24px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                        <i class="fas fa-check-circle" style="color: #1e8e3e;"></i>
                        <span style="font-weight: 600; color: var(--text-color);">回答済み（<?php echo $join_count; ?>名）</span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($participants as $p): ?>
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: #f0f4f8; padding: 6px 14px; border-radius: 20px; font-size: 0.9rem;">
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($p['name']); ?></span>
                                <span style="color: #888; font-size: 0.8rem;"><?php echo htmlspecialchars($p['grade']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- ===== Event / Survey Manager View (Full Table) ===== -->
                <table class="res-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">名前</th>
                        <th style="width: 60px;">学年</th>
                        <?php if (!$is_survey): ?>
                            <th style="width: 80px;">ステータス</th>
                        <?php endif; ?>
                        <?php if ($is_manager): ?>
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
                            <?php if (!$is_survey): ?>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($p['status']); ?>">
                                    <?php echo getStatusLabel($p['status'], $is_survey_event); ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <?php if ($is_manager): ?>
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
                </table>
            <?php endif; ?>

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
