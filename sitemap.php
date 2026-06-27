<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/seo.php';
require_once __DIR__ . '/src/PublicStoreRepository.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$pdo = Database::connect(shopSignalConfig());
if ($pdo === null) {
    http_response_code(503);
    header('Retry-After: 300');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$repository = new PublicStoreRepository($pdo);
$section = (string) ($_GET['section'] ?? 'index');
$xml = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';

if ($section === 'index') {
    $chunkSize = 50000;
    $chunks = max(1, (int) ceil($repository->sitemapStoreCount() / $chunkSize));
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    for ($page = 1; $page <= $chunks; $page++) {
        echo '<sitemap><loc>' . $xml(shopSignalAbsoluteAssetUrl('sitemaps/stores-' . $page . '.xml')) . '</loc></sitemap>';
    }
    echo '<sitemap><loc>' . $xml(shopSignalAbsoluteAssetUrl('sitemaps/directories.xml')) . '</loc></sitemap>';
    echo '</sitemapindex>';
    exit;
}

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
if ($section === 'stores') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $chunkSize = 50000;
    foreach ($repository->sitemapStores($chunkSize, ($page - 1) * $chunkSize) as $store) {
        echo '<url><loc>' . $xml(shopSignalAbsoluteAssetUrl(shopSignalStorePublicPath($store))) . '</loc>';
        if (!empty($store['updated_at'])) {
            echo '<lastmod>' . $xml(date(DATE_ATOM, strtotime((string) $store['updated_at']) ?: time())) . '</lastmod>';
        }
        echo '</url>';
    }
} elseif ($section === 'directories') {
    foreach (['stores/', 'categories/', 'countries/', 'apps/', 'methodology/'] as $path) {
        echo '<url><loc>' . $xml(shopSignalAbsoluteAssetUrl($path)) . '</loc></url>';
    }
    foreach (['category', 'country', 'app'] as $type) {
        foreach ($repository->sitemapDimensions($type) as $row) {
            if ((int) ($row['store_count'] ?? 0) < 3) {
                continue;
            }
            echo '<url><loc>' . $xml(shopSignalAbsoluteAssetUrl(shopSignalDirectoryPublicPath($type, (string) $row['value']))) . '</loc>';
            if (!empty($row['updated_at'])) {
                echo '<lastmod>' . $xml(date(DATE_ATOM, strtotime((string) $row['updated_at']) ?: time())) . '</lastmod>';
            }
            echo '</url>';
        }
    }
}
echo '</urlset>';
