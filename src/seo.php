<?php
declare(strict_types=1);

function shopSignalSeoSlug(string $value): string
{
    $value = trim(mb_strtolower($value));
    if (function_exists('transliterator_transliterate')) {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        if (is_string($transliterated)) {
            $value = $transliterated;
        }
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'store';
}

function shopSignalStorePublicPath(array $store): string
{
    return 'stores/' . (int) ($store['id'] ?? 0) . '-' . shopSignalSeoSlug((string) ($store['domain'] ?? $store['name'] ?? 'store'));
}

function shopSignalDirectoryPublicPath(string $type, string $value = '', int $page = 1): string
{
    $prefix = match ($type) {
        'category' => 'categories',
        'categories' => 'categories',
        'country' => 'countries',
        'countries' => 'countries',
        'app' => 'apps',
        'apps' => 'apps',
        default => 'stores',
    };
    $path = $prefix . '/';
    if ($value !== '') {
        $path .= shopSignalSeoSlug($value) . '/';
    }
    if ($page > 1) {
        $path .= 'page/' . $page . '/';
    }
    return $path;
}

function shopSignalAbsoluteAssetUrl(string $path): string
{
    return shopSignalAbsoluteUrl(ltrim($path, '/'));
}

function shopSignalProductCountRange(int $count): string
{
    return match (true) {
        $count >= 5000 => '5,000+ products',
        $count >= 1000 => '1,000–5,000 products',
        $count >= 500 => '500–1,000 products',
        $count >= 100 => '100–500 products',
        $count > 0 => 'Under 100 products',
        default => 'Catalog size unavailable',
    };
}

function shopSignalPublicPageHeader(string $title, string $description, string $canonicalPath, array $jsonLd = [], string $robots = 'index,follow,max-image-preview:large', string $imagePath = 'og-image.png'): void
{
    $canonical = shopSignalAbsoluteAssetUrl($canonicalPath);
    $image = $imagePath !== '' ? shopSignalAbsoluteAssetUrl($imagePath) : '';
    ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>" />
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>" />
    <?php $verification = trim((string) (shopSignalConfig()['google_site_verification'] ?? '')); ?>
    <?php if ($verification !== ''): ?><meta name="google-site-verification" content="<?= htmlspecialchars($verification) ?>" /><?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>" />
    <meta property="og:site_name" content="ShopSignal" />
    <meta property="og:locale" content="en_US" />
    <?php if ($image !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($image) ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="<?= htmlspecialchars($title) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="<?= htmlspecialchars($image) ?>" />
    <?php else: ?>
    <meta name="twitter:card" content="summary" />
    <?php endif; ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>" />
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>" />
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('public.css')) ?>" />
    <?php if ($jsonLd !== []): ?>
      <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endif; ?>
    <?php
}

function shopSignalPublicNotFound(string $message = 'This page could not be found.'): never
{
    http_response_code(404);
    $title = 'Page not found — ShopSignal';
    ?>
    <!doctype html>
    <html lang="en"><head><?php shopSignalPublicPageHeader($title, $message, 'stores/', [], 'noindex,follow'); ?></head>
    <body class="public-page"><main class="public-empty"><a class="public-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">ShopSignal</a><h1>Page not found.</h1><p><?= htmlspecialchars($message) ?></p><a class="public-button" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Browse stores</a></main></body></html>
    <?php
    exit;
}
