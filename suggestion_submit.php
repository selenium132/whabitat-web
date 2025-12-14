<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // If name is empty, use "匿名"
    if (empty($name)) {
        $name = '匿名（目安箱）';
    } else {
        $name = $name . '（目安箱）';
    }

    // Use logged-in user's info for email (but can be hidden)
    $email = '目安箱からの投稿';

    if ($message) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $message]);

        // Set flash message
        $_SESSION['suggestion_success'] = true;

        // Redirect back to dashboard
        header("Location: dashboard.php");
        exit;
    }
}

// If invalid, redirect back
header("Location: dashboard.php");
exit;
