<?php
require_once 'config.php';
requireLogin();

// Access Control
$event_id = $_GET['id'] ?? null;

if ($event_id) {
    // Edit Mode: Check if Admin or Event Admin
    if (!isEventAdmin($event_id)) {
        header("Location: dashboard.php");
        exit;
    }
} else {
    // Create Mode: Only Global Admin
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php");
        exit;
    }
}

$edit_mode = false;
$event_data = null;

// Handle Edit Mode - Fetch Data
if ($event_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($event_data) {
        $edit_mode = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '無題のイベント';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? date('Y-m-d H:i:s');
    $form_schema = $_POST['form_schema'] ?? '[]';
    $target_id = $_POST['event_id'] ?? null;
    
    // Response schedule (NULL = default behavior)
    $open_at = !empty($_POST['open_at']) ? $_POST['open_at'] : null;
    $close_at = !empty($_POST['close_at']) ? $_POST['close_at'] : null;
    
    // Capacity (NULL = unlimited)
    $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;

    if ($title) {
        $pdo = getDB();
        
        if ($target_id) {
            // Update
            $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, form_schema = ?, open_at = ?, close_at = ?, capacity = ? WHERE id = ?");
            $res = $stmt->execute([$title, $description, $event_date, $form_schema, $open_at, $close_at, $capacity, $target_id]);
            $event_id_final = $target_id;
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, created_by, form_schema, open_at, close_at, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $res = $stmt->execute([$title, $description, $event_date, $_SESSION['user_id'], $form_schema, $open_at, $close_at, $capacity]);
            $event_id_final = $pdo->lastInsertId();
        }

        if ($res) {
            // --- Handle Event Admins ---
            // Ensure table exists (Failsafe)
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_event_admin (event_id, user_id)
            )");

            // 1. Delete existing admins for this event
            $del_stmt = $pdo->prepare("DELETE FROM event_admins WHERE event_id = ?");
            $del_stmt->execute([$event_id_final]);

            // 2. Insert new admins
            if (!empty($_POST['event_admins']) && is_array($_POST['event_admins'])) {
                $ins_stmt = $pdo->prepare("INSERT INTO event_admins (event_id, user_id) VALUES (?, ?)");
                foreach ($_POST['event_admins'] as $uid) {
                    $ins_stmt->execute([$event_id_final, $uid]);
                }
            }
            // ---------------------------

            header("Location: dashboard.php");
            exit;
        } else {
            $error = '保存に失敗しました。';
        }
    }
}

// Fetch Users for Admin Selection (Exclude Global Admins, Group by Grade)
$pdo = getDB();
$users_stmt = $pdo->query("SELECT id, name, grade FROM users WHERE is_approved = 1 AND role != 'admin' ORDER BY grade ASC, name ASC");
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group users by grade
$users_by_grade = [];
foreach ($all_users as $user) {
    $g = $user['grade'] ?: '未設定'; // Handle empty grade
    $users_by_grade[$g][] = $user;
}

// Fetch Current Event Admins if editing
$current_admins = [];
if ($edit_mode) {
    if ($pdo->query("SHOW TABLES LIKE 'event_admins'")->rowCount() > 0) {
        $stmt_admins = $pdo->prepare("SELECT user_id FROM event_admins WHERE event_id = ?");
        $stmt_admins->execute([$event_id]);
        $current_admins = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);
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
    <title>フォーム作成 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-color);
            font-family: 'Noto Sans JP', sans-serif;
            padding-bottom: 80px;
        }
        .header {
            background: white;
            box-shadow: none;
            border-bottom: 1px solid #e0e0e0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .form-builder-container {
            max-width: 800px;
            /* Wider for admin list? No, 800 is fine */
            margin: 100px auto 40px;
            padding: 0 1rem;
        }

        /* Top Title Card */
        .title-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .title-input {
            width: 100%;
            font-size: 1.8rem;
            font-weight: 700;
            border: none;
            border-bottom: 1px solid transparent;
            padding: 8px 0;
            margin-bottom: 12px;
            outline: none;
            font-family: inherit;
            color: var(--primary-color);
            background: transparent;
            transition: border 0.3s;
        }
        .title-input:focus {
            border-bottom: 1px solid var(--accent-blue);
        }
        .desc-input {
            width: 100%;
            font-size: 1rem;
            border: none;
            border-bottom: 1px solid transparent;
            padding: 4px 0;
            outline: none;
            resize: none;
            font-family: inherit;
            color: var(--text-color);
            line-height: 1.6;
            transition: border 0.3s;
        }
        .desc-input:focus {
            border-bottom: 1px solid var(--accent-blue);
        }
        
        /* Date Info & Admin Selection */
        .meta-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        .meta-row {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 10px;
        }
        .meta-row input[type="datetime-local"] {
            border: 1px solid #ddd; 
            padding: 8px 12px; 
            border-radius: 8px;
            font-family: inherit;
            margin-left: 10px;
            font-size: 1rem;
            color: var(--text-color);
            background: #f9f9f9;
        }
        
        .admin-selection-area {
            background: #fcfcfc;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 0.5rem 1rem; /* Compact padding */
            margin-top: 10px;
        }
        .admin-selection-details summary {
            font-weight: 600;
            cursor: pointer;
            padding: 0.5rem 0;
            color: var(--text-color);
            list-style: none; /* Hide default triangle in some browsers */
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-selection-details summary::-webkit-details-marker {
            display: none;
        }
        .admin-selection-details summary::after {
            content: '\f078'; /* FontAwesome chevron down */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            font-size: 0.8rem;
            transition: transform 0.2s;
            margin-left: auto;
        }
        .admin-selection-details[open] summary::after {
            transform: rotate(180deg);
        }

        .admin-checkbox-list {
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .grade-group {
            margin-bottom: 15px;
        }
        .grade-header {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 700;
            margin-bottom: 5px;
            padding-bottom: 2px;
            border-bottom: 1px solid #eee;
        }
        .grade-users {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
        }
        .admin-checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .admin-checkbox-item:hover {
            background: #f0f0f0;
        }
        .admin-checkbox-item input {
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        /* Question Cards */
        .question-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid transparent;
        }
        .question-card.active {
            box-shadow: var(--shadow-md);
            border-color: var(--accent-blue);
        }

        .q-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .q-text-input {
            flex-grow: 1;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 12px;
            background-color: #f9f9f9;
            border: 1px solid transparent;
            border-radius: 8px;
            outline: none;
            transition: 0.2s;
            resize: none;
            box-sizing: border-box;
            color: var(--primary-color);
        }
        .q-text-input:focus {
            background-color: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .q-type-select {
            width: 200px;
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-color);
            cursor: pointer;
            background: #fff;
            transition: 0.2s;
        }
        .q-type-select:hover {
            border-color: #ddd;
        }
        .q-type-select:focus {
            border-color: var(--accent-blue);
            outline: none;
        }

        /* Options Area */
        .q-content {
            margin-bottom: 24px;
            padding-left: 4px;
        }

        .option-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
        }
        
        .option-icon {
            color: #ccc;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .option-input {
            flex-grow: 1;
            border: 1px solid transparent;
            border-bottom: 1px solid #eee;
            font-size: 1rem;
            padding: 8px 0;
            outline: none;
            transition: 0.2s;
            color: var(--text-color);
        }
        .option-input:focus {
            border-bottom-color: var(--accent-blue);
        }

        .option-remove {
            color: #ccc;
            cursor: pointer;
            visibility: hidden;
            font-size: 14px;
            padding: 8px;
        }
        .option-row:hover .option-remove {
            visibility: visible;
        }
        .option-remove:hover {
            color: var(--accent-red);
        }

        .add-option-link {
            color: var(--accent-blue);
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            margin-top: 5px;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .add-option-link:hover {
            background: rgba(52, 152, 219, 0.05);
        }
        .add-option-btn {
            font-weight: 500;
        }

        /* Long Text View */
        .long-text-placeholder {
            border-bottom: 1px dashed #ccc;
            color: #999;
            font-size: 0.9rem;
            padding: 10px 0;
            width: 60%;
        }

        /* Footer */
        .q-footer {
            border-top: 1px solid #f0f0f0;
            padding-top: 16px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
        }
        
        .icon-btn {
            color: #888;
            font-size: 1.1rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: 0.2s;
        }
        .icon-btn:hover {
            background-color: #f5f5f5;
            color: var(--text-color);
        }
        
        .divider-vertical {
            width: 1px;
            height: 24px;
            background-color: #eee;
            margin: 0 5px;
        }

        .required-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-color);
            cursor: pointer;
            user-select: none;
        }

        /* Floating Menu */
        .floating-menu {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            border-radius: 50px;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column; /* Default to column?? or stick to row */
            padding: 10px;
            z-index: 100;
            gap: 10px;
        }
        /* Let's make it a row at bottom right, simple and accessible */
        .floating-menu {
            flex-direction: row;
            padding: 0 10px;
            height: 60px;
            align-items: center;
        }

        .float-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            border-radius: 50%;
            transition: 0.2s;
            font-size: 1.2rem;
        }
        .float-btn:hover {
            background-color: #f0f0f0;
            color: var(--primary-color);
        }
        .float-btn.add-q {
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-md);
        }
        .float-btn.add-q:hover {
            background-color: #1a252f;
            transform: translateY(-2px);
        }
        
        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Action Bar (Top Right) */
        .action-bar {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-save {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px; /* Pill shape matches other btns */
            font-weight: 600;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .btn-save:hover {
            background-color: #1a252f;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

    </style>
</head>
<body>
    
    <form id="mainForm" method="POST" action="">
        <input type="hidden" name="form_schema" id="form_schema_input">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="event_id" value="<?php echo $event_data['id']; ?>">
        <?php endif; ?>
        
        <header class="header">
            <div class="header-inner">
                <a href="dashboard.php" class="logo" style="font-size: 1rem; font-weight: 500; display: flex; align-items: center;">
                    <i class="fas fa-chevron-left" style="margin-right: 8px; font-size: 0.8rem;"></i> 一覧に戻る
                </a>
                
                <div class="action-bar">
                    <?php if ($edit_mode): ?>
                         <a href="event_delete.php?id=<?php echo $event_data['id']; ?>" class="icon-btn" style="color: #d93025; text-decoration: none; margin-right: 15px;" onclick="return confirm('本当に削除しますか？\nこの操作は取り消せません。');" title="イベントを削除">
                            <i class="far fa-trash-alt"></i>
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-save" onclick="saveForm()"><?php echo $edit_mode ? '更新 (Update)' : '作成 (Save)'; ?></button>
                </div>
            </div>
        </header>

        <div class="form-builder-container">
            
            <!-- Form Title Card -->
            <div class="title-card">
                <input type="text" name="title" id="form-title" class="title-input" placeholder="無題のフォーム" value="<?php echo $edit_mode ? htmlspecialchars($event_data['title']) : '新規イベント参加フォーム'; ?>" required>
                <input type="text" name="description" id="form-desc" class="desc-input" placeholder="フォームの説明" value="<?php echo $edit_mode ? htmlspecialchars($event_data['description']) : ''; ?>">
                
                <!-- Extra fields for Events table -->
                <div class="meta-info">
                    <div class="meta-row">
                        <label style="font-weight: 600;">開催日時:</label>
                        <input type="datetime-local" name="event_date" value="<?php echo $edit_mode ? date('Y-m-d\TH:i', strtotime($event_data['event_date'])) : ''; ?>" required>
                    </div>
                    
                    <!-- Admin Selection -->
                    <div class="admin-selection-area">
                        <details class="admin-selection-details">
                            <summary>
                                <span><i class="fas fa-user-shield"></i> イベント管理者設定</span>
                            </summary>
                            
                            <div class="admin-checkbox-list">
                                <?php if (empty($users_by_grade)): ?>
                                    <div style="color: #999; padding: 10px;">追加可能なメンバーがいません</div>
                                <?php else: ?>
                                    <?php foreach ($users_by_grade as $grade => $users): ?>
                                        <div class="grade-group">
                                            <div class="grade-header"><?php echo htmlspecialchars($grade); ?></div>
                                            <div class="grade-users">
                                                <?php foreach ($users as $user): ?>
                                                    <?php 
                                                        $checked = in_array($user['id'], $current_admins) ? 'checked' : '';
                                                    ?>
                                                    <label class="admin-checkbox-item">
                                                        <input type="checkbox" name="event_admins[]" value="<?php echo $user['id']; ?>" <?php echo $checked; ?>>
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                    
                    <!-- Response Schedule -->
                    <div class="admin-selection-area">
                        <details class="admin-selection-details">
                            <summary>
                                <span><i class="fas fa-cog"></i> 詳細設定</span>
                            </summary>
                            
                            <div style="padding: 15px; display: flex; flex-direction: column; gap: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <label style="min-width: 80px; font-weight: 500;">開始日時:</label>
                                    <input type="datetime-local" name="open_at" 
                                        value="<?php echo ($edit_mode && !empty($event_data['open_at'])) ? date('Y-m-d\TH:i', strtotime($event_data['open_at'])) : ''; ?>"
                                        style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                    <span style="color: #888; font-size: 0.85rem;">空欄 = 即時公開</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <label style="min-width: 80px; font-weight: 500;">締切日時:</label>
                                    <input type="datetime-local" name="close_at" 
                                        value="<?php echo ($edit_mode && !empty($event_data['close_at'])) ? date('Y-m-d\TH:i', strtotime($event_data['close_at'])) : ''; ?>"
                                        style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                    <span style="color: #888; font-size: 0.85rem;">空欄 = 締切なし</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <label style="min-width: 80px; font-weight: 500;">定員:</label>
                                    <input type="number" name="capacity" min="1" 
                                        value="<?php echo ($edit_mode && !empty($event_data['capacity'])) ? intval($event_data['capacity']) : ''; ?>"
                                        placeholder="例: 20"
                                        style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; width: 100px;">
                                    <span style="color: #888; font-size: 0.85rem;">空欄 = 定員なし</span>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            <!-- Attendance Question Notice -->
            <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid #4caf50; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-check-circle" style="color: #4caf50; font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: 600; color: #2e7d32;">出欠確認は自動で追加されます</div>
                    <div style="font-size: 0.85rem; color: #558b2f; margin-top: 3px;">「参加 / 不参加 / 未定」の選択項目は自動的に表示されます。追加の質問があれば下から追加してください。</div>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questions-container"></div>

            <!-- Add Question Button Area -->
            <div style="text-align: center; margin-top: 20px; padding-bottom: 30px;">
                <button type="button" class="btn-secondary" onclick="addQuestion()" style="border-radius: 50px; padding: 12px 30px; font-weight: 600; font-size: 1rem; box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus-circle" style="color: var(--primary-color);"></i> 質問を追加
                </button>
            </div>

        </div>
    </form>

    <script>
        const container = document.getElementById('questions-container');
        // We track questions in DOM primarily, but assign IDs
        let questionIdCounter = 0;

        // Existing Schema
        // Security Fix: json_encode the decoded array to ensure safe JS output
        const existingSchema = <?php 
            $schema_data = ($edit_mode && !empty($event_data['form_schema'])) ? json_decode($event_data['form_schema']) : [];
            echo json_encode($schema_data); 
        ?>;

        // Initialize with one question or existing
        window.onload = () => {
             if (existingSchema.length > 0) {
                 existingSchema.forEach(q => {
                     restoreQuestion(q);
                 });
             } else {
                 addQuestion('radio'); 
             }
        };


        function restoreQuestion(qData) {
            questionIdCounter++;
            const id = questionIdCounter;
            const type = qData.type;
            
            const newQ = createQuestionElement(id, type);
            container.appendChild(newQ);
            
            // Set Values
            const card = document.getElementById(`q-${id}`);
            card.querySelector('.q-text-input').value = qData.title;
            
            // Render Content first to have inputs
            renderContent(id, type, qData.options); // Pass options to render
            
            // Required toggle
            if (qData.required) {
                // It defaults to false, so toggle it
                toggleRequired(id);
            }
        }
        
        function createQuestionElement(id, initialType = 'radio') {
            const div = document.createElement('div');
            div.className = 'question-card active';
            div.id = `q-${id}`;
            div.dataset.qid = id; // Store ID for logic
            div.onclick = (e) => {
                // Prevent triggering when clicking inputs (to avoid re-focusing weirdness if needed)
                if(e.target.tagName !== 'INPUT') setActive(id);
                // Also prevent if clicking delete/copy
            };
            
            div.innerHTML = `
                <div class="q-header">
                    <input type="text" class="q-text-input" placeholder="質問" value="無題の質問">
                    <select class="q-type-select" onchange="changeType(${id}, this.value)">
                        <option value="paragraph" ${initialType === 'paragraph' ? 'selected' : ''}>記述式 (Long text)</option>
                        <option value="radio" ${initialType === 'radio' ? 'selected' : ''}>ラジオボタン</option>
                        <option value="checkbox" ${initialType === 'checkbox' ? 'selected' : ''}>チェックボックス</option>
                        <option value="dropdown" ${initialType === 'dropdown' ? 'selected' : ''}>プルダウン</option>
                    </select>
                </div>
                
                <div class="q-content" id="q-content-${id}">
                    <!-- Content injected by JS -->
                </div>

                <div class="q-footer">
                    <div class="icon-btn" onclick="duplicateQuestion(${id})" title="コピー"><i class="far fa-copy"></i></div>
                    <div class="icon-btn" onclick="deleteQuestion(${id})" title="削除"><i class="far fa-trash-alt"></i></div>
                    <div class="divider-vertical"></div>
                    <div class="required-toggle" onclick="toggleRequired(${id})">
                        <span>必須</span>
                        <i class="fas fa-toggle-off" style="font-size: 24px; color: #dadce0;" id="req-toggle-${id}" data-required="false"></i>
                    </div>
                </div>
            `;
            return div;
        }

        function setActive(id) {
            document.querySelectorAll('.question-card').forEach(c => c.classList.remove('active'));
            const card = document.getElementById(`q-${id}`);
            if (card) card.classList.add('active');
        }

        function addQuestion(type = 'radio') {
            questionIdCounter++;
            const newQ = createQuestionElement(questionIdCounter, type);
            container.appendChild(newQ);
            renderContent(questionIdCounter, type);
            setActive(questionIdCounter);
            newQ.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function changeType(id, newType) {
            renderContent(id, newType);
        }

        function renderContent(id, type, existingOptions = []) {
            const contentDiv = document.getElementById(`q-content-${id}`);
            // Save existing options if converting between list types? 
            // For simplicity, we clear and re-init for now, unless we want to be fancy.
            contentDiv.innerHTML = '';

            if (type === 'paragraph') {
                contentDiv.innerHTML = `<div class="long-text-placeholder">長文回答テキスト</div>`;
            } else {
                // List based types
                const listContainer = document.createElement('div');
                listContainer.id = `options-list-${id}`;
                listContainer.className = 'options-list';
                contentDiv.appendChild(listContainer);

                // Add existing options or initial one
                if (existingOptions.length > 0) {
                    existingOptions.forEach(opt => {
                        addOption(id, type, opt);
                    });
                } else {
                    addOption(id, type, 'オプション 1');
                }

                // "Add Option" link
                const addLink = document.createElement('div');
                addLink.className = 'add-option-link';
                addLink.innerHTML = `
                    <div class="option-icon" style="visibility:hidden;"><i class="fas fa-circle"></i></div>
                    <span class="add-option-btn" onclick="addOption(${id}, '${type}')">オプションを追加</span>
                `;
                contentDiv.appendChild(addLink);
            }
        }

        function addOption(id, type, value = '') {
            const list = document.getElementById(`options-list-${id}`);
            const count = list ? list.children.length + 1 : 1;
            const optionText = value || `オプション ${count}`;

            const row = document.createElement('div');
            row.className = 'option-row';
            
            let iconClass = 'fa-circle'; 
            if (type === 'checkbox') iconClass = 'fa-square';
            
            let iconHtml = '';
            if (type === 'dropdown') {
                iconHtml = `<div class="option-icon" style="font-size: 14px;">${count}.</div>`;
            } else {
                iconHtml = `<div class="option-icon"><i class="far ${iconClass}"></i></div>`;
            }

            row.innerHTML = `
                ${iconHtml}
                <input type="text" class="option-input" value="${optionText}">
                <div class="option-remove" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></div>
            `;
            
            list.appendChild(row);
        }
        
        function deleteQuestion(id) {
            const card = document.getElementById(`q-${id}`);
            if (container.children.length > 1) {
                card.remove();
            } else {
                alert("これ以上削除できません");
            }
        }

        function duplicateQuestion(id) {
            // Simply add new
            const typeSelect = document.querySelector(`#q-${id} .q-type-select`);
            addQuestion(typeSelect.value);
        }

        function toggleRequired(id) {
            const icon = document.getElementById(`req-toggle-${id}`);
            const isReq = icon.getAttribute('data-required') === 'true';
            
            if (!isReq) {
                icon.className = 'fas fa-toggle-on';
                icon.style.color = 'var(--accent-blue)';
                icon.setAttribute('data-required', 'true');
            } else {
                icon.className = 'fas fa-toggle-off';
                icon.style.color = '#ccc';
                icon.setAttribute('data-required', 'false');
            }
        }

        // ----------------------------------------
        // Data Saving Logic
        // ----------------------------------------
        function saveForm() {
            const formTitle = document.getElementById('form-title').value;
            if (!formTitle) {
                alert("フォームのタイトルを入力してください");
                return;
            }

            const questions = [];
            const cards = document.querySelectorAll('.question-card');
            
            cards.forEach(card => {
                const id = card.dataset.qid;
                const text = card.querySelector('.q-text-input').value;
                const type = card.querySelector('.q-type-select').value;
                const required = card.querySelector('.required-toggle i').getAttribute('data-required') === 'true';
                
                let options = [];
                if (type !== 'paragraph') {
                    const inputs = card.querySelectorAll('.option-input');
                    inputs.forEach(input => options.push(input.value));
                }

                questions.push({
                    title: text,
                    type: type,
                    required: required,
                    options: options
                });
            });

            const schema = JSON.stringify(questions);
            document.getElementById('form_schema_input').value = schema;
            
            // Submit the real form
            document.getElementById('mainForm').submit();
        }

    </script>
</body>
</html>
