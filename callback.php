<?php
require_once 'config.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (!$code || $state !== $_SESSION['line_state']) {
    die('Invalid Request');
}

// 1. Get Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/oauth2/v2.1/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => LINE_CALLBACK_URL,
    'client_id' => LINE_CHANNEL_ID,
    'client_secret' => LINE_CHANNEL_SECRET,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    die('Token Error: ' . $response);
}

// 2. Get User Profile
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/profile');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$profile = json_decode(curl_exec($ch), true);

$line_user_id = $profile['userId'];
$line_name = $profile['displayName'];
$avatar_url = $profile['pictureUrl'] ?? '';

// 3. Check DB
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
$stmt->execute([$line_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Existing User
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['is_approved'] = $user['is_approved']; // Set Approval Status
    
    // If profile is incomplete, force update
    if (empty($user['name']) || empty($user['student_id'])) {
        header("Location: register_profile.php");
    } else {
        header("Location: dashboard.php");
    }
} else {
    // New User -> Create basic record and redirect to profile fill
    $stmt = $pdo->prepare("INSERT INTO users (line_user_id, line_name, avatar_url) VALUES (?, ?, ?)");
    $stmt->execute([$line_user_id, $line_name, $avatar_url]);
    
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['role'] = 'member';
    
    header("Location: register_profile.php");
}
exit;
?>
