<?php
require 'config.php';
try {
    $pdo = getDB();
    $pdo->exec("ALTER TABLE blogs MODIFY content LONGTEXT");
    echo "Success: blogs content column updated to LONGTEXT.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
