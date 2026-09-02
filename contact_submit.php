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
            'content' => http_build_query($verify_data),
            'timeout' => 5, // Google側の遅延でPHPワーカーを長時間占有しないため（失敗時は従来どおり送信を拒否）
        ]
    ];

    $context = stream_context_create($options);
    $verify_result = file_get_contents($verify_url, false, $context);
    $verify_json = json_decode($verify_result, true);

    if (!($verify_json['success'] ?? false)) {
        $_SESSION['contact_error'] = 'reCAPTCHAの確認に失敗しました。もう一度お試しください。';
        header("Location: index.php#contact");
        exit;
    }

    // Honeypot check - if the hidden "website" field is filled, it's a bot
    if (!empty($_POST['website'])) {
        // Silently ignore (don't give feedback to bots)
        header("Location: index.php#contact");
        exit;
    }

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    // Block HTML tags (spam typically contains <a href=...>)
    if (preg_match('/<[a-z?!\/]/i', $name . $email . $message)) {
        $_SESSION['contact_error'] = 'HTMLタグを含むメッセージは送信できません。';
        header("Location: index.php#contact");
        exit;
    }

    // Block suspicious domains/keywords commonly used in spam
    $spam_patterns = [
        '/\b(888starz|casino|poker|bet|gambling)\b/i',
        '/\.(store|online|top|xyz|icu|buzz)\//i',
    ];
    foreach ($spam_patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            // Silently ignore spam
            header("Location: index.php#contact");
            exit;
        }
    }

    if ($name && $email && $message) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, source, is_read) VALUES (?, ?, ?, 'contact', 0)");
        $stmt->execute([$name, $email, $message]);

        // 管理者へLINEで新着通知（見落とし防止。失敗しても送信処理は続行）
        $excerpt = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '…' : $message;
        linePushToAdmins("📮 サイトに新しいお問い合わせが届きました\n\n名前: {$name}\n\n{$excerpt}\n\n確認: https://whabitathome.com/admin/messages.php");

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
