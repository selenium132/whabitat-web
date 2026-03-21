<?php
require_once '../config.php';
require_once '../SimpleGoogleSheets.php';

requireLogin();

// Only Admin can export members
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pdo = getDB();

try {
    $gs = new SimpleGoogleSheets('../service-account.json');
    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxITwm-W_e9-1axQqFgzlqo48tBPSJJHEr90r6aoAa74Md1ETZLAwLMOMPdiJYthBWS/exec';
    
    $sheetIdFile = 'members_sheet_id.txt';
    $spreadsheetId = '';
    $isNew = false;
    
    if (file_exists($sheetIdFile)) {
        $spreadsheetId = trim(file_get_contents($sheetIdFile));
    }
    
    if (isset($_GET['reset']) || empty($spreadsheetId)) {
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
        'ID', '名前', 'ふりがな', '学籍番号', '代', '入学年', '今の学年', '学部', '学科', '性別', 
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
        
        // Calculate uni year
        $uni_year_str = "-";
        if (!empty($m['admission_year'])) {
            $adm_year_num = (int)str_replace('年', '', $m['admission_year']);
            $current_year = (int)date('Y');
            $current_month = (int)date('n');
            $current_academic_year = ($current_month >= 4) ? $current_year : $current_year - 1;
            if ($adm_year_num > 2000) {
                $uni_year = $current_academic_year - $adm_year_num + 1;
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
    
    $sheetUrl = "https://docs.google.com/spreadsheets/d/" . $spreadsheetId . "/edit";
    header("Location: " . $sheetUrl);
    exit;

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='members.php'>戻る</a></p>";
    exit;
}
