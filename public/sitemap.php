<?php
require_once __DIR__ . '/config/config.php';

$base = rtrim(SITE_URL, '/') . '/';
$today = date('Y-m-d');

$pages = [
    ['url' => '',                      'changefreq' => 'weekly',  'priority' => '1.0'],
    ['url' => 'events.php',            'changefreq' => 'weekly',  'priority' => '0.8'],
    ['url' => 'senior-movie.php',      'changefreq' => 'monthly', 'priority' => '0.8'],
    ['url' => 'private-screenings.php','changefreq' => 'monthly', 'priority' => '0.8'],
    ['url' => 'concessions.php',       'changefreq' => 'monthly', 'priority' => '0.6'],
    ['url' => 'location.php',          'changefreq' => 'yearly',  'priority' => '0.7'],
    ['url' => 'contact.php',           'changefreq' => 'yearly',  'priority' => '0.6'],
];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . $p['url']) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
