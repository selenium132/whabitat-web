<?php
// Redirect old URLs to new equivalents
// This handles 404 pages from the old site

$old_urls = [
    '/about-us' => '/#about',
    '/about-us/' => '/#about',
    '/contact' => '/#contact',
    '/contact/' => '/#contact',
    '/activity' => '/activity_domestic.php',
    '/activity/' => '/activity_domestic.php',
    '/gv' => '/activity_gv.php',
    '/jv' => '/activity_jv.php',
    '/domestic' => '/activity_domestic.php',
    '/member' => '/dashboard.php',
    '/member/' => '/dashboard.php',
    '/login' => '/login.php',
];

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Check if it's an old URL that needs redirect
foreach ($old_urls as $old => $new) {
    if ($path === $old) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: https://whabitathome.com" . $new);
        exit;
    }
}

// If no match, show 404 page
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ページが見つかりません | WHABITAT</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        .container { padding: 2rem; max-width: 600px; }
        .logo { display: block; margin: 0 auto 1.5rem; height: 56px; width: auto; }
        h1 { font-size: 4rem; margin: 0; }
        p { font-size: 1.2rem; opacity: 0.9; }
        a { color: white; text-decoration: underline; }
        .links {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        .links a { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/"><img src="/logo.png" alt="WHABITAT" class="logo"></a>
        <h1>404</h1>
        <p>お探しのページは見つかりませんでした。</p>
        <p><a href="/">トップページへ戻る</a></p>
        <nav class="links" aria-label="主要ページ">
            <a href="/#about">About</a>
            <a href="/#activities">Activities</a>
            <a href="/blog.php">Blog</a>
            <a href="/#contact">Contact</a>
            <a href="/login.php">ログイン</a>
        </nav>
    </div>
</body>
</html>
