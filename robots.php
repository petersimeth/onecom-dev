<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
$base = shopSignalBasePath();
echo "User-agent: *\n";
echo 'Disallow: ' . $base . "api/\n";
echo 'Disallow: ' . $base . "scripts/\n";
echo "\n";
echo 'Sitemap: ' . shopSignalAbsoluteUrl('sitemap.xml') . "\n";
