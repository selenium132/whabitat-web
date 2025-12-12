<?php
// Database Configuration
// TODO: Update these with your Xserver database details
define('DB_HOST', 'localhost');
define('DB_NAME', 'xs923565_whabitatdb');
define('DB_USER', 'xs923565_user');
define('DB_PASS', '9Z`e*k4#K$bW');

// Circle Secret Code (for registration)
define('CIRCLE_SECRET', 'whabitat2025');     // 一般メンバー用
define('ADMIN_SECRET', 'whabitat_admin');    // 管理者（幹部）用

// API Key for Google Forms Sync (Change this to a random string)
define('API_KEY', 'himitsu_no_key_12345');

// Available Grades (Generations)
define('AVAILABLE_GRADES', ['17th', '18th', '19th', '20th']);

// LINE Login Configuration
define('LINE_CHANNEL_ID', '2008588186');
define('LINE_CHANNEL_SECRET', 'b13037697c736acbb99ddf5fa3d1431d');
define('LINE_CALLBACK_URL', 'https://whabitathome.com/v2/callback.php'); // Updated for v2 subdirectory

// LINE Messaging API (Bot) Configuration
define('LINE_BOT_ACCESS_TOKEN', 'XfDWPXXjPtI7VNVP5tbaUAEnwyxCBaDIaZtwiqZ+2cbmhOI4/CRzlsQQBQqdvHkSr9EXTcM90UQJwa2C8h0AcqI3HVpacr7HdqT4yEJ8mfY6XvGrSxT9xad9aftOPxplReoWpt7ex+BrkohL20yeegdB04t89/1O/w1cDnyilFU=');
define('LINE_BOT_CHANNEL_SECRET', '71e364600d533ca83747d44d177b92f6'); // Add this! Must be different from Login Secret

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

// Helper: Generate CSRF Token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper: Validate CSRF Token
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF Token');
    }
}

// Helper: Check Login & Approval
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // Security: Re-validate user status from DB on every request
    // This prevents banned/deleted users from staying logged in via session
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, is_approved, name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // User deleted
            header("Location: logout.php");
            exit;
        }

        // Sync Session with DB
        $_SESSION['is_approved'] = $user['is_approved'];
        $_SESSION['role'] = $user['role']; // Also sync role in case of promotion/demotion

    } catch (PDOException $e) {
        // DB Error, maybe serve 500 or just ignore and rely on session (fail open or closed?)
        // Safe to fail closed
        die("Database Error during auth check.");
    }
    
    // Check Approval Status (except for pending page)
    if (empty($_SESSION['is_approved']) && basename($_SERVER['PHP_SELF']) !== 'approval_pending.php' && basename($_SERVER['PHP_SELF']) !== 'promote.php') {
        header("Location: approval_pending.php");
        exit;
    }

    // Check Profile Completion (except for profile registration page)
    if (empty($_SESSION['name']) && basename($_SERVER['PHP_SELF']) !== 'register_profile.php' && basename($_SERVER['PHP_SELF']) !== 'approval_pending.php' && basename($_SERVER['PHP_SELF']) !== 'promote.php') {
        header("Location: register_profile.php");
        exit;
    }
}

// Helper: Check if user is Event Admin (Global Admin OR Assigned Event Admin)
function isEventAdmin($event_id) {
    // 1. Global Admin is always allowed
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // 2. Check if user is assigned as admin for this event
    if (isset($_SESSION['user_id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_admins WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            // Table might not exist yet, treat as not admin
            return false;
        }
    }

    return false;
}


// Helper: Get DB Connection
?>
