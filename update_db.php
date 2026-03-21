<?php
require_once 'config.php';

// Only allow admin (if logged in, check role, or just a simple secret check if needed)
// Assuming user will run this directly and delete afterwards, or we check admin role
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("このスクリプトは管理者アカウントでログインしている場合のみ実行できます。");
}

$pdo = getDB();

$queries = [
    "ALTER TABLE users ADD COLUMN name_kana VARCHAR(255) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN admission_year VARCHAR(10) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN zipcode VARCHAR(20) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN address TEXT",
    "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN birthdate DATE DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN other_circles TEXT",
    "ALTER TABLE users ADD COLUMN allergies TEXT",
    "ALTER TABLE users ADD COLUMN notes TEXT",
    "ALTER TABLE users MODIFY COLUMN gender VARCHAR(50) DEFAULT ''" // ensure gender can hold 'no_answer'
];

echo "<h1>データベース更新</h1>";

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "<p style='color: green;'>成功: " . htmlspecialchars($query) . "</p>";
    } catch (PDOException $e) {
        // SQLSTATE 42S21 means column already exists, safe to ignore
        if ($e->getCode() == '42S21') {
            echo "<p style='color: orange;'>スキップ (既に存在します): " . htmlspecialchars($query) . "</p>";
        } else {
            echo "<p style='color: red;'>エラー ({$e->getCode()}): " . htmlspecialchars($e->getMessage()) . " - " . htmlspecialchars($query) . "</p>";
        }
    }
}

echo "<p>正常に終了しました。<a href='dashboard.php'>ダッシュボードへ戻る</a></p>";
