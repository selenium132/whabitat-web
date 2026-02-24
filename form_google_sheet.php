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
    
    // DEBUG: Show who we are explicitly
    $creds = json_decode(file_get_contents('service-account.json'), true);
    // echo "Debug: Client Email = " . $creds['client_email'] . "<br>";
    // echo "Debug: Project ID = " . $creds['project_id'] . "<br>";
    // Un-comment the above if needed, but for now let's just log it or pass it to exception
    
    // Allow user to see it in the error message if it fails
    $debugEmail = $creds['client_email'];
    $debugProject = $creds['project_id'];

    // 2. Check if Sheet already exists
    // Force reset if requested
    if (isset($_GET['reset'])) {
        $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = NULL WHERE id = ?");
        $updateStmt->execute([$event_id]);
        $event['spreadsheet_id'] = null; // Update local variable
    }

    $spreadsheetId = $event['spreadsheet_id'];
    $isNew = false;
    
    // Apps Script URL for creating spreadsheets
    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxITwm-W_e9-1axQqFgzlqo48tBPSJJHEr90r6aoAa74Md1ETZLAwLMOMPdiJYthBWS/exec';
    
    if (empty($spreadsheetId)) {
        // Create New Sheet via Apps Script (this uses user's Drive storage, not service account)
        try {
            $sheet = $gs->createSpreadsheetViaAppsScript('[WHABITAT] ' . $event['title'], $appsScriptUrl);
            $spreadsheetId = $sheet['spreadsheetId'];
            $isNew = true;

            // Save ID to DB
            $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = ? WHERE id = ?");
            $updateStmt->execute([$spreadsheetId, $event_id]);
        } catch (Exception $e) {
            throw new Exception("スプレッドシートの作成に失敗しました: " . $e->getMessage());
        }
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
    // Try Japanese sheet name first, then English as fallback
    $sheetName = 'シート1'; // Japanese locale default
    
    // Clear old data first to avoid leftovers
    try {
        $gs->clearValues($spreadsheetId, $sheetName);
    } catch (Exception $e) {
        // Try English sheet name
        $sheetName = 'Sheet1';
        try {
            $gs->clearValues($spreadsheetId, $sheetName);
        } catch (Exception $e2) {
            // Ignore clear errors (e.g. if sheet is empty or different name)
        }
    }

    // Write new data
    $gs->updateValues($spreadsheetId, $sheetName . '!A1', $dataRows);

    // 5. Set Permissions (if new) - Apps Script already shares with service account
    // We can optionally make it "anyone with link" for easy sharing among admins
    if ($isNew) {
        try {
            // Make accessible to anyone with link (optional, for admins to share easily)
            $gs->addPermission($spreadsheetId, 'writer', 'anyone');
        } catch (Exception $e) {
            // This might fail if service account doesn't have permission - that's OK
            // The sheet is still accessible to the user who created it via Apps Script
        }
    }

    // Redirect directly to the spreadsheet (simpler UX)
    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId . "/edit";
    header("Location: " . $sheetUrl);
    exit;

} catch (Exception $e) {
    // Basic Error Handling
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    if (isset($debugEmail)) {
        echo "<hr><p>Debug Info:<br>";
        echo "Service Account: <strong>" . htmlspecialchars($debugEmail) . "</strong><br>";
        echo "Project ID: <strong>" . htmlspecialchars($debugProject) . "</strong></p>";
    }
    echo "<p><a href='form_responses.php?id=$event_id'>戻る</a></p>";
    exit;
}
?>
