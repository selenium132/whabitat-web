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
    SELECT a.*, u.name as user_name, u.student_id, u.line_name, u.grade 
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
// Requested Order: Name, LINE Name, Student ID, Grade, Questions... (ID and UpdatedAt can go at ends or start)
// Let's keep ID first for technical reference, then the requested order.
$headerRow = ['ID', '氏名', 'LINE名', '学籍番号', '学年'];

foreach ($question_titles as $qt) {
    $headerRow[] = $qt;
}
$headerRow[] = '更新日時';

fputcsv($output, $headerRow);

// 5. Build Data Rows
foreach ($rows as $row) {
    $csvRow = [
        $row['id'],
        $row['user_name'],
        $row['line_name'],
        $row['student_id'],
        $row['grade']
    ];

    // Parse JSON Answers
    $answers = json_decode($row['response_data'] ?? '[]', true);
    
    if (is_array($answers)) {
        foreach ($answers as $idx => $ans) {
            if (is_array($ans)) {
                $ans = implode(', ', $ans);
            }
            $csvRow[] = $ans;
        }
    }
    
    // Add Updated At at the end
    $csvRow[] = $row['updated_at'];
    
    fputcsv($output, $csvRow);
}

fclose($output);
exit;
