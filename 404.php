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
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Montserrat:wght@400;600;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: '1'; ?>">
    <link rel="stylesheet" href="/landing.css?v=<?php echo @filemtime(__DIR__ . '/landing.css') ?: '1'; ?>">
    <style>
        /* 404固有：墨・オフホワイト・グレーのみ。余白で見せるミニマル構成 */
        body {
            font-family: 'Noto Sans JP', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            background: var(--lp-paper);
            color: var(--lp-ink);
            text-align: center;
        }
        .nf-wrap { padding: 3rem 1.5rem; max-width: 600px; }
        .nf-logo { display: block; margin: 0 auto 2.4rem; height: 52px; width: auto; }
        .nf-code {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: clamp(3.6rem, 12vw, 5.6rem);
            letter-spacing: .12em;
            line-height: 1;
            color: var(--lp-ink);
            margin: 0;
        }
        .nf-code::after {
            content: ""; display: block;
            width: 30px; height: 1px; margin: 1.6rem auto 0;
            background: var(--lp-ink); opacity: .28;
        }
        .nf-lead {
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.9;
            color: var(--lp-muted);
            margin: 1.8rem auto 2.6rem;
            max-width: 26em;
        }
        .nf-home {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .85rem 2rem;
            font-size: .88rem; font-weight: 500; letter-spacing: .03em;
            text-decoration: none;
            color: #fff; background: var(--lp-ink);
            border: 1px solid var(--lp-ink); border-radius: var(--lp-radius);
            transition: background .3s, color .3s, transform .3s var(--lp-ease);
        }
        .nf-home:hover { background: transparent; color: var(--lp-ink); transform: translateY(-2px); }
        .nf-links {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--lp-line);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .9rem 1.8rem;
        }
        .nf-links a {
            white-space: nowrap;
            font-family: 'Montserrat', sans-serif;
            font-size: .74rem;
            font-weight: 500;
            letter-spacing: .14em;
            text-transform: uppercase;
            text-decoration: none;
            color: var(--lp-muted);
            transition: color .25s;
        }
        .nf-links a:hover { color: var(--lp-ink); }
    </style>
</head>
<body>
    <div class="nf-wrap">
        <a href="/"><img src="/logo.png" alt="WHABITAT" class="nf-logo"></a>
        <h1 class="nf-code">404</h1>
        <p class="nf-lead">お探しのページは見つかりませんでした。</p>
        <a href="/" class="nf-home">トップページへ戻る</a>
        <nav class="nf-links" aria-label="主要ページ">
            <a href="/#about">About</a>
            <a href="/#activities">Activities</a>
            <a href="/blog.php">Blog</a>
            <a href="/#contact">Contact</a>
            <a href="/login.php">ログイン</a>
        </nav>
    </div>
</body>
</html>
