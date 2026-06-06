<?php
require_once __DIR__ . '/SimpleGoogleSheets.php';

/**
 * イベントの参加者・アンケート回答を Google スプレッドシートへ同期する。
 *
 * form_google_sheet.php（手動同期ボタン）と form_view.php（回答時の自動同期）の
 * 両方から呼ばれる共通処理。
 *
 * @param PDO  $pdo
 * @param int  $event_id
 * @param bool $autoCreate  spreadsheet_id が未設定のときに新規シートを作るか。
 *                          手動ボタンは true（初回作成も担う）、
 *                          回答時の自動同期は false（既存シートのみ更新）。
 * @param bool $forceReset  spreadsheet_id をリセットして作り直すか。
 * @return array ['status' => 'ok'|'skipped', 'spreadsheetId' => ?string, 'isNew' => bool]
 * @throws Exception 認証・API エラー時
 */
function syncEventToSheet(PDO $pdo, $event_id, $autoCreate = false, $forceReset = false) {
    // Apps Script URL for creating spreadsheets (uses user's Drive storage, not service account)
    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxITwm-W_e9-1axQqFgzlqo48tBPSJJHEr90r6aoAa74Md1ETZLAwLMOMPdiJYthBWS/exec';

    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception("Event not found");
    }

    $gs = new SimpleGoogleSheets(__DIR__ . '/service-account.json');

    // Force reset if requested
    if ($forceReset) {
        $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = NULL WHERE id = ?");
        $updateStmt->execute([$event_id]);
        $event['spreadsheet_id'] = null;
    }

    $spreadsheetId = $event['spreadsheet_id'];
    $isNew = false;

    if (empty($spreadsheetId)) {
        // 自動同期(回答時)では、まだシートが無いイベントは何もしない。
        // 初回のシート作成は手動の同期ボタン($autoCreate=true)に任せる。
        if (!$autoCreate) {
            return ['status' => 'skipped', 'spreadsheetId' => null, 'isNew' => false];
        }

        // Create New Sheet via Apps Script
        try {
            $sheet = $gs->createSpreadsheetViaAppsScript('[WHABITAT] ' . $event['title'], $appsScriptUrl);
            $spreadsheetId = $sheet['spreadsheetId'];
            $isNew = true;

            // Save ID to DB
            $updateStmt = $pdo->prepare("UPDATE events SET spreadsheet_id = ? WHERE id = ?");
            $updateStmt->execute([$spreadsheetId, $event_id]);
        } catch (Exception $e) {
            throw new Exception("スプレッドシートの作成に失敗しました: " . $e->getMessage());
        }
    }

    // Fetch Participants
    $stmt = $pdo->prepare("
        SELECT u.name, u.student_id, u.line_name, u.faculty, u.grade, a.status, a.comment, a.response_data, a.updated_at
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
    $headerRow = ['名前', '学部', '学年', 'ステータス', 'コメント', '学籍番号', 'LINE名', '回答日時'];
    foreach ($form_schema as $q) {
        $headerRow[] = $q['title'];
    }

    // Build Data Rows
    $dataRows = [];
    $dataRows[] = $headerRow;

    foreach ($participants as $p) {
        $row = [
            $p['name'],
            $p['faculty'],
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
            $val = $ans[$idx] ?? '';
            if (is_array($val)) $val = implode(', ', $val);
            $row[] = $val;
        }
        $dataRows[] = $row;
    }

    // Update Sheet
    // Try Japanese sheet name first, then English as fallback
    $sheetName = 'シート1'; // Japanese locale default

    // Clear old data first to avoid leftovers
    try {
        $gs->clearValues($spreadsheetId, $sheetName);
    } catch (Exception $e) {
        // Try English sheet name
        $sheetName = 'Sheet1';
        try {
            $gs->clearValues($spreadsheetId, $sheetName);
        } catch (Exception $e2) {
            // Ignore clear errors (e.g. if sheet is empty or different name)
        }
    }

    // Write new data
    $gs->updateValues($spreadsheetId, $sheetName . '!A1', $dataRows);

    // Set Permissions (if new) - make accessible to anyone with link for easy admin sharing
    if ($isNew) {
        try {
            $gs->addPermission($spreadsheetId, 'writer', 'anyone');
        } catch (Exception $e) {
            // This might fail if service account doesn't have permission - that's OK
        }
    }

    return ['status' => 'ok', 'spreadsheetId' => $spreadsheetId, 'isNew' => $isNew];
}

/**
 * 回答時の自動同期用ラッパー。既にシートがあるイベントだけ更新し、
 * 失敗してもエラーを投げない（回答保存などの本処理を妨げないため、ログのみ）。
 *
 * @param PDO $pdo
 * @param int $event_id
 * @return void
 */
function syncEventToSheetSafe(PDO $pdo, $event_id) {
    try {
        syncEventToSheet($pdo, $event_id, false);
    } catch (Exception $e) {
        error_log("Auto-sync to sheet failed for event $event_id: " . $e->getMessage());
    }
}

/**
 * 全メンバー(users)を「メンバー名簿」スプレッドシートへ同期する。
 *
 * 名簿シートの ID は admin/members_sheet_id.txt に保存される。
 * admin/members_export_sheet.php（手動ボタン）と、メンバー情報の変更箇所からの
 * 自動同期の両方で使う共通処理。
 *
 * @param PDO  $pdo
 * @param bool $autoCreate  シート未作成時に新規作成するか（手動ボタンは true、自動同期は false）。
 * @param bool $forceReset  シートを作り直すか。
 * @return array ['status' => 'ok'|'skipped', 'spreadsheetId' => ?string, 'isNew' => bool]
 * @throws Exception 認証・API エラー時
 */
function syncMembersToSheet(PDO $pdo, $autoCreate = false, $forceReset = false) {
    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxITwm-W_e9-1axQqFgzlqo48tBPSJJHEr90r6aoAa74Md1ETZLAwLMOMPdiJYthBWS/exec';
    $sheetIdFile = __DIR__ . '/admin/members_sheet_id.txt';

    $gs = new SimpleGoogleSheets(__DIR__ . '/service-account.json');

    $spreadsheetId = '';
    $isNew = false;

    if (file_exists($sheetIdFile)) {
        $spreadsheetId = trim(file_get_contents($sheetIdFile));
    }

    if ($forceReset || empty($spreadsheetId)) {
        // 自動同期では、まだ名簿シートが無い場合は何もしない（初回作成は手動ボタンに任せる）
        if (!$autoCreate) {
            return ['status' => 'skipped', 'spreadsheetId' => null, 'isNew' => false];
        }
        $sheet = $gs->createSpreadsheetViaAppsScript('[WHABITAT] メンバー名簿', $appsScriptUrl);
        $spreadsheetId = $sheet['spreadsheetId'];
        $isNew = true;
        file_put_contents($sheetIdFile, $spreadsheetId);
    }

    // Fetch all members
    $stmt = $pdo->query("SELECT * FROM users ORDER BY grade ASC, name COLLATE utf8mb4_unicode_ci ASC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Header Row
    $headerRow = [
        'ID', '名前', 'ふりがな', '学籍番号', '代', '卒業予定年', '今の学年', '学部', '学科', '性別',
        '郵便番号', '住所', '電話番号', '生年月日', 'LINE名', 'メールアドレス',
        '他サークル', 'アレルギー等', '備考(その他)', 'ステータス', '権限'
    ];

    $dataRows = [$headerRow];

    foreach ($members as $m) {
        $status = $m['is_approved'] ? '承認済' : '未承認';
        $role = $m['role'] === 'admin' ? '管理者' : '一般';

        // Translate gender
        $gender_label = $m['gender'];
        if ($m['gender'] === 'male') $gender_label = '男性';
        elseif ($m['gender'] === 'female') $gender_label = '女性';
        elseif ($m['gender'] === 'no_answer') $gender_label = '回答しない';

        // Calculate uni year from graduation year
        $uni_year_str = "-";
        if (!empty($m['admission_year'])) {
            $grad_year_num = (int)str_replace('年', '', $m['admission_year']);
            $current_year = (int)date('Y');
            $current_month = (int)date('n');
            $current_academic_year = ($current_month >= 4) ? $current_year : $current_year - 1;
            if ($grad_year_num > 2000) {
                $uni_year = 4 - ($grad_year_num - $current_academic_year - 1);
                if ($uni_year < 1) $uni_year_str = "入学前";
                elseif ($uni_year > 4) $uni_year_str = "OB/OG";
                else $uni_year_str = $uni_year . "年生";
            }
        }

        $dataRows[] = [
            $m['id'],
            $m['name'],
            $m['name_kana'] ?? '',
            $m['student_id'],
            $m['grade'],
            $m['admission_year'] ?? '',
            $uni_year_str,
            $m['faculty'] ?? '',
            $m['department'] ?? '',
            $gender_label,
            $m['zipcode'] ?? '',
            $m['address'] ?? '',
            $m['phone'] ?? '',
            $m['birthdate'] ?? '',
            $m['line_name'],
            $m['email'],
            $m['other_circles'] ?? '',
            $m['allergies'] ?? '',
            $m['notes'] ?? '',
            $status,
            $role
        ];
    }

    $sheetName = 'シート1';

    try {
        $gs->clearValues($spreadsheetId, $sheetName);
    } catch (Exception $e) {
        $sheetName = 'Sheet1';
        try {
            $gs->clearValues($spreadsheetId, $sheetName);
        } catch (Exception $e2) {}
    }

    $gs->updateValues($spreadsheetId, $sheetName . '!A1', $dataRows);

    if ($isNew) {
        try {
            $gs->addPermission($spreadsheetId, 'writer', 'anyone');
        } catch (Exception $e) {}
    }

    return ['status' => 'ok', 'spreadsheetId' => $spreadsheetId, 'isNew' => $isNew];
}

/**
 * メンバー名簿の自動同期用ラッパー。既に名簿シートがある場合だけ更新し、
 * 失敗してもエラーを投げない（本処理を妨げないため、ログのみ）。
 *
 * @param PDO $pdo
 * @return void
 */
function syncMembersToSheetSafe(PDO $pdo) {
    try {
        syncMembersToSheet($pdo, false);
    } catch (Exception $e) {
        error_log("Auto-sync members to sheet failed: " . $e->getMessage());
    }
}
