<?php
// Database Configuration
// TODO: Update these with your Xserver database details
define('DB_HOST', 'localhost');
define('DB_NAME', 'whabitatdb');
define('DB_USER', 'xs923565_user');
define('DB_PASS', '9Z`e*k4#K$bW');

// Circle Secret Code (for registration)
define('CIRCLE_SECRET', 'whabitat2025');     // 一般メンバー用
define('ADMIN_SECRET', 'whabitat_admin');    // 管理者（幹部）用

// API Key for Google Forms Sync (Change this to a random string)
define('API_KEY', 'himitsu_no_key_12345');

// Start Session
session_start();

// Database Connection Function
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

// Helper to check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}
?>
