<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/seo.php';

$appName = trim((string) (shopSignalConfig()['app_name'] ?? 'ShopSignal'));
$title = $appName . ' Privacy Policy';
$description = 'How ' . $appName . ' collects, uses, and protects personal data, the cookies it uses, and your choices and rights.';
$canonicalPath = 'privacy/';
$canonicalUrl = shopSignalAbsoluteAssetUrl($canonicalPath);
$updated = 'June 30, 2026';
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    '@id' => $canonicalUrl,
    'url' => $canonicalUrl,
    'name' => $title,
    'description' => $description,
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
      <p class="public-eyebrow">Privacy</p>
      <h1>Privacy Policy</h1>
      <p class="public-article-lead">This explains what personal data <?= htmlspecialchars($appName) ?> collects, why, and the choices you have. Last updated <?= htmlspecialchars($updated) ?>.</p>

      <section><h2>Who we are</h2><p><?= htmlspecialchars($appName) ?> provides intelligence about publicly accessible Shopify stores. This policy covers the <?= htmlspecialchars($appName) ?> web application and public directory. For questions, contact us at <a href="mailto:privacy@onecom.io">privacy@onecom.io</a>.</p></section>

      <section><h2>Data we collect</h2><p>When you create an account we collect your <strong>name and email address</strong>, and a securely hashed version of your password (we never store your password in plain text). If you subscribe, our payment processor handles your card details and shares limited billing status with us. We also keep basic account activity such as your last sign-in time.</p><p>When you browse the public store directory, we may collect standard technical information (such as your IP address and browser type) and, if you consent, analytics about how pages are used.</p></section>

      <section><h2>Cookies and similar technologies</h2><p>We use two kinds of cookies:</p><p><strong>Essential cookies</strong> keep you signed in and secure (your session and optional "remember me" cookie). These are required for the app to work and do not need consent.</p><p><strong>Analytics cookies</strong> (Google Analytics) help us understand usage. These are only set <em>after you accept</em> them in the cookie banner. Until then, analytics storage is denied by default (Google Consent Mode). You can change your choice at any time:</p><p><button type="button" class="public-button small" onclick="if(window.shopSignalOpenCookieConsent){shopSignalOpenCookieConsent();}return false;">Cookie settings</button></p></section>

      <section><h2>How we use your data</h2><p>We use your data to operate your account, authenticate you, send essential service emails (verification and password resets), provide and improve the product, process payments, and—where you consent—measure usage. We do not sell your personal data.</p></section>

      <section><h2>Third parties</h2><p>We rely on a few processors to run the service: <strong>IONOS</strong> (hosting and email delivery), <strong>Stripe</strong> (payments, if you subscribe), and <strong>Google Analytics</strong> (usage analytics, only with consent). Each processes data on our behalf under their own terms.</p></section>

      <section><h2>Data retention</h2><p>We keep account data for as long as your account is active. Email verification and password-reset tokens are short-lived and expire automatically. When you delete your account, your personal account data is removed and any active subscription is cancelled.</p></section>

      <section><h2>Your rights and choices</h2><p>You can view and update your name, email, and password from your profile, and you can <strong>delete your account</strong> yourself at any time from the profile page. Depending on where you live, you may also have rights to access, correct, export, or restrict processing of your data—contact <a href="mailto:privacy@onecom.io">privacy@onecom.io</a> and we'll help.</p><p>Information about public stores can be corrected or removed via the process described in our <a href="<?= htmlspecialchars(shopSignalAssetUrl('methodology/')) ?>">methodology</a>.</p></section>

      <section><h2>Security</h2><p>We protect accounts with hashed passwords, CSRF protection, hardened session cookies, rate-limited logins, and transport encryption. No system is perfectly secure, but we work to safeguard your data.</p></section>

      <section><h2>Changes to this policy</h2><p>We may update this policy as the product evolves. Material changes will be reflected here with a new "last updated" date.</p></section>

      <p class="public-disclaimer"><small>This page is provided for transparency and is not legal advice. Please review it with your own counsel to ensure it meets your obligations.</small></p>

      <div class="public-article-cta"><div><strong>Explore the public index.</strong><span>Browse store profiles, categories, countries, and detected applications.</span></div><a class="public-button" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Browse stores</a></div>
    </main>
    <footer class="public-footer"><span>ShopSignal · Shopify store intelligence</span><div><a href="<?= htmlspecialchars(shopSignalAssetUrl('methodology/')) ?>">Methodology</a> · <a href="<?= htmlspecialchars(shopSignalAssetUrl('pricing.php')) ?>">View plans</a></div></footer>
  </body>
</html>
