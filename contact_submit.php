<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    if ($name && $email && $message) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $message]);

        // Redirect with success flag
        header("Location: index.html?contact=success#contact");
        exit;
    }
}

// If invalid, redirect back
header("Location: index.html#contact");
exit;
