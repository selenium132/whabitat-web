<?php
require_once '../config.php';
requireAdmin();

$pdo = getDB();
ensureUsersEmailColumn($pdo);

// config.php の requireLogin 内 profile_incomplete と同じ必須項目に揃える。
// （ここに載る人＝会員ページを開くとプロフィール編集へ飛ばされる人）
$required = [
    'name_kana'  => 'ふりがな',
    'email'      => 'メール',
    'gender'     => '性別',
    'zipcode'    => '郵便番号',
    'address'    => '住所',
    'phone'      => '電話',
    'birthdate'  => '生年月日',
    'grade'      => '代',
    'student_id' => '学籍番号',
];

$members = $pdo->query("SELECT id, name, name_kana, email, gender, zipcode, address, phone, birthdate, grade, student_id, is_approved, created_at, line_user_id FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$incomplete = [];
foreach ($members as $m) {
    $missing = [];
    foreach ($required as $key => $label) {
        if (empty($m[$key])) {
            $missing[] = $label;
        }
    }
    if ($missing) {
        $m['_missing'] = $missing;
        $incomplete[] = $m;
    }
}

// LINEリマインド送信（一覧に載っている本人へ push）
$flash = '';
$flash_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remind') {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    $target_id = (int)($_POST['user_id'] ?? 0);
    $target = null;
    foreach ($incomplete as $m) {
        if ((int)$m['id'] === $target_id) { $target = $m; break; }
    }
    if (!$target) {
        $flash = '対象が見つかりません（既に入力済みの可能性があります）。';
    } elseif (empty($target['line_user_id'])) {
        $flash = 'この会員はLINE未連携のため送信できません。';
    } else {
        $msg = "【WHABITAT】プロフィールに未入力の項目があります（" . implode('、', $target['_missing']) . "）。\n"
             . "こちらから入力をお願いします🙏\nhttps://whabitathome.com/register_profile.php";
        if (linePushToUser($target['line_user_id'], $msg)) {
            $flash_ok = true;
            $flash = ($target['name'] !== '' ? $target['name'] : 'ID:' . $target_id) . ' さんにLINEでリマインドを送信しました。';
            auditLog('remind_incomplete_profile', $target_id, $target['name'] ?? null, null);
        } else {
            $flash = 'LINE送信に失敗しました（ブロックされている可能性があります）。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>未入力者一覧 | WHABITAT 管理</title>
    <style>
        body { font-family: -apple-system, "Hiragino Kaku Gothic ProN", sans-serif; color: #1a1a1a; background: #fff; margin: 0; padding: 1.5rem; }
        h1 { font-size: 1.3rem; font-weight: 600; }
        .lead { color: #555; font-size: .9rem; line-height: 1.6; margin-bottom: 1rem; }
        .count { font-weight: 600; }
        a.back { display: inline-block; margin-bottom: 1rem; color: #333; font-size: .85rem; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { border-collapse: collapse; width: 100%; font-size: .85rem; }
        th, td { border: 1px solid #e2e2e2; padding: .5rem .6rem; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:nth-child(even) td { background: #fafafa; }
        .missing { color: #b0453a; font-weight: 600; }
        .pending { color: #888; font-size: .8rem; }
        .none { color: #3f7d54; font-weight: 600; padding: 1rem 0; }
        .flash { border: 1px solid #ccc; background: #f7f7f7; padding: .7rem 1rem; font-size: .85rem; margin-bottom: 1rem; border-radius: 6px; }
        .flash.ok { border-color: #3f7d54; color: #3f7d54; background: #f3f8f4; }
        .flash.ng { border-color: #b0453a; color: #b0453a; background: #fbf4f3; }
        .remind-btn { font-size: .78rem; padding: .3rem .7rem; border: 1px solid #1a1a1a; background: #fff; color: #1a1a1a; border-radius: 999px; cursor: pointer; white-space: nowrap; }
        .remind-btn:hover { background: #1a1a1a; color: #fff; }
        .no-line { color: #aaa; font-size: .78rem; white-space: nowrap; }
    </style>
</head>
<body>
    <a class="back" href="/admin/members.php">← 名簿管理に戻る</a>
    <h1>必須項目が未入力の会員</h1>
    <p class="lead">
        ここに載っている人は、会員ページを開くと自動的にプロフィール編集へ誘導され、埋めるまで先に進めません（<?php echo htmlspecialchars(implode(' / ', $required)); ?> のいずれかが空）。<br>
        対象: <span class="count"><?php echo count($incomplete); ?></span> 名 ／ 全 <?php echo count($members); ?> 名中
    </p>

    <?php if ($flash !== ''): ?>
        <p class="flash <?php echo $flash_ok ? 'ok' : 'ng'; ?>"><?php echo htmlspecialchars($flash); ?></p>
    <?php endif; ?>

    <?php if (empty($incomplete)): ?>
        <p class="none">未入力者はいません 🎉</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>氏名</th>
                    <th>代</th>
                    <th>メール</th>
                    <th>承認</th>
                    <th>未入力の項目</th>
                    <th>リマインド</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($incomplete as $m): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo ($m['name'] !== '' && $m['name'] !== null) ? htmlspecialchars($m['name']) : '<span class="pending">(氏名未入力)</span>'; ?></td>
                        <td><?php echo htmlspecialchars($m['grade'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($m['email'] ?? ''); ?></td>
                        <td><?php echo $m['is_approved'] ? '承認済' : '<span class="pending">未承認</span>'; ?></td>
                        <td class="missing"><?php echo htmlspecialchars(implode('、', $m['_missing'])); ?></td>
                        <td>
                            <?php if (!empty($m['line_user_id'])): ?>
                                <form method="post" style="margin:0" onsubmit="return confirm('<?php echo htmlspecialchars($m['name'] !== '' && $m['name'] !== null ? $m['name'] : 'この会員', ENT_QUOTES); ?> さんへLINEでリマインドを送りますか？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="remind">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                                    <button type="submit" class="remind-btn">LINEでリマインド</button>
                                </form>
                            <?php else: ?>
                                <span class="no-line">LINE未連携</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</body>
</html>
