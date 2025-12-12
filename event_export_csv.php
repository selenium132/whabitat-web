<?php
require_once 'config.php';
requireLogin();

// Check Admin Role
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$event_id = $_GET['id'] ?? null;
if (!$event_id) {
    die("Invalid Event ID");
}

$pdo = getDB();

// 1. Fetch Event Info & Schema
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found");
}

// Parse Schema to get Question Titles (Headers)
$schema = json_decode($event['form_schema'] ?? '[]', true);
$question_titles = [];
if (is_array($schema)) {
    foreach ($schema as $q) {
        $question_titles[] = $q['title'] ?? '質問';
    }
}

// 2. Fetch Attendance Data
$stmt = $pdo->prepare("
    SELECT a.*, u.name as user_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.event_id = ? 
    ORDER BY a.updated_at DESC
");
$stmt->execute([$event_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Prepare CSV Output
$filename = "event_{$event_id}_responses_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

// 4. Build Header Row
$headerRow = ['ID', '名前', '更新日時']; // Header changed to Updated At
foreach ($question_titles as $qt) {
    $headerRow[] = $qt;
}
fputcsv($output, $headerRow);

// 5. Build Data Rows
foreach ($rows as $row) {
    $csvRow = [
        $row['id'],
        $row['user_name'],
        $row['updated_at'] // Use updated_at
    ];

    // Parse JSON Answers
    $answers = json_decode($row['response_data'] ?? '[]', true);
    
    // Map answers to schema order
    // Note: This relies on the schema order not changing significantly, 
    // or we assume answers array is in same order if it's just a list of values.
    // However, the `answers` column structure depends on how we saved it.
    // In event_view.php, we saved it as an array of values corresponding to the schema loop.
    // Let's assume it's a simple indexed array matching the schema.
    
    if (is_array($answers)) {
        foreach ($answers as $idx => $ans) {
            // If schema has more questions than answers (added later), handle it
            // If answer is array (checkbox), implode it
            if (is_array($ans)) {
                $ans = implode(', ', $ans);
            }
            $csvRow[] = $ans;
        }
    }
    
    fputcsv($output, $csvRow);
}

fclose($output);
exit;
