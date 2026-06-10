<?php
require_once 'config.php';
require_once 'sheet_sync.php'; // 共通の同期処理

requireLogin();

$event_id = $_GET['id'] ?? 0;
// Only Admin or Event Admin can sync
if (!isEventAdmin($event_id)) {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDB();

try {
    // 手動の同期ボタン: シートが無ければ新規作成し、最新データへ更新する
    $result = syncEventToSheet($pdo, $event_id, true, isset($_GET['reset']));
    $spreadsheetId = $result['spreadsheetId'];

    // Redirect directly to the spreadsheet (simpler UX)
    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId . "/edit";
    header("Location: " . $sheetUrl);
    exit;

} catch (Exception $e) {
    // 詳細はサーバーログにのみ記録し、画面には汎用メッセージのみ表示する
    error_log('form_google_sheet sync failed (event_id=' . $event_id . '): ' . $e->getMessage());
    echo "<h1>Error</h1>";
    echo "<p>同期処理に失敗しました。時間をおいて再度お試しください。</p>";
    echo "<p><a href='form_responses.php?id=" . htmlspecialchars(urlencode((string)$event_id)) . "'>戻る</a></p>";
    exit;
}
?>
