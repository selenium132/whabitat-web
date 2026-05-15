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

if (!$event_id || !in_array($action, ['archive', 'unarchive', 'restore'])) {
    http_response_code(400);
    exit;
}

// Only event admins (including creators) can archive/unarchive/restore
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

if ($action === 'archive') {
    $stmt = $pdo->prepare("UPDATE events SET is_archived = 1 WHERE id = ?");
    $stmt->execute([$event_id]);
} elseif ($action === 'unarchive') {
    $stmt = $pdo->prepare("UPDATE events SET is_archived = 0 WHERE id = ?");
    $stmt->execute([$event_id]);
} elseif ($action === 'restore') {
    // Restore: unarchive AND optionally update date to future
    $new_date = $_POST['new_event_date'] ?? '';
    
    if (!empty($new_date)) {
        // Update both is_archived and event_date
        $stmt = $pdo->prepare("UPDATE events SET is_archived = 0, event_date = ? WHERE id = ?");
        $stmt->execute([$new_date, $event_id]);
    } else {
        // Just unarchive (for archived events with future dates)
        $stmt = $pdo->prepare("UPDATE events SET is_archived = 0 WHERE id = ?");
        $stmt->execute([$event_id]);
    }
}

// Redirect back (whitelist to prevent open redirect)
$allowed_returns = ['dashboard.php', 'past_events.php'];
$return = $_POST['return'] ?? 'dashboard.php';
if (!in_array(basename($return), $allowed_returns)) {
    $return = 'dashboard.php';
}
header("Location: " . $return);
exit;
