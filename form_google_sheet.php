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
    // Basic Error Handling
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    $creds = @json_decode(@file_get_contents(__DIR__ . '/service-account.json'), true);
    if (is_array($creds) && isset($creds['client_email'])) {
        echo "<hr><p>Debug Info:<br>";
        echo "Service Account: <strong>" . htmlspecialchars($creds['client_email']) . "</strong><br>";
        echo "Project ID: <strong>" . htmlspecialchars($creds['project_id'] ?? '') . "</strong></p>";
    }
    echo "<p><a href='form_responses.php?id=$event_id'>戻る</a></p>";
    exit;
}
?>
