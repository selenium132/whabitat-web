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
        $name = '匿名';
    }

    // Use logged-in user's info for email (but can be hidden)
    $email = '目安箱';

    if ($message) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, source, is_read) VALUES (?, ?, ?, 'suggestion', 0)");
        $stmt->execute([$name, $email, $message]);

        // Set flash message
        $_SESSION['suggestion_success'] = true;

        // Redirect back to dashboard
        header("Location: dashboard.php#suggestion");
        exit;
    }

    // Message was empty: give feedback instead of silently returning
    $_SESSION['suggestion_error'] = '内容を入力してください。';
    header("Location: dashboard.php#suggestion");
    exit;
}

// If invalid, redirect back
header("Location: dashboard.php#suggestion");
exit;
