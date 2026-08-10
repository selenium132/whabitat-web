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

        // 管理者へLINEで新着通知（匿名投稿の匿名性はそのまま：投稿者名は入力された表示名のみ）
        $excerpt = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '…' : $message;
        linePushToAdmins("📫 目安箱に新しい投稿がありました\n\n投稿者: {$name}\n\n{$excerpt}\n\n確認: https://whabitathome.com/admin/messages.php");

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
