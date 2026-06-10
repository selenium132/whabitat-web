<?php
require_once 'config.php';
requireLogin();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$pdo = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($event ?: ['error' => 'Not found']);
            break;
            
        case 'add':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $title = $_POST['title'] ?? '';
            $is_all_day = isset($_POST['is_all_day']) ? 1 : 0;
            $color = $_POST['color'] ?? '#667eea';
            $description = $_POST['description'] ?? '';
            
            if ($is_all_day) {
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? $start_date; // Default to start_date if empty
                $event_date = $start_date;
                $start_time = null;
                $end_time = null;
            } else {
                $start_datetime = $_POST['start_datetime'] ?? '';
                $end_datetime = $_POST['end_datetime'] ?? '';
                $event_date = $start_datetime ? date('Y-m-d', strtotime($start_datetime)) : '';
                // For non-all-day, end_date matches end_datetime date
                $end_date = $end_datetime ? date('Y-m-d', strtotime($end_datetime)) : $event_date;
                $start_time = $start_datetime ? date('H:i:s', strtotime($start_datetime)) : null;
                $end_time = $end_datetime ? date('H:i:s', strtotime($end_datetime)) : null;
            }
            
            if ($title && $event_date) {
                $stmt = $pdo->prepare("INSERT INTO calendar_events (title, event_date, end_date, start_time, end_time, is_all_day, description, color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $event_date, $end_date, $start_time, $end_time, $is_all_day, $description ?: null, $color, $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['error' => 'タイトルと日時を入力してください']);
            }
            break;
            
        case 'update':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $is_all_day = isset($_POST['is_all_day']) ? 1 : 0;
            $color = $_POST['color'] ?? '#667eea';
            $description = $_POST['description'] ?? '';
            
            if ($is_all_day) {
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? $start_date;
                $event_date = $start_date;
                $start_time = null;
                $end_time = null;
            } else {
                $start_datetime = $_POST['start_datetime'] ?? '';
                $end_datetime = $_POST['end_datetime'] ?? '';
                $event_date = $start_datetime ? date('Y-m-d', strtotime($start_datetime)) : '';
                $end_date = $end_datetime ? date('Y-m-d', strtotime($end_datetime)) : $event_date;
                $start_time = $start_datetime ? date('H:i:s', strtotime($start_datetime)) : null;
                $end_time = $end_datetime ? date('H:i:s', strtotime($end_datetime)) : null;
            }
            
            if ($id && $title && $event_date) {
                $stmt = $pdo->prepare("UPDATE calendar_events SET title=?, event_date=?, end_date=?, start_time=?, end_time=?, is_all_day=?, description=?, color=? WHERE id=?");
                $stmt->execute([$title, $event_date, $end_date, $start_time, $end_time, $is_all_day, $description ?: null, $color, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid data']);
            }
            break;
            
        case 'delete':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid ID']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('calendar_api error: ' . $e->getMessage());
    echo json_encode(['error' => '処理に失敗しました']);
}
