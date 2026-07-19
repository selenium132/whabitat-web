<?php
// sitemap.xml の動的生成（.htaccess の RewriteRule ^sitemap\.xml$ で配信）
// 静的ページ + 公開済みブログ記事。DB接続に失敗しても静的ページだけは必ず返す。
require_once 'config.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$base = 'https://whabitathome.com';

$pages = [
    ['loc' => '/',                       'changefreq' => 'weekly',  'priority' => '1.0'],
    ['loc' => '/activity_gv.php',        'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => '/activity_jv.php',        'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => '/activity_domestic.php',  'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => '/activity_mtg.php',       'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/blog.php',               'changefreq' => 'weekly',  'priority' => '0.7'],
];

$blogs = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, GREATEST(COALESCE(updated_at, created_at), created_at) AS lastmod FROM blogs WHERE is_published = 1 ORDER BY created_at DESC");
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // DB不調時は静的ページのみ
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pages as $p) {
    echo "    <url>\n";
    echo "        <loc>" . htmlspecialchars($base . $p['loc'], ENT_XML1) . "</loc>\n";
    echo "        <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "        <priority>{$p['priority']}</priority>\n";
    echo "    </url>\n";
}

foreach ($blogs as $b) {
    $lastmod = $b['lastmod'] ? date('Y-m-d', strtotime($b['lastmod'])) : null;
    echo "    <url>\n";
    echo "        <loc>" . htmlspecialchars($base . '/blog_view.php?id=' . (int)$b['id'], ENT_XML1) . "</loc>\n";
    if ($lastmod) echo "        <lastmod>{$lastmod}</lastmod>\n";
    echo "        <changefreq>monthly</changefreq>\n";
    echo "        <priority>0.6</priority>\n";
    echo "    </url>\n";
}

echo '</urlset>' . "\n";
