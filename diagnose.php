<?php
require 'config.php';
$pdo = getDB();
$id = 47;
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM event_admins WHERE event_id = ?");
$stmt->execute([$id]);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Event 47:\n";
print_r($event);
echo "\nEvent Admins:\n";
print_r($admins);
echo "</pre>";
