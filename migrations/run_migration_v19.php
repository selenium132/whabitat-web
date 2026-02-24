<?php
require_once 'config.php';

function getDBOverwrite() {
    // Try list of common connections
    $hosts = [
        "mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4",
        "mysql:host=127.0.0.1;dbname=" . DB_NAME . ";charset=utf8mb4",
    ];

    foreach ($hosts as $dsn) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            // continue
        }
    }
    // Final attempt or fail
    return getDB();
}

try {
    $pdo = getDBOverwrite();
    $sql = file_get_contents(__DIR__ . '/setup_v19.sql');
    if (!$sql) die("Could not read setup_v19.sql");
    
    $pdo->exec($sql);
    echo "Migration setup_v19.sql successful!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
