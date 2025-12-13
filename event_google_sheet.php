<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // Ensure this path is correct after deployment

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

// 1. Initialize Google Client
$client = new Google\Client();
$client->setAuthConfig('service-account.json');
$client->addScope(Google\Service\Sheets::SPREADSHEETS);
$client->addScope(Google\Service\Drive::DRIVE); // Needed for sharing

$service = new Google\Service\Sheets($client);
$driveService = new Google\Service\Drive($client);

// 2. Check if Sheet already exists
$spreadsheetId = $event['spreadsheet_id'];
$isNew = false;

if (empty($spreadsheetId)) {
    // Create New Sheet
    $spreadsheet = new Google\Service\Sheets\Spreadsheet([
        'properties' => [
            'title' => '[WHABITAT] ' . $event['title']
        ]
    ]);
    $spreadsheet = $service->spreadsheets->create($spreadsheet);
    $spreadsheetId = $spreadsheet->spreadsheetId;
    $isNew = true;

    // Save ID to DB
    $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = ? WHERE id = ?");
    $updateStmt->execute([$spreadsheetId, $event_id]);

    // Share with Admin (TODO: Get email from user input or config? For now hardcoded or ask user)
    // We will share with the Service Account (already owner) and the Group Email if known.
    // For now, allow anyone with link? Or specific email?
    // User asked to share with "Whabitat Gmail". We should define this in config or ask.
    // Let's use a constant for now or just output the URL.
    // Ideally we add permission for the group email.
    
    // User email to share with (Hardcoded for now based on request, or define in config)
    $targetEmail = 'whabitat.circle@gmail.com'; // REPLACE THIS WITH ACTUAL EMAIL IF KNOWN, OR ASK USER
    // Actually, user said "whabitat...". I'll default to the one in their JSON if present? No, that's the bot.
    // I will add a permission for 'whabitat.circle@gmail.com' as a placeholder, user can change logic.
    /*
    $newPermission = new Google\Service\Drive\Permission();
    $newPermission->setType('user');
    $newPermission->setRole('writer');
    $newPermission->setEmailAddress($targetEmail);
    $driveService->permissions->create($spreadsheetId, $newPermission);
    */
    // Since I don't know the exact email, I will make it "anyone with link" for now? NO, insecure.
    // I'll skip sharing for a moment and just let the bot own it, but output the URL.
    // WAIT, if bot owns it, normal users can't see it unless shared.
    // I will try to make it readable by anyone with link (easier for now) OR just specific email if user provided.
    // User didn't provide specific email yet in chat log (just "whabitat...").
    // I'll add logic to share with specific email if defined, else public link reader?
    // Safety first: Let's share with the email address of the CURRENT USER if they requested it? 
    // No, let's share with 'whabitat.waseda@gmail.com' (common guess) or just print the ID.
    // BETTER: Share with the email address defined in CONFIG if exists.
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
$body = new Google\Service\Sheets\ValueRange([
    'values' => $dataRows
]);
$params = [
    'valueInputOption' => 'RAW'
];

// Write to 'Sheet1' (clearing old data implicitly by overwriting? No, update overwrites range)
// Better to Clear first then Write to avoid leftover rows
$clear = new Google\Service\Sheets\ClearValuesRequest();
$service->spreadsheets_values->clear($spreadsheetId, 'Sheet1', $clear);

$result = $service->spreadsheets_values->update($spreadsheetId, 'Sheet1!A1', $body, $params);

// 5. Redirect back with success message
$sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId;
    // Make it "anyone with link" = "writer" as requested by user
    // This allows the "admin group" to access it simply by clicking the link, without individual invites.
    $newPermission = new Google\Service\Drive\Permission();
    $newPermission->setType('anyone');
    $newPermission->setRole('writer');
    try {
        $driveService->permissions->create($spreadsheetId, $newPermission);
        
        // Also share with the whabitat circle email if valid, just in case they want it in their "Shared with me"
        // But 'anyone' covers access.
    } catch (Exception $e) {
        // Ignore if fails (e.g. already set)
    }
}

// Notify user via session flash or redirect param
header("Location: event_responses.php?id=$event_id&sheet_url=" . urlencode($sheetUrl));
exit;
?>
