<?php
require 'config.php';
$stmt = getDB()->query("DESCRIBE blogs");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
