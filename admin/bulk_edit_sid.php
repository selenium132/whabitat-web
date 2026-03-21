<?php
require_once '../config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    if (isset($_POST['sids']) && is_array($_POST['sids'])) {
        $stmt = $pdo->prepare("UPDATE users SET student_id = ? WHERE id = ?");
        $updated = 0;
        foreach ($_POST['sids'] as $id => $sid) {
            // Trim leading/trailing whitespace
            $clean_sid = trim($sid);
            
            // Backend safeguard: If somehow a hyphen is still submitted, strip from hyphen onwards
            // Example: 1A234567-1 -> 1A234567
            if (strpos($clean_sid, '-') !== false) {
                $clean_sid = explode('-', $clean_sid)[0];
            }
            
            $stmt->execute([$clean_sid, $id]);
            $updated++;
        }
        $message = "全メンバーの学籍番号を一括保存しました。";
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT id, name, student_id, grade, admission_year FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学籍番号一括修正 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .bulk-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .bulk-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .highlight-change {
            background-color: #fff3cd !important;
            border-color: #ffeeba !important;
            transition: background-color 0.5s;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="members.php" class="logo" style="font-size: 1rem;">
                ← メンバー管理に戻る
            </a>
        </div>
    </header>
    <main>
        <div class="dashboard-container" style="max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
                <h1 style="margin: 0;">学籍番号一括修正ツール</h1>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="autoFixHyphens()" class="btn-secondary" style="background-color: #e67e22; color: white;">
                        <i class="fas fa-magic"></i> 全員のハイフン以降を消す
                    </button>
                    <button type="submit" form="bulkEditSidForm" class="btn-primary" style="padding: 0.8rem 2rem; font-size: 1.1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        一括で保存する
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <p style="margin-bottom: 1.5rem; color: #666; font-size: 0.9rem;">
                学籍番号にハイフン（-）と不要な数字を付けてしまっているメンバーの番号を一括で修正できます。<br>
                「全員のハイフン以降を消す」ボタンを押すと入力欄のハイフン以降が自動で削除されます。確認後、「一括で保存する」を押してください。<br>
                <span style="color: #e74c3c;">未入力（空欄）のままでもエラーにならず保存可能です。</span>
            </p>

            <div class="card" style="padding: 0;">
                <form id="bulkEditSidForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 20%;">代 / 入学</th>
                                    <th style="width: 40%;">名前</th>
                                    <th style="width: 40%;">学籍番号</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $m): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight:bold; color:var(--primary-color);"><?php echo htmlspecialchars($m['grade'] ?? '-'); ?></span><br>
                                            <span style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars($m['admission_year'] ?? ''); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                                        </td>
                                        <td>
                                            <!-- Remove "required" to allow empty strings to save gracefully -->
                                            <input type="text" name="sids[<?php echo $m['id']; ?>]" value="<?php echo htmlspecialchars($m['student_id'] ?? ''); ?>" class="bulk-input sid-input">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding: 1.5rem; text-align: center; background: #fafafa; border-top: 1px solid #ddd;">
                        <button type="submit" class="btn-primary" style="padding: 1rem 3rem; font-size: 1.2rem;">
                            一括で保存する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function autoFixHyphens() {
            const inputs = document.querySelectorAll('.sid-input');
            let count = 0;
            inputs.forEach(input => {
                const val = input.value.trim();
                const hyphenIndex = val.indexOf('-');
                if (hyphenIndex !== -1) {
                    input.value = val.substring(0, hyphenIndex);
                    input.classList.add('highlight-change');
                    count++;
                }
            });
            if (count > 0) {
                alert(count + "名の学籍番号からハイフン以降を削除しました。変更を確定するには保存ボタンを押してください。");
            } else {
                alert("ハイフンが含まれる学籍番号は見つかりませんでした。");
            }
        }
    </script>
</body>
</html>
