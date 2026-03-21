<?php
require_once '../config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    $stmt = $pdo->query("SELECT id, name, name_kana FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;

    foreach ($users as $user) {
        $name = $user['name'] ?? '';
        $kana = $user['name_kana'] ?? '';
        $changed = false;

        // Process name
        if ($name && mb_strpos($name, ' ') === false && mb_strpos($name, '　') === false) {
            if (mb_strlen($name) > 2) { // Cannot split 2 char names
                // Insert full-width space after 2nd char
                $name = mb_substr($name, 0, 2) . '　' . mb_substr($name, 2);
                $changed = true;
            }
        } elseif ($name && mb_strpos($name, ' ') !== false) {
            // Replace existing half-width space with full-width
            $name = str_replace(' ', '　', $name);
            $changed = true;
        }

        // Process kana
        if ($kana && mb_strpos($kana, ' ') === false && mb_strpos($kana, '　') === false) {
            if (mb_strlen($kana) > 2) {
                // Insert full-width space after 2nd char
                $kana = mb_substr($kana, 0, 2) . '　' . mb_substr($kana, 2);
                $changed = true;
            }
        } elseif ($kana && mb_strpos($kana, ' ') !== false) {
            $kana = str_replace(' ', '　', $kana);
            $changed = true;
        }

        if ($changed) {
            $upd = $pdo->prepare("UPDATE users SET name = ?, name_kana = ? WHERE id = ?");
            $upd->execute([$name, $kana, $user['id']]);
            $updated++;
        }
    }
    
    $message = "{$updated}名のデータに全角スペースを挿入・置換しました。";
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>名前スペース一括付与 | WHABITAT</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
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
        <div class="dashboard-container" style="max-width: 600px;">
            <div class="card">
                <h1 style="margin-bottom: 1.5rem;">全員の氏名にスペースを一括挿入</h1>
                
                <?php if ($message): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <p style="margin-bottom: 1rem; color: #666; line-height: 1.6;">
                    この処理を実行すると、スペースが含まれていない全メンバーの「氏名」と「ふりがな」に対し、<strong>前から2文字目の後に全角スペース（　）を自動で挿入</strong>します。<br>
                    また、既に半角スペースが含まれている場合は全角スペースに置換されます。
                </p>

                <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
                    <strong>【注意】</strong><br>
                    日本の苗字は3文字以上の場合（例：早稲田）や、ふりがなが3文字以上の場合（例：やまだ）でも、強制的に2文字で区切られます（早稲　田 / やま　だ）。実行後は手動での修正が必要になります。
                </div>

                <form method="POST" onsubmit="return confirm('本当に一括処理を実行しますか？この操作は元に戻せません。');">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="execute" value="1">
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; background-color: #e74c3c;">
                        一括挿入を実行する
                    </button>
                </form>

                <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #ddd; padding-top: 1.5rem;">
                    <p style="margin-bottom: 1rem; color: #666;">強制分割後、かんたんに手動修正できるツールを用意しました！</p>
                    <a href="bulk_edit_names.php" class="btn-secondary" style="display: block; width: 100%; box-sizing: border-box; text-align: center;">
                        手動一括修正ツール（タイピング用）を開く
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
