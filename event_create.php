<?php
require_once 'config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '無題のイベント';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? date('Y-m-d H:i:s'); // Default to now if not set
    $form_schema = $_POST['form_schema'] ?? '[]'; // JSON string

    if ($title) {
        $pdo = getDB();
        // Check if column exists (optional safety, or just assume migration ran)
        // We assume 'form_schema' column exists in 'events' table
        
        $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, created_by, form_schema) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $event_date, $_SESSION['user_id'], $form_schema])) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = '作成に失敗しました。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フォーム作成 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f0ebf8; /* Google Forms light purple tint bg */
            font-family: 'Roboto', sans-serif;
            padding-bottom: 60px;
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
            max-width: 770px;
            margin: 100px auto 40px;
            padding-bottom: 50px;
        }

        /* Top Title Card */
        .title-card {
            background: white;
            border-radius: 8px;
            border-top: 10px solid rgb(103, 58, 183); /* Purple accent */
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 24px;
            position: relative;
        }
        
        .title-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 10px;
            background: rgb(103, 58, 183);
            border-radius: 8px 8px 0 0;
        }

        .title-input {
            width: 100%;
            font-size: 32px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 0;
            margin-bottom: 8px;
            outline: none;
            font-family: inherit;
        }
        .title-input:focus {
            border-bottom: 2px solid rgb(103, 58, 183);
        }
        .desc-input {
            width: 100%;
            font-size: 14px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 4px 0;
            outline: none;
            resize: none;
            font-family: inherit;
        }
        .desc-input:focus {
            border-bottom: 2px solid rgb(103, 58, 183);
        }
        
        /* Date Info (Hidden visually or styled to look like meta info) */
        .meta-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.9rem;
        }

        /* Question Cards */
        .question-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin-bottom: 24px;
            position: relative;
            transition: box-shadow 0.2s;
            border-left: 6px solid transparent;
        }
        .question-card.active {
            border-left: 6px solid rgb(66, 133, 244);
            box-shadow: 0 4px 5px rgba(0,0,0,0.2);
        }

        .q-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 20px;
        }

        .q-text-input {
            flex-grow: 1;
            font-size: 16px;
            padding: 16px;
            background-color: #f8f9fa;
            border: none;
            border-bottom: 1px solid #808080;
            outline: none;
            transition: 0.2s;
            resize: none;
            height: 56px;
            box-sizing: border-box;
        }
        .q-text-input:focus {
            background-color: #e8f0fe;
            border-bottom: 2px solid rgb(103, 58, 183);
        }

        .q-type-select {
            width: 220px;
            padding: 12px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 14px;
            color: #202124;
            cursor: pointer;
        }

        /* Options Area */
        .q-content {
            margin-bottom: 20px;
        }

        .option-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }
        
        .option-icon {
            color: #dadce0;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .option-input {
            flex-grow: 1;
            border: none;
            font-size: 14px;
            padding: 8px 0;
            outline: none;
        }
        .option-input:hover {
            border-bottom: 1px solid #e0e0e0;
        }
        .option-input:focus {
            border-bottom: 2px solid rgb(103, 58, 183);
        }

        .option-remove {
            color: #5f6368;
            cursor: pointer;
            visibility: hidden;
            font-size: 16px;
        }
        .option-row:hover .option-remove {
            visibility: visible;
        }

        .add-option-link {
            color: #70757a;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            margin-top: 5px;
        }
        .add-option-link:hover {
            color: #202124;
        }
        .add-option-btn {
            color: rgb(103, 58, 183);
        }

        /* Long Text View */
        .long-text-placeholder {
            border-bottom: 1px dotted #dadce0;
            color: #70757a;
            font-size: 14px;
            padding: 10px 0;
            width: 50%;
        }

        /* Footer */
        .q-footer {
            border-top: 1px solid #dadce0;
            padding-top: 16px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
        }
        
        .icon-btn {
            color: #5f6368;
            font-size: 20px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .icon-btn:hover {
            background-color: rgba(95,99,104,0.08);
        }
        
        .divider-vertical {
            width: 1px;
            height: 30px;
            background-color: #dadce0;
            margin: 0 10px;
        }

        .required-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #202124;
            cursor: pointer;
        }

        /* Floating Menu */
        .floating-menu {
            position: fixed;
            right: calc(50% - 460px); /* Adjust based on container width */
            top: 200px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            display: flex;
            flex-direction: column;
            padding: 8px 0;
            z-index: 100;
        }
        @media (max-width: 950px) {
            .floating-menu {
                position: fixed;
                bottom: 20px;
                right: 20px;
                top: auto;
                flex-direction: row;
                padding: 0 8px;
            }
        }

        .float-btn {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #5f6368;
            border-radius: 50%;
            transition: 0.2s;
        }
        .float-btn:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .float-btn.add-q {
            color: rgb(103, 58, 183);
            font-size: 24px;
        }
        
        /* Action Bar (Top Right) */
        .action-bar {
            position: absolute;
            right: 20px;
            top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-save {
            background-color: rgb(103, 58, 183);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .btn-save:hover {
            background-color: rgb(85, 45, 160);
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

    </style>
</head>
<body>
    
    <form id="mainForm" method="POST" action="">
        <input type="hidden" name="form_schema" id="form_schema_input">
        
        <header class="header">
            <div class="header-inner" style="position: relative;">
                <a href="dashboard.php" class="logo">WHABITAT <span style="font-size: 14px; color: #5f6368; font-weight: normal; margin-left: 10px;">Create Form</span></a>
                
                <div class="action-bar">
                    <button type="button" class="btn-save" onclick="saveForm()">作成 (Save)</button>
                    <a href="dashboard.php" style="margin-left: 10px; text-decoration: none; color: #5f6368; display: flex; align-items: center; padding: 0 10px;">キャンセル</a>
                </div>
            </div>
        </header>

        <div class="form-builder-container">
            
            <!-- Form Title Card -->
            <div class="title-card">
                <input type="text" name="title" id="form-title" class="title-input" placeholder="無題のフォーム" value="新規イベント参加フォーム" required>
                <input type="text" name="description" id="form-desc" class="desc-input" placeholder="フォームの説明" value="">
                
                <!-- Extra fields for Events table -->
                <div class="meta-info">
                   <label style="font-size: 0.8rem;">開催日時: <input type="datetime-local" name="event_date" required style="border:1px solid #ddd; padding: 4px; border-radius: 4px;"></label>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questions-container"></div>

            <!-- Floating Sidebar -->
            <div class="floating-menu">
                <div class="float-btn add-q" onclick="addQuestion()" title="質問を追加">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <!-- Other buttons can act as placeholders or future features -->
                <div class="float-btn" title="画像を挿入 (未実装)">
                    <i class="far fa-image"></i>
                </div>
                <div class="float-btn" title="動画を追加 (未実装)">
                    <i class="fab fa-youtube"></i>
                </div>
            </div>

        </div>
    </form>

    <script>
        const container = document.getElementById('questions-container');
        // We track questions in DOM primarily, but assign IDs
        let questionIdCounter = 0;

        // Initialize with one question
        window.onload = () => {
             addQuestion('radio'); 
        };

        function createQuestionElement(id, initialType = 'radio') {
            const div = document.createElement('div');
            div.className = 'question-card active';
            div.id = `q-${id}`;
            div.dataset.qid = id; // Store ID for logic
            div.onclick = (e) => {
                // Prevent triggering when clicking inputs (to avoid re-focusing weirdness if needed)
                if(e.target.tagName !== 'INPUT') setActive(id);
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

        function renderContent(id, type) {
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

                // Add initial option
                addOption(id, type, 'オプション 1');

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
                icon.style.color = 'rgb(103, 58, 183)';
                icon.setAttribute('data-required', 'true');
            } else {
                icon.className = 'fas fa-toggle-off';
                icon.style.color = '#dadce0';
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
