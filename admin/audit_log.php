<?php
require_once '../config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();
ensureAuditLogTable($pdo); // 無ければ作る（初回アクセスでも落ちないように）

$logs = [];
try {
    $logs = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('audit_log view failed: ' . $e->getMessage());
}

// 操作コード → 日本語ラベル
$action_labels = [
    'approve'        => '承認',
    'disapprove'     => '承認取消',
    'delete'         => '削除',
    'update_role'    => '権限変更',
    'update_profile' => 'プロフィール編集',
    'export_sheet'   => 'シート出力',
];
function al_label($action, $map) {
    return $map[$action] ?? $action;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>監査ログ | WHABITAT 管理</title>
    <style>
        body { font-family: -apple-system, "Hiragino Kaku Gothic ProN", sans-serif; color: #1a1a1a; background: #fff; margin: 0; padding: 1.5rem; }
        h1 { font-size: 1.3rem; font-weight: 600; }
        .lead { color: #555; font-size: .9rem; line-height: 1.6; margin-bottom: 1rem; }
        a.back { display: inline-block; margin-bottom: 1rem; color: #333; font-size: .85rem; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { border-collapse: collapse; width: 100%; font-size: .85rem; }
        th, td { border: 1px solid #e2e2e2; padding: .45rem .6rem; text-align: left; vertical-align: top; white-space: nowrap; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:nth-child(even) td { background: #fafafa; }
        td.detail { white-space: normal; }
        .act { display: inline-block; padding: 1px 8px; border-radius: 10px; background: #eee; font-size: .78rem; }
        .act-delete { background: #f6d6d6; }
        .act-export_sheet { background: #d9e4f5; }
        .act-update_role { background: #efe1c6; }
        .none { color: #888; padding: 1rem 0; }
        .muted { color: #999; }
    </style>
</head>
<body>
    <a class="back" href="/admin/members.php">← 名簿管理に戻る</a>
    <h1>監査ログ</h1>
    <p class="lead">
        管理者による会員データの操作履歴（承認・承認取消・削除・権限変更・プロフィール編集・シート出力）です。新しい順、最大500件。
    </p>

    <?php if (empty($logs)): ?>
        <p class="none">まだ記録はありません。</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>操作者</th>
                    <th>操作</th>
                    <th>対象</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['created_at'] ?? ''); ?></td>
                        <td>
                            <?php echo htmlspecialchars($log['admin_name'] ?? '') ?: '<span class="muted">(不明)</span>'; ?>
                            <span class="muted">#<?php echo (int)($log['admin_id'] ?? 0); ?></span>
                        </td>
                        <td><span class="act act-<?php echo htmlspecialchars($log['action'] ?? ''); ?>"><?php echo htmlspecialchars(al_label($log['action'] ?? '', $action_labels)); ?></span></td>
                        <td>
                            <?php if (!empty($log['target_name']) || !empty($log['target_id'])): ?>
                                <?php echo htmlspecialchars($log['target_name'] ?? ''); ?>
                                <?php if (!empty($log['target_id'])): ?><span class="muted">#<?php echo (int)$log['target_id']; ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="detail"><?php echo htmlspecialchars($log['detail'] ?? '') ?: '<span class="muted">-</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</body>
</html>
