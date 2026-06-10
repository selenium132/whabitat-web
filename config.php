<?php
// ===========================================
// Load Environment Variables from .env
// ===========================================
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    die('ERROR: .env file not found. System cannot continue without environment configuration.');
}
$env = parse_ini_file($env_path);
if ($env === false) {
    die('ERROR: Failed to parse .env file.');
}

// Database Configuration
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? '');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? '');

// Circle Secret Code (for registration)
define('CIRCLE_SECRET', $env['CIRCLE_SECRET'] ?? '');    // 一般メンバー用
define('ADMIN_SECRET', $env['ADMIN_SECRET'] ?? '');    // 管理者（幹部）用

// API Key for Google Forms Sync
define('API_KEY', $env['API_KEY'] ?? '');

// Available Grades (Generations) - dynamically calculated
// Base: fiscal year 2026 -> newest gen = 20th
$current_fy = ((int)date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;
$newest_gen = 20 + ($current_fy - 2026);
define('AVAILABLE_GRADES', array_map(function($g) { return $g . 'th'; }, range($newest_gen - 3, $newest_gen + 1)));

// LINE Login Configuration
define('LINE_CHANNEL_ID', $env['LINE_CHANNEL_ID'] ?? '');
define('LINE_CHANNEL_SECRET', $env['LINE_CHANNEL_SECRET'] ?? '');
define('LINE_CALLBACK_URL', $env['LINE_CALLBACK_URL'] ?? '');

// LINE Messaging API (Bot) Configuration
define('LINE_BOT_ACCESS_TOKEN', $env['LINE_BOT_ACCESS_TOKEN'] ?? '');
define('LINE_BOT_CHANNEL_SECRET', $env['LINE_BOT_CHANNEL_SECRET'] ?? '');

// reCAPTCHA v2 Configuration
define('RECAPTCHA_SITE_KEY', $env['RECAPTCHA_SITE_KEY'] ?? '');
define('RECAPTCHA_SECRET_KEY', $env['RECAPTCHA_SECRET_KEY'] ?? '');

// Security: Session Hardening
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Enable for HTTPS
ini_set('session.cookie_samesite', 'None'); // Required for OAuth redirects (LINE login)

// Security: Disable Error Display in Production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
        error_log("DB connection failed: " . $e->getMessage());
        die("システムエラーが発生しました。時間をおいて再度お試しください。");
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
        // Use JavaScript redirect to capture the full URL including hash (anchor)
        // Fallback to PHP session storage if JS fails (or for API calls) can happen in login.php via 'next' param
        
        $login_url = 'login.php';
        
        // Output simple HTML with JS redirect
        // We pass the current full URL as 'next' parameter to login.php
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
        echo '<script>window.location.href = "' . $login_url . '?next=" + encodeURIComponent(window.location.href);</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $login_url . '"></noscript>';
        echo '</body></html>';
        exit;
    }

    // Security: Re-validate user status from DB on every request
    // This prevents banned/deleted users from staying logged in via session
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, is_approved, name, role, name_kana, gender, zipcode, address, phone, birthdate, grade FROM users WHERE id = ?");
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

        // Check if profile is missing any of the newly added required fields
        $profile_incomplete = (
            empty($user['name_kana']) || 
            empty($user['gender']) || 
            empty($user['zipcode']) || 
            empty($user['address']) || 
            empty($user['phone']) || 
            empty($user['birthdate']) || 
            empty($user['grade'])
        );

    } catch (PDOException $e) {
        die("Database Error during auth check.");
    }
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_unapproved = ['approval_pending.php', 'logout.php'];
    $allowed_incomplete = ['register_profile.php', 'approval_pending.php', 'logout.php'];

    // Check Approval Status
    if (empty($_SESSION['is_approved']) && !in_array($current_page, $allowed_unapproved)) {
        header("Location: approval_pending.php");
        exit;
    }

    // Check Profile Completion (Force existing & new users to complete their profile)
    if (!empty($_SESSION['is_approved']) && $profile_incomplete && !in_array($current_page, $allowed_incomplete)) {
        header("Location: register_profile.php");
        exit;
    }
}

// Helper: Check if user is Event Admin (Global Admin, Creator, OR Assigned Event Admin)
function isEventAdmin($event_id) {
    $event_id = (int)$event_id;
    // 1. Global Admin is always allowed
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // 不正/空のIDは以降のDB判定不要
    if ($event_id <= 0) return false;

    if (isset($_SESSION['user_id'])) {
        try {
            $pdo = getDB();
            
            // 2. Check if user is the creator of the event
            $stmt_creator = $pdo->prepare("SELECT COUNT(*) FROM events WHERE id = ? AND created_by = ?");
            $stmt_creator->execute([$event_id, $_SESSION['user_id']]);
            if ($stmt_creator->fetchColumn() > 0) return true;
            
            // 3. Check if user is assigned as admin for this event
            $stmt_admin = $pdo->prepare("SELECT COUNT(*) FROM event_admins WHERE event_id = ? AND user_id = ?");
            $stmt_admin->execute([$event_id, $_SESSION['user_id']]);
            if ($stmt_admin->fetchColumn() > 0) return true;
        } catch (PDOException $e) {
            // Table might not exist yet, ignore
        }
    }

    return false;
}


// Helper: Get DB Connection
?>
