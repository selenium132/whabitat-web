<?php
require_once '../config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

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

$members = $pdo->query("SELECT id, name, name_kana, email, gender, zipcode, address, phone, birthdate, grade, student_id, is_approved, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

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
    </style>
</head>
<body>
    <a class="back" href="/admin/members.php">← 名簿管理に戻る</a>
    <h1>必須項目が未入力の会員</h1>
    <p class="lead">
        ここに載っている人は、会員ページを開くと自動的にプロフィール編集へ誘導され、埋めるまで先に進めません（<?php echo htmlspecialchars(implode(' / ', $required)); ?> のいずれかが空）。<br>
        対象: <span class="count"><?php echo count($incomplete); ?></span> 名 ／ 全 <?php echo count($members); ?> 名中
    </p>

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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</body>
</html>
