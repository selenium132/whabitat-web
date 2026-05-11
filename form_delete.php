<?php
require_once 'config.php';
requireLogin();

// Only accept POST requests (prevent CSRF via GET links)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: dashboard.php");
    exit;
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$event_id = $_POST['id'] ?? null;

// Only event admins (including creators) can delete
if (!$event_id || !isEventAdmin($event_id)) {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDB();

// Delete Attendance first (Foreign Key might cascade, but let's be safe)
$stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
$stmt->execute([$event_id]);

// Delete Event
$stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
$stmt->execute([$event_id]);

header("Location: dashboard.php");
exit;

