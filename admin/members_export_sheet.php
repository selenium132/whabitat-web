<?php
require_once '../config.php';
require_once '../sheet_sync.php'; // 共通の同期処理

requireLogin();

// Only Admin can export members
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();

try {
    // 手動ボタン: 名簿シートが無ければ新規作成し、最新データへ更新する
    $result = syncMembersToSheet($pdo, true, isset($_GET['reset']));
    $spreadsheetId = $result['spreadsheetId'];

    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId . "/edit";
    header("Location: " . $sheetUrl);
    exit;

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='members.php'>戻る</a></p>";
    exit;
}
