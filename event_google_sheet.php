<?php
require_once 'config.php';
require_once 'SimpleGoogleSheets.php'; // Use our custom class

requireLogin();

$event_id = $_GET['id'] ?? 0;
// Only Admin or Event Admin can sync
if (!isEventAdmin($event_id)) {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found");
}

try {
    // 1. Initialize Custom Google Client
    $gs = new SimpleGoogleSheets('service-account.json');

    // 2. Check if Sheet already exists
    $spreadsheetId = $event['spreadsheet_id'];
    $isNew = false;

    if (empty($spreadsheetId)) {
        // Create New Sheet
        $sheet = $gs->createSpreadsheet('[WHABITAT] ' . $event['title']);
        $spreadsheetId = $sheet['spreadsheetId'];
        $isNew = true;

        // Save ID to DB
        $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = ? WHERE id = ?");
        $updateStmt->execute([$spreadsheetId, $event_id]);
    }

    // 3. Prepare Data
    // Fetch Participants
    $stmt = $pdo->prepare("
        SELECT u.name, u.student_id, u.line_name, u.grade, a.status, a.comment, a.response_data, a.updated_at
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.event_id = ? AND a.status = 'join'
        ORDER BY a.updated_at DESC
    ");
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse Schema for Headers
    $form_schema = [];
    if (!empty($event['form_schema'])) {
        $decoded = json_decode($event['form_schema'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $form_schema = $decoded;
        }
    }

    // Build Header Row
    $headerRow = ['名前', '学年', 'ステータス', 'コメント', '学籍番号', 'LINE名', '回答日時'];
    foreach ($form_schema as $q) {
        $headerRow[] = $q['title'];
    }

    // Build Data Rows
    $dataRows = [];
    $dataRows[] = $headerRow;

    foreach ($participants as $p) {
        $row = [
            $p['name'],
            $p['grade'],
            $p['status'],
            $p['comment'],
            $p['student_id'],
            $p['line_name'],
            $p['updated_at']
        ];
        
        // Custom Answers
        $ans = json_decode($p['response_data'], true) ?? [];
        foreach ($form_schema as $idx => $q) {
            // Look up by index
            $val = $ans[$idx] ?? '';
            if (is_array($val)) $val = implode(', ', $val);
            $row[] = $val;
        }
        $dataRows[] = $row;
    }

    // 4. Update Sheet
    // Clear old data first to avoid leftovers
    try {
        $gs->clearValues($spreadsheetId, 'Sheet1');
    } catch (Exception $e) {
        // Ignore clear errors (e.g. if sheet is empty)
    }

    // Write new data
    $gs->updateValues($spreadsheetId, 'Sheet1!A1', $dataRows);

    // 5. Set Permissions (if new)
    if ($isNew) {
        // Share with anyone with link as writer
        try {
            $gs->addPermission($spreadsheetId, 'writer', 'anyone');
        } catch (Exception $e) {
            // Permission error
        }
    }

    // Notify user via Logic (Redirect)
    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId;
    header("Location: event_responses.php?id=$event_id&sheet_url=" . urlencode($sheetUrl));
    exit;

} catch (Exception $e) {
    // Basic Error Handling
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='event_responses.php?id=$event_id'>戻る</a></p>";
    exit;
}
?>
