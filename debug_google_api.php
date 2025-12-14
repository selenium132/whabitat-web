<?php
// Debug script to test Google API connectivity
require_once 'config.php';
require_once 'SimpleGoogleSheets.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Google API Debug</h1>";

try {
    $gs = new SimpleGoogleSheets('service-account.json');
    
    // Step 1: Check token info
    echo "<h2>1. Token Info</h2>";
    $tokenInfo = $gs->debugTokenInfo();
    echo "<pre>" . htmlspecialchars(json_encode($tokenInfo, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Step 2: Try reading a public spreadsheet
    echo "<h2>2. Read Test (Public Sheet)</h2>";
    $readTest = $gs->debugTestApi();
    echo "<p>HTTP Code: " . $readTest['httpCode'] . "</p>";
    if ($readTest['httpCode'] == 200) {
        echo "<p style='color:green;'>✓ READ works!</p>";
    } else {
        echo "<p style='color:red;'>✗ READ failed</p>";
        echo "<pre>" . htmlspecialchars(json_encode($readTest['response'], JSON_PRETTY_PRINT)) . "</pre>";
    }
    
    // Step 3: Try creating a spreadsheet via Sheets API
    echo "<h2>3. Create Test (Sheets API)</h2>";
    try {
        $sheet = $gs->createSpreadsheet('TEST_DELETE_ME_' . time());
        echo "<p style='color:green;'>✓ CREATE via Sheets API works! Sheet ID: " . $sheet['spreadsheetId'] . "</p>";
        echo "<p><a href='https://docs.google.com/spreadsheets/d/" . $sheet['spreadsheetId'] . "' target='_blank'>Open Sheet</a></p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ CREATE via Sheets API failed</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    
    // Step 4: Try creating a spreadsheet via Drive API (alternative)
    echo "<h2>4. Create Test (Drive API - Alternative)</h2>";
    try {
        $sheet = $gs->createSpreadsheetViaDrive('TEST_DRIVE_DELETE_ME_' . time());
        echo "<p style='color:green;'>✓ CREATE via Drive API works! Sheet ID: " . $sheet['spreadsheetId'] . "</p>";
        echo "<p><a href='https://docs.google.com/spreadsheets/d/" . $sheet['spreadsheetId'] . "' target='_blank'>Open Sheet</a></p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ CREATE via Drive API failed</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr><a href='dashboard.php'>Back to Dashboard</a>";
?>
