<?php
require_once 'config.php';
requireLogin();

$event_id = $_GET['id'] ?? null;

// Only event admins (including creators) can delete
if (!$event_id || !isEventAdmin($event_id)) {
    header("Location: dashboard.php");
    exit;
}

if ($event_id) {
    $pdo = getDB();
    
    // Delete Attendance first (Foreign Key might cascade, but let's be safe)
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
    $stmt->execute([$event_id]);
    
    // Delete Event
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
}

header("Location: dashboard.php");
exit;
