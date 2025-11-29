<?php
require_once 'config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? '';

    if ($title && $event_date) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $event_date, $_SESSION['user_id']])) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'イベント作成中にエラーが発生しました。';
        }
    } else {
        $error = 'タイトルと開催日時は必須です。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 600px;
            margin: 100px auto 60px;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-textarea {
            height: 120px;
            resize: vertical;
        }
        .btn-submit {
            background-color: #333;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-submit:hover {
            background-color: #555;
        }
        .error-message {
            color: #e74c3c;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo">WHABITAT</a>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>新規イベント作成</h1>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">イベント名</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">開催日時</label>
                    <input type="datetime-local" name="event_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">詳細</label>
                    <textarea name="description" class="form-textarea"></textarea>
                </div>
                <button type="submit" class="btn-submit">作成する</button>
                <a href="dashboard.php" style="margin-left: 1rem;">キャンセル</a>
            </form>
        </div>
    </main>
</body>
</html>
