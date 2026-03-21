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
        'ID', '名前', 'ふりがな', '学籍番号', '代', '学部', '学科', '入学年', '性別', 
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
        
        $dataRows[] = [
            $m['id'],
            $m['name'],
            $m['name_kana'] ?? '',
            $m['student_id'],
            $m['grade'],
            $m['faculty'] ?? '',
            $m['department'] ?? '',
            $m['admission_year'] ?? '',
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
