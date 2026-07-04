<?php
// 部室の入退室・在室状況・予約に関する共有ロジック。
// Web(room_api.php)とLINE(line_webhook.php)の両方から呼ばれるため、ここに集約する。

define('ROOM_ID', 1); // 現状は部室1つのみ。将来複数部屋化してもテーブル構造は変えずに済むよう固定値で扱う。

// Helper: 入退室・予約用テーブルを自動作成（無ければ作る。config.php の ensureAuditLogTable() と同じ流儀）
function ensureRoomTables(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS room_presence (
            id INT NOT NULL AUTO_INCREMENT,
            room_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source ENUM('web','line') NOT NULL DEFAULT 'web',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_room_user (room_id, user_id),
            KEY idx_checked_in_at (checked_in_at),
            CONSTRAINT room_presence_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS room_checkinout_log (
            id INT NOT NULL AUTO_INCREMENT,
            room_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            action ENUM('check_in','check_out') NOT NULL,
            source ENUM('web','line') NOT NULL DEFAULT 'web',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            CONSTRAINT room_checkinout_log_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS room_reservations (
            id INT NOT NULL AUTO_INCREMENT,
            room_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            reserved_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at DATETIME DEFAULT NULL,
            cancelled_by INT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_room_date_time (room_id, reserved_date, start_time, end_time),
            CONSTRAINT room_reservations_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT room_reservations_ibfk_2 FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        // source ENUMに'auto'（日付またぎの自動退室用）を追加。既存環境向けの後方互換マイグレーション。
        $pdo->exec("ALTER TABLE room_checkinout_log MODIFY source ENUM('web','line','auto') NOT NULL DEFAULT 'web'");
    } catch (Exception $e) {
        error_log('ensureRoomTables failed: ' . $e->getMessage());
    }
}

// 日付をまたいで残っている在室記録を自動的に強制退室させる（退室押し忘れ対策）。
// 「部室利用状況」は1日単位でリセットする運用のため、日付が変わった時点で
// 前日以前から続く在室レコードは自動的に閉じる。
function expireStalePresence(PDO $pdo) {
    $stmt = $pdo->prepare("SELECT user_id FROM room_presence WHERE room_id = ? AND checked_in_at < CURDATE()");
    $stmt->execute([ROOM_ID]);
    $stale = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($stale)) return;

    $pdo->prepare("DELETE FROM room_presence WHERE room_id = ? AND checked_in_at < CURDATE()")->execute([ROOM_ID]);
    foreach ($stale as $userId) {
        logRoomAction($pdo, $userId, 'check_out', 'auto');
    }
}

// 指定ユーザーが現在在室中か
function isUserPresent(PDO $pdo, $userId) {
    expireStalePresence($pdo);
    $stmt = $pdo->prepare("SELECT 1 FROM room_presence WHERE room_id = ? AND user_id = ?");
    $stmt->execute([ROOM_ID, $userId]);
    return (bool)$stmt->fetch();
}

// 現在の在室者一覧（入室が古い順）
function getCurrentOccupants(PDO $pdo) {
    expireStalePresence($pdo);
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.avatar_url, rp.checked_in_at
        FROM room_presence rp JOIN users u ON u.id = rp.user_id
        WHERE rp.room_id = ? ORDER BY rp.checked_in_at ASC");
    $stmt->execute([ROOM_ID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 指定日時（省略時は現在時刻）を含む、キャンセルされていない予約を1件返す
function getActiveReservation(PDO $pdo, $date = null, $time = null) {
    $date = $date ?? date('Y-m-d');
    $time = $time ?? date('H:i:s');
    $stmt = $pdo->prepare("SELECT r.id, r.user_id, u.name, r.reserved_date, r.start_time, r.end_time, r.purpose
        FROM room_reservations r JOIN users u ON u.id = r.user_id
        WHERE r.room_id = ? AND r.reserved_date = ? AND r.cancelled_at IS NULL
          AND r.start_time <= ? AND r.end_time > ?
        ORDER BY r.start_time ASC LIMIT 1");
    $stmt->execute([ROOM_ID, $date, $time, $time]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// 入室処理。予約時間帯に本人以外が入ろうとした場合はブロックする。
function roomCheckIn(PDO $pdo, $userId, $source) {
    expireStalePresence($pdo);
    $active = getActiveReservation($pdo);
    if ($active && (int)$active['user_id'] !== (int)$userId) {
        $timeRange = substr($active['start_time'], 0, 5) . '〜' . substr($active['end_time'], 0, 5);
        return ['success' => false, 'error' => "この時間帯（{$timeRange}）は {$active['name']} さんが予約しています。時間をずらすか、直接調整してください。"];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO room_presence (room_id, user_id, source) VALUES (?, ?, ?)");
        $stmt->execute([ROOM_ID, $userId, $source]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') { // Duplicate entry以外は再送出
            throw $e;
        }
        // 既に入室中 -> 冪等に成功扱い
        return ['success' => true, 'already' => true];
    }

    logRoomAction($pdo, $userId, 'check_in', $source);
    return ['success' => true];
}

// 退室処理
function roomCheckOut(PDO $pdo, $userId, $source) {
    $stmt = $pdo->prepare("DELETE FROM room_presence WHERE room_id = ? AND user_id = ?");
    $stmt->execute([ROOM_ID, $userId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => '入室していません。'];
    }

    logRoomAction($pdo, $userId, 'check_out', $source);
    return ['success' => true];
}

function logRoomAction(PDO $pdo, $userId, $action, $source) {
    try {
        $stmt = $pdo->prepare("INSERT INTO room_checkinout_log (room_id, user_id, action, source) VALUES (?, ?, ?, ?)");
        $stmt->execute([ROOM_ID, $userId, $action, $source]);
    } catch (Exception $e) {
        error_log('logRoomAction failed: ' . $e->getMessage());
    }
}

// 予約の作成/更新（共通処理）。$reservationIdを渡すと更新（自分自身の枠は重複チェックから除外）、
// nullなら新規作成。呼び出し側で対象予約の所有者確認(IDOR対策)を済ませてから呼ぶこと。
function saveReservation(PDO $pdo, $userId, $date, $startTime, $endTime, $purpose, $reservationId = null) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        return ['error' => '入力内容が不正です'];
    }
    if ($date < date('Y-m-d')) {
        return ['error' => '過去の日付は予約できません'];
    }
    if ($startTime >= $endTime) {
        return ['error' => '終了時刻は開始時刻より後にしてください'];
    }

    // 同一日付内での時間帯重複を防ぐため、日付単位のアプリケーションロックを取る。
    // SELECT...FOR UPDATEでは「まだ存在しない行」はロックできず競合を防げないため。
    $lockName = 'room_reservation_' . $date;
    $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 5)");
    $lockStmt->execute([$lockName]);
    if (!$lockStmt->fetchColumn()) {
        return ['error' => '混雑しています。もう一度お試しください'];
    }

    try {
        $sql = "SELECT id FROM room_reservations WHERE room_id = ? AND reserved_date = ? AND cancelled_at IS NULL AND start_time < ? AND end_time > ?";
        $params = [ROOM_ID, $date, $endTime, $startTime];
        if ($reservationId) {
            $sql .= " AND id != ?";
            $params[] = $reservationId;
        }
        $checkStmt = $pdo->prepare($sql . " LIMIT 1");
        $checkStmt->execute($params);
        if ($checkStmt->fetch()) {
            return ['error' => 'その時間帯は既に予約されています'];
        }

        if ($reservationId) {
            $updateStmt = $pdo->prepare("UPDATE room_reservations SET reserved_date=?, start_time=?, end_time=?, purpose=? WHERE id=? AND user_id=? AND cancelled_at IS NULL");
            $updateStmt->execute([$date, $startTime, $endTime, $purpose !== '' ? $purpose : null, $reservationId, $userId]);
            return ['success' => true, 'id' => $reservationId];
        }

        $insertStmt = $pdo->prepare("INSERT INTO room_reservations (room_id, user_id, reserved_date, start_time, end_time, purpose) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([ROOM_ID, $userId, $date, $startTime, $endTime, $purpose !== '' ? $purpose : null]);
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } finally {
        $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
    }
}

// LINE返信用にテキスト整形
function formatOccupantsForLineText(array $occupants, $activeReservation = null) {
    if (empty($occupants)) {
        $text = "現在、部室には誰もいません。";
    } else {
        $text = "🏠 現在の部室在室状況（" . count($occupants) . "人）\n\n";
        foreach ($occupants as $o) {
            $time = date('H:i', strtotime($o['checked_in_at']));
            $text .= "・{$o['name']}（{$time}〜）\n";
        }
    }

    if ($activeReservation) {
        $range = substr($activeReservation['start_time'], 0, 5) . '〜' . substr($activeReservation['end_time'], 0, 5);
        $text .= "\n📅 現在の予約: {$range} {$activeReservation['name']}";
    }

    return $text;
}
