<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    validateCsrfToken($_POST['csrf_token'] ?? '');

    // reCAPTCHA Verification
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptcha_response)) {
        $_SESSION['contact_error'] = 'reCAPTCHAを確認してください。';
        header("Location: index.php#contact");
        exit;
    }

    // Verify with Google
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $verify_data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($verify_data)
        ]
    ];

    $context = stream_context_create($options);
    $verify_result = file_get_contents($verify_url, false, $context);
    $verify_json = json_decode($verify_result, true);

    if (!$verify_json['success']) {
        $_SESSION['contact_error'] = 'reCAPTCHAの確認に失敗しました。もう一度お試しください。';
        header("Location: index.php#contact");
        exit;
    }

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    if ($name && $email && $message) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, source, is_read) VALUES (?, ?, ?, 'contact', 0)");
        $stmt->execute([$name, $email, $message]);

        // Set flash message
        $_SESSION['contact_success'] = true;

        // Redirect
        header("Location: index.php#contact");
        exit;
    }
}

// If invalid, redirect back
header("Location: index.php#contact");
exit;
