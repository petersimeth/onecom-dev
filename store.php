<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/seo.php';
require_once __DIR__ . '/src/PublicStoreRepository.php';

$storeId = max(0, (int) ($_GET['id'] ?? 0));
if ($storeId <= 0) {
    shopSignalPublicNotFound('The requested store profile does not exist.');
}

$pdo = Database::connect(shopSignalConfig());
if ($pdo === null) {
    http_response_code(503);
    header('Retry-After: 300');
    $store = null;
} else {
    $store = (new PublicStoreRepository($pdo))->findStore($storeId);
}
if ($pdo !== null && !is_array($store)) {
    shopSignalPublicNotFound('The requested store profile does not exist.');
}

if (!is_array($store)) {
    $title = 'Store directory temporarily unavailable — ShopSignal';
    ?><!doctype html><html lang="en"><head><?php shopSignalPublicPageHeader($title, 'ShopSignal store intelligence is temporarily unavailable.', 'stores/', [], 'noindex,follow'); ?></head><body class="public-page"><main class="public-empty"><a class="public-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">ShopSignal</a><h1>Store data is temporarily unavailable.</h1><p>Please try again shortly.</p></main></body></html><?php
    exit;
}

$canonicalPath = shopSignalStorePublicPath($store);
$requestedSlug = trim((string) ($_GET['slug'] ?? ''));
if ($requestedSlug !== '' && $requestedSlug !== shopSignalSeoSlug((string) $store['domain'])) {
    header('Location: ' . shopSignalAssetUrl($canonicalPath), true, 301);
    exit;
}

$storeName = (string) $store['name'];
$category = (string) ($store['category'] ?: 'Shopify store');
$country = (string) ($store['country'] ?: 'Location unavailable');
$founded = (int) ($store['founded_year'] ?? 0);
$storeDomain = preg_replace('/[^a-z0-9.-]/i', '', preg_replace('#^https?://#', '', (string) $store['domain'])) ?: (string) $store['domain'];
$description = $storeName . ' is a ' . $category . ' Shopify store' . ($country !== 'Location unavailable' ? ' based in ' . $country : '') . '. Explore its public catalog, technology, and activity profile on ShopSignal.';
$title = $storeName . ' Shopify Store: Apps, Products & Company Profile — ShopSignal';
$canonicalUrl = shopSignalAbsoluteAssetUrl($canonicalPath);
$breadcrumbs = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Stores', 'item' => shopSignalAbsoluteAssetUrl('stores/')],
    ['@type' => 'ListItem', 'position' => 2, 'name' => $storeName, 'item' => $canonicalUrl],
];
$organizationLd = [
    '@type' => 'Organization',
    '@id' => $canonicalUrl . '#store',
    'name' => $storeName,
    'url' => 'https://' . $storeDomain,
];
if ($founded > 0) {
    $organizationLd['foundingDate'] = (string) $founded;
}
if ($country !== 'Location unavailable') {
    $organizationLd['location'] = ['@type' => 'Country', 'name' => $country];
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl,
            'url' => $canonicalUrl,
            'name' => $title,
            'description' => $description,
            'dateModified' => date(DATE_ATOM, strtotime((string) $store['updated_at']) ?: time()),
            'isPartOf' => ['@id' => shopSignalAbsoluteAssetUrl('stores/') . '#directory'],
            'mainEntity' => ['@id' => $canonicalUrl . '#store'],
        ],
        $organizationLd,
        ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs],
    ],
];
?>
<!doctype html>
<html lang="en">
  <head><?php shopSignalPublicPageHeader($title, $description, $canonicalPath, $jsonLd); ?></head>
  <body class="public-page">
    <header class="public-header">
      <a class="public-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>"><span>◇</span> ShopSignal</a>
      <nav aria-label="Public navigation">
        <a href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Stores</a>
        <a href="<?= htmlspecialchars(shopSignalAssetUrl('categories/')) ?>">Categories</a>
        <a href="<?= htmlspecialchars(shopSignalAssetUrl('countries/')) ?>">Countries</a>
        <a href="<?= htmlspecialchars(shopSignalAssetUrl('apps/')) ?>">Apps</a>
      </nav>
      <a class="public-button small" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Unlock full data</a>
    </header>

    <main class="public-shell">
      <nav class="public-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Stores</a><span>/</span><span><?= htmlspecialchars($storeName) ?></span>
      </nav>

      <section class="public-store-hero">
        <div class="public-store-logo <?= htmlspecialchars((string) ($store['logo_class'] ?? '')) ?>"><?= htmlspecialchars((string) ($store['logo_letter'] ?: mb_substr($storeName, 0, 1))) ?></div>
        <div>
          <p class="public-eyebrow">Shopify store profile</p>
          <h1><?= htmlspecialchars($storeName) ?></h1>
          <p><?= htmlspecialchars($description) ?></p>
          <div class="public-tags">
            <a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath('category', $category))) ?>"><?= htmlspecialchars($category) ?></a>
            <?php if ($country !== 'Location unavailable'): ?><a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath('country', $country))) ?>"><?= htmlspecialchars($country) ?></a><?php endif; ?>
            <span>Updated <?= htmlspecialchars(date('M j, Y', strtotime((string) $store['updated_at']) ?: time())) ?></span>
          </div>
        </div>
        <a class="public-domain-link" href="https://<?= htmlspecialchars($storeDomain) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $store['domain']) ?> ↗</a>
      </section>

      <section class="public-metric-grid" aria-label="Store summary">
        <article><span>Category</span><strong><?= htmlspecialchars($category) ?></strong></article>
        <article><span>Headquarters</span><strong><?= htmlspecialchars((string) ($store['headquarters'] ?: $country)) ?></strong></article>
        <article><span>Founded</span><strong><?= $founded > 0 ? $founded : 'Unknown' ?></strong></article>
        <article><span>Catalog size</span><strong><?= htmlspecialchars(shopSignalProductCountRange((int) $store['product_count'])) ?></strong></article>
      </section>

      <div class="public-content-grid">
        <div class="public-main-column">
          <section class="public-panel">
            <div class="public-section-heading"><div><p class="public-eyebrow">Technology</p><h2>Detected commerce stack</h2></div><span>Public sample</span></div>
            <?php if ($store['technologies'] !== []): ?>
              <div class="public-tech-list">
                <?php foreach ($store['technologies'] as $technology): ?>
                  <a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath('app', (string) $technology['technology_name']))) ?>">
                    <i><?= htmlspecialchars(mb_strtoupper(mb_substr((string) $technology['technology_name'], 0, 2))) ?></i>
                    <span><strong><?= htmlspecialchars((string) $technology['technology_name']) ?></strong><small><?= htmlspecialchars((string) $technology['category']) ?></small></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?><p class="public-muted">No public technology detections are available yet.</p><?php endif; ?>
          </section>

          <section class="public-panel">
            <div class="public-section-heading"><div><p class="public-eyebrow">Catalog</p><h2>Product examples</h2></div><span><?= htmlspecialchars(shopSignalProductCountRange((int) $store['product_count'])) ?></span></div>
            <?php if ($store['products'] !== []): ?>
              <div class="public-product-list">
                <?php foreach ($store['products'] as $product): ?>
                  <article><div><strong><?= htmlspecialchars((string) $product['name']) ?></strong><span><?= htmlspecialchars((string) ($product['category'] ?: 'Product')) ?></span></div><b><?= htmlspecialchars((string) $product['currency_symbol'] . number_format((float) $product['price'], 2)) ?></b></article>
                <?php endforeach; ?>
              </div>
            <?php else: ?><p class="public-muted">No public product examples are available yet.</p><?php endif; ?>
          </section>

          <section class="public-panel">
            <div class="public-section-heading"><div><p class="public-eyebrow">Observations</p><h2>Recent store activity</h2></div><span>Titles only</span></div>
            <?php if ($store['signals'] !== []): ?>
              <div class="public-signal-list">
                <?php foreach ($store['signals'] as $signal): ?>
                  <article><i></i><div><small><?= htmlspecialchars(ucfirst((string) $signal['signal_type'])) ?> · <?= htmlspecialchars((string) $signal['occurred_label']) ?></small><strong><?= htmlspecialchars((string) $signal['title']) ?></strong></div></article>
                <?php endforeach; ?>
              </div>
            <?php else: ?><p class="public-muted">No recent public observations are available.</p><?php endif; ?>
          </section>
        </div>

        <aside class="public-side-column">
          <section class="public-upgrade-card">
            <p class="public-eyebrow">ShopSignal Pro</p>
            <h2>Go beyond the public profile.</h2>
            <p>Unlock exact traffic and revenue estimates, the complete technology stack, catalog intelligence, contacts, and detailed buying signals.</p>
            <a class="public-button" href="<?= htmlspecialchars(shopSignalAssetUrl('pricing.php')) ?>">View Pro access</a>
          </section>
          <section class="public-method-card">
            <strong>About this data</strong>
            <p>ShopSignal combines public storefront observations with modeled ecommerce estimates. Data can be incomplete and should be verified before business decisions.</p>
            <a href="<?= htmlspecialchars(shopSignalAssetUrl('methodology/')) ?>">Read our methodology →</a>
          </section>
        </aside>
      </div>

      <?php if ($store['related'] !== []): ?>
        <section class="public-related">
          <div class="public-section-heading"><div><p class="public-eyebrow">Discover</p><h2>Related <?= htmlspecialchars($category) ?> stores</h2></div><a href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalDirectoryPublicPath('category', $category))) ?>">View category →</a></div>
          <div class="public-store-grid">
            <?php foreach ($store['related'] as $related): ?>
              <a class="public-store-card" href="<?= htmlspecialchars(shopSignalAssetUrl(shopSignalStorePublicPath($related))) ?>"><span class="public-card-logo"><?= htmlspecialchars((string) ($related['logo_letter'] ?: mb_substr((string) $related['name'], 0, 1))) ?></span><div><strong><?= htmlspecialchars((string) $related['name']) ?></strong><span><?= htmlspecialchars((string) $related['domain']) ?></span><small><?= htmlspecialchars((string) $related['country']) ?> · <?= htmlspecialchars(shopSignalProductCountRange((int) $related['product_count'])) ?></small></div></a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>

    <footer class="public-footer"><span>ShopSignal · Shopify store intelligence</span><div><a href="<?= htmlspecialchars(shopSignalAssetUrl('methodology/')) ?>">Methodology</a> · <a href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Open application</a></div></footer>
  </body>
</html>
