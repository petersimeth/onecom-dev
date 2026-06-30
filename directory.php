<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/seo.php';
require_once __DIR__ . '/src/PublicStoreRepository.php';

$type = in_array($_GET['type'] ?? '', ['stores', 'category', 'country', 'app', 'categories', 'countries', 'apps'], true) ? (string) $_GET['type'] : 'stores';
$rawSlug = trim((string) ($_GET['slug'] ?? ''));
$slug = $rawSlug !== '' ? shopSignalSeoSlug($rawSlug) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 24;
$pdo = Database::connect(shopSignalConfig());
$repository = $pdo !== null ? new PublicStoreRepository($pdo) : null;
$dimension = null;
$dimensionIndexType = match ($type) { 'categories' => 'category', 'countries' => 'country', 'apps' => 'app', default => '' };
$isDimensionIndex = $dimensionIndexType !== '';
if ($isDimensionIndex) {
    $page = 1;
}

if ($type !== 'stores' && !$isDimensionIndex) {
    if ($repository === null) {
        $dimension = ['value' => ucfirst($slug), 'store_count' => 0];
    } else {
        $dimension = $repository->resolveDimension($type, $slug);
        if ($dimension === null) {
            shopSignalPublicNotFound('This directory page does not exist.');
        }
    }
}

$value = (string) ($dimension['value'] ?? '');
$dimensionRows = $isDimensionIndex && $repository !== null ? array_slice($repository->sitemapDimensions($dimensionIndexType), 0, 120) : [];
$total = $isDimensionIndex ? ($repository?->countDimensions($dimensionIndexType) ?? 0) : ($repository?->countStores($type, $value) ?? 0);
$pageCount = max(1, (int) ceil($total / $pageSize));
if ($page > $pageCount && $total > 0) {
    shopSignalPublicNotFound('This directory page does not exist.');
}
$stores = !$isDimensionIndex ? ($repository?->findStores($type, $value, $pageSize, ($page - 1) * $pageSize) ?? []) : [];
$categories = $repository?->topDimensions('category', 10) ?? [];
$countries = $repository?->topDimensions('country', 10) ?? [];
$apps = $repository?->topDimensions('app', 10) ?? [];

$heading = match ($type) {
    'category' => $value . ' Shopify stores',
    'country' => 'Shopify stores in ' . $value,
    'app' => 'Shopify stores using ' . $value,
    'categories' => 'Shopify store categories',
    'countries' => 'Shopify stores by country',
    'apps' => 'Shopify app adoption directory',
    default => 'Shopify store directory',
};
$description = match ($type) {
    'category' => 'Explore indexed ' . $value . ' Shopify stores, public company profiles, catalog ranges, technology examples, and recent observations.',
    'country' => 'Discover Shopify stores based in ' . $value . ' with public category, catalog, technology, and company information.',
    'app' => 'Browse Shopify stores where ShopSignal has detected ' . $value . ', with public store and catalog profiles.',
    'categories' => 'Explore curated Shopify store categories and discover public company, catalog, technology, and activity profiles.',
    'countries' => 'Explore Shopify stores by country and discover regional ecommerce brands and public store profiles.',
    'apps' => 'Explore detected Shopify applications and browse public profiles for stores using each technology.',
    default => 'Browse public profiles for indexed Shopify stores, including categories, countries, catalog ranges, technologies, and recent activity.',
};
$title = $heading . ($page > 1 ? ' — Page ' . $page : '') . ' | ShopSignal';
$canonicalPath = shopSignalDirectoryPublicPath($type, $value, $isDimensionIndex ? 1 : $page);
$canonicalUrl = shopSignalAbsoluteAssetUrl($canonicalPath);
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'CollectionPage', '@id' => $canonicalUrl . '#directory', 'url' => $canonicalUrl, 'name' => $heading, 'description' => $description],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Stores', 'item' => shopSignalAbsoluteAssetUrl('stores/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $heading, 'item' => $canonicalUrl],
        ]],
    ],
];
$robots = $repository === null || (!$isDimensionIndex && $type !== 'stores' && $total < 3)
    ? 'noindex,follow'
    : 'index,follow,max-image-preview:large';
if ($repository === null) {
    http_response_code(503);
    header('Retry-After: 300');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <?php shopSignalPublicPageHeader($title, $description, $canonicalPath, $jsonLd, $robots); ?>
    <?php if ($page > 1): ?><link rel="prev" href="<?= htmlspecialchars(shopSignalAbsoluteAssetUrl(shopSignalDirectoryPublicPath($type, $value, $page - 1))) ?>" /><?php endif; ?>
    <?php if ($page < $pageCount): ?><link rel="next" href="<?= htmlspecialchars(shopSignalAbsoluteAssetUrl(shopSignalDirectoryPublicPath($type, $value, $page + 1))) ?>" /><?php endif; ?>
  </head>
  <body class="public-page">
    <?php shopSignalGoogleBodyTag(); ?>
    <header class="public-header">
      <a class="public-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>"><span>◇</span> ShopSignal</a>
      <nav aria-label="Public navigation"><a href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Stores</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('categories/')) ?>">Categories</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('countries/')) ?>">Countries</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('apps/')) ?>">Apps</a></nav>
      <a class="public-button small" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Create free account</a>
    </header>
    <main class="public-shell directory-shell">
      <section class="directory-hero">
        <p class="public-eyebrow">Shopify intelligence directory</p>
        <h1><?= htmlspecialchars($heading) ?></h1>
        <p><?= htmlspecialchars($description) ?></p>
        <div class="directory-count"><strong><?= number_format($total) ?></strong><span><?= $isDimensionIndex ? 'curated directory pages' : 'indexed stores' ?><?= !$isDimensionIndex && $page > 1 ? ' · page ' . $page . ' of ' . $pageCount : '' ?></span></div>
      </section>

      <div class="directory-layout">
        <aside class="directory-facets">
          <?php foreach ([['category', 'Top categories', $categories], ['country', 'Top countries', $countries], ['app', 'Popular apps', $apps]] as [$facetType, $facetTitle, $rows]): ?>
            <section><h2><?= htmlspecialchars($facetTitle) ?></h2><?php if ($rows === []): ?><p class="public-muted">Available when the database is connected.</p><?php else: ?><div><?php foreach ($rows as $row): ?><a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath($facetType, (string) $row['value']))) ?>"><span><?= htmlspecialchars((string) $row['value']) ?></span><b><?= number_format((int) $row['store_count']) ?></b></a><?php endforeach; ?></div><?php endif; ?></section>
          <?php endforeach; ?>
        </aside>

        <section class="directory-results">
          <div class="public-section-heading"><div><p class="public-eyebrow">Results</p><h2><?= htmlspecialchars($heading) ?></h2></div><span>Updated continuously</span></div>
          <?php if ($repository === null): ?>
            <div class="directory-empty"><h2>Store data is temporarily unavailable.</h2><p>Please try again shortly.</p></div>
          <?php elseif ($isDimensionIndex): ?>
            <div class="dimension-index-grid">
              <?php foreach ($dimensionRows as $row): ?>
                <a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath($dimensionIndexType, (string) $row['value']))) ?>">
                  <span><?= htmlspecialchars((string) $row['value']) ?></span>
                  <strong><?= number_format((int) $row['store_count']) ?></strong>
                  <small>stores →</small>
                </a>
              <?php endforeach; ?>
            </div>
          <?php elseif ($stores === []): ?>
            <div class="directory-empty"><h2>No stores found.</h2><p>This curated directory will appear once enough matching stores are indexed.</p></div>
          <?php else: ?>
            <div class="directory-store-grid">
              <?php foreach ($stores as $store): ?>
                <a class="directory-store-card" href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalStorePublicPath($store))) ?>">
                  <div class="directory-card-top"><span class="public-card-logo"><?= htmlspecialchars((string) ($store['logo_letter'] ?: mb_substr((string) $store['name'], 0, 1))) ?></span><span class="directory-arrow">↗</span></div>
                  <strong><?= htmlspecialchars((string) $store['name']) ?></strong><span><?= htmlspecialchars((string) $store['domain']) ?></span>
                  <div><small><?= htmlspecialchars((string) $store['category']) ?></small><small><?= htmlspecialchars((string) ($store['country'] ?: 'Location unknown')) ?></small></div>
                  <p><?= htmlspecialchars(shopSignalProductCountRange((int) $store['product_count'])) ?><?= (int) $store['founded_year'] > 0 ? ' · Founded ' . (int) $store['founded_year'] : '' ?></p>
                </a>
              <?php endforeach; ?>
            </div>
            <?php if ($pageCount > 1): ?>
              <nav class="public-pagination" aria-label="Directory pagination">
                <?php if ($page > 1): ?><a rel="prev" href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath($type, $value, $page - 1))) ?>">← Previous</a><?php else: ?><span></span><?php endif; ?>
                <strong>Page <?= $page ?> of <?= $pageCount ?></strong>
                <?php if ($page < $pageCount): ?><a rel="next" href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath($type, $value, $page + 1))) ?>">Next →</a><?php else: ?><span></span><?php endif; ?>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      </div>
    </main>
    <footer class="public-footer"><span>ShopSignal · Shopify store intelligence</span><div><a href="<?= htmlspecialchars(shopSignalAssetUrl('methodology/')) ?>">Methodology</a> · <a href="<?= htmlspecialchars(shopSignalAssetUrl('pricing.php')) ?>">View plans</a></div></footer>
  </body>
</html>
