<?php
require_once '../config.php';
require_once '../sheet_sync.php'; // 共通の同期処理

requireLogin();

// Only Admin can export members
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

// 出力する管理者“本人”のGoogleアカウントを確認する。
// セッションに検証済みメールが無ければ Google 認証へ送り、認証後ここへ戻る。
$googleEmail = requireGoogleAccount('admin/members_export_sheet.php');

$pdo = getDB();

try {
    // 手動ボタン: 名簿シートが無ければ新規作成し、最新データへ更新する
    $result = syncMembersToSheet($pdo, true, isset($_GET['reset']));
    $spreadsheetId = $result['spreadsheetId'];

    // 押した本人のGoogleアカウントにだけ共有（編集可）。anyone 公開共有は廃止済み。
    try {
        $gs = new SimpleGoogleSheets(__DIR__ . '/../service-account.json');
        $gs->addPermission($spreadsheetId, 'writer', 'user', $googleEmail);
    } catch (Exception $e) {
        // 共有失敗（権限設定等）はログのみ。シートURLへは進む。
        error_log('Member sheet share failed for ' . $googleEmail . ': ' . $e->getMessage());
    }

    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId . "/edit";
    header("Location: " . $sheetUrl);
    exit;

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='members.php'>戻る</a></p>";
    exit;
}
