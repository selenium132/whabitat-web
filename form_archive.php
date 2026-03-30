<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$event_id = intval($_POST['event_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$event_id || !in_array($action, ['archive', 'unarchive'])) {
    http_response_code(400);
    exit;
}

// Only event admins (including creators) can archive/unarchive
if (!isEventAdmin($event_id)) {
    http_response_code(403);
    exit;
}

$pdo = getDB();

// Ensure column exists
try {
    $pdo->exec("ALTER TABLE events ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // Column already exists
}

$is_archived = ($action === 'archive') ? 1 : 0;
$stmt = $pdo->prepare("UPDATE events SET is_archived = ? WHERE id = ?");
$stmt->execute([$is_archived, $event_id]);

// Redirect back
$return = $_POST['return'] ?? 'dashboard.php';
header("Location: " . $return);
exit;
