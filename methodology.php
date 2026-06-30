<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/seo.php';

$title = 'ShopSignal Data Methodology — Shopify Store Intelligence';
$description = 'Learn how ShopSignal discovers Shopify stores, observes public storefront data, models estimates, refreshes profiles, and handles corrections.';
$canonicalPath = 'methodology/';
$canonicalUrl = shopSignalAbsoluteAssetUrl($canonicalPath);
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    '@id' => $canonicalUrl,
    'url' => $canonicalUrl,
    'name' => $title,
    'description' => $description,
    'about' => ['@type' => 'Thing', 'name' => 'Shopify store intelligence methodology'],
];
?>
<!doctype html>
<html lang="en">
  <head><?php shopSignalPublicPageHeader($title, $description, $canonicalPath, $jsonLd); ?></head>
  <body class="public-page">
    <?php shopSignalGoogleBodyTag(); ?>
    <header class="public-header">
      <a class="public-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>"><span>◇</span> ShopSignal</a>
      <nav aria-label="Public navigation"><a href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Stores</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('categories/')) ?>">Categories</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('countries/')) ?>">Countries</a><a href="<?= htmlspecialchars(shopSignalAssetUrl('apps/')) ?>">Apps</a></nav>
      <a class="public-button small" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Create free account</a>
    </header>
    <main class="public-shell public-article">
      <p class="public-eyebrow">Transparency</p>
      <h1>How ShopSignal builds store intelligence.</h1>
      <p class="public-article-lead">ShopSignal organizes observations from publicly accessible ecommerce storefronts into searchable company, catalog, technology, and activity profiles.</p>

      <section><h2>Store discovery</h2><p>Stores are discovered from public web pages, links, and storefront signals associated with Shopify. A detected store is reviewed by automated checks before it is added to the index.</p></section>
      <section><h2>Public observations</h2><p>Profiles may include domain, category, country, public catalog examples, detected storefront technologies, and visible changes over time. ShopSignal does not claim that every detection is complete or permanent.</p></section>
      <section><h2>Modeled estimates</h2><p>Traffic, revenue, growth, and similar commercial metrics are estimates derived from observed signals and statistical models. They are directional research indicators—not audited financial statements—and should be independently verified.</p></section>
      <section><h2>Refresh frequency</h2><p>Store records are refreshed on different schedules based on activity and crawl priority. Each public profile displays its latest meaningful update date. Sitemap dates change only when page content changes materially.</p></section>
      <section><h2>Corrections and removals</h2><p>Store ownership, technology, and catalog information can change. Businesses should contact the site operator to request a correction or removal. Valid requests should identify the store domain and the information requiring review.</p></section>
      <section><h2>Responsible use</h2><p>ShopSignal is intended for market research, ecommerce analysis, and business discovery. Users are responsible for complying with applicable privacy, marketing, and communications laws.</p></section>

      <div class="public-article-cta"><div><strong>Explore the public index.</strong><span>Browse store profiles, categories, countries, and detected applications.</span></div><a class="public-button" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Browse stores</a></div>
    </main>
    <footer class="public-footer"><span>ShopSignal · Shopify store intelligence</span><div><a href="<?= htmlspecialchars(shopSignalAssetUrl('pricing.php')) ?>">View plans</a> · <a href="<?= htmlspecialchars(shopSignalAssetUrl('privacy/')) ?>">Privacy</a> · <a href="#" onclick="if(window.shopSignalOpenCookieConsent){shopSignalOpenCookieConsent();}return false;">Cookie settings</a></div></footer>
  </body>
</html>
