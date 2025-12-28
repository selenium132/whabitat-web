<?php
require_once 'config.php';

function getDBOverwrite() {
    try {
        // 127.0.0.1に変更してTCP接続を強制
        $dsn = "mysql:host=127.0.0.1;dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

try {
    $pdo = getDBOverwrite();
    $sql = file_get_contents('update_calendar_schema.sql');
    $pdo->exec($sql);
    echo "Migration successful!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
