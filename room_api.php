<?php
require_once 'config.php';
require_once 'room_common.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
ensureRoomTables($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'status':
            $occupants = getCurrentOccupants($pdo);
            $myPresence = false;
            foreach ($occupants as $o) {
                if ((int)$o['id'] === (int)$_SESSION['user_id']) {
                    $myPresence = true;
                    break;
                }
            }
            echo json_encode([
                'occupants' => $occupants,
                'my_presence' => $myPresence,
                'active_reservation' => getActiveReservation($pdo),
            ]);
            break;

        case 'checkin':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            // 対象は常に自分自身（$_SESSION['user_id']）。クライアントからuser_idは一切受け取らない。
            echo json_encode(roomCheckIn($pdo, $_SESSION['user_id'], 'web'));
            break;

        case 'checkout':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            echo json_encode(roomCheckOut($pdo, $_SESSION['user_id'], 'web'));
            break;

        case 'reserve':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            // 対象は常に自分自身（$_SESSION['user_id']）。クライアントからuser_idは一切受け取らない。
            echo json_encode(saveReservation(
                $pdo,
                $_SESSION['user_id'],
                $_POST['reserved_date'] ?? '',
                $_POST['start_time'] ?? '',
                $_POST['end_time'] ?? '',
                trim($_POST['purpose'] ?? '')
            ));
            break;

        case 'update_reservation':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $editId = (int)($_POST['id'] ?? 0);

            // IDOR対策: 更新対象(予約のuser_id)で認可を再判定する。本人のみ編集可。
            $ownerStmt = $pdo->prepare("SELECT user_id FROM room_reservations WHERE id = ? AND cancelled_at IS NULL");
            $ownerStmt->execute([$editId]);
            $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

            if (!$owner) {
                echo json_encode(['error' => '予約が見つかりません']);
                break;
            }
            if ((int)$owner['user_id'] !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'この予約を編集する権限がありません']);
                break;
            }

            echo json_encode(saveReservation(
                $pdo,
                $_SESSION['user_id'],
                $_POST['reserved_date'] ?? '',
                $_POST['start_time'] ?? '',
                $_POST['end_time'] ?? '',
                trim($_POST['purpose'] ?? ''),
                $editId
            ));
            break;

        case 'cancel_reservation':
            validateCsrfToken($_POST['csrf_token'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT user_id FROM room_reservations WHERE id = ? AND cancelled_at IS NULL");
            $stmt->execute([$id]);
            $resv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resv) {
                echo json_encode(['error' => '予約が見つかりません']);
                break;
            }
            // IDOR対策: 更新対象(予約のuser_id)で認可を再判定する。本人か管理者のみキャンセル可。
            if ((int)$resv['user_id'] !== (int)$_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'この予約をキャンセルする権限がありません']);
                break;
            }
            $updateStmt = $pdo->prepare("UPDATE room_reservations SET cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND cancelled_at IS NULL");
            $updateStmt->execute([$_SESSION['user_id'], $id]);
            echo json_encode(['success' => $updateStmt->rowCount() > 0]);
            break;

        case 'reservations':
            $stmt = $pdo->prepare("SELECT r.id, r.user_id, u.name, r.reserved_date, r.start_time, r.end_time, r.purpose
                FROM room_reservations r JOIN users u ON u.id = r.user_id
                WHERE r.room_id = ? AND r.cancelled_at IS NULL
                  AND (r.reserved_date > CURDATE() OR (r.reserved_date = CURDATE() AND r.end_time >= CURTIME()))
                  AND r.reserved_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY r.reserved_date ASC, r.start_time ASC");
            $stmt->execute([ROOM_ID]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['is_mine'] = ((int)$row['user_id'] === (int)$_SESSION['user_id']);
            }
            unset($row);
            echo json_encode($rows);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('room_api error: ' . $e->getMessage());
    echo json_encode(['error' => '処理に失敗しました']);
}
