<?php
require 'config.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
