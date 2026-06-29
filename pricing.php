<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$isAuthenticated = shopSignalHasActiveSession();
$currentPlan = shopSignalCurrentPlan();
$isAdmin = shopSignalIsAdmin();
$stripeEnabled = shopSignalStripeCheckoutEnabled();
$stripePriceLabel = trim((string) (shopSignalConfig()['stripe_pro_price_label'] ?? '$29 / month')) ?: '$29 / month';
$billingError = trim((string) ($_GET['billing_error'] ?? ''));
$checkoutCancelled = ($_GET['checkout'] ?? '') === 'cancelled';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Compare ShopSignal Free and Pro plans for Shopify store intelligence, market research, technology detection, and prospecting." />
    <meta name="robots" content="index,follow" />
    <link rel="canonical" href="<?= htmlspecialchars(shopSignalAbsoluteUrl('pricing.php')) ?>" />
    <title>Pricing — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="pricing-page">
    <main class="pricing-shell">
      <nav class="pricing-top">
        <a class="brand admin-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg>
          </span>
          <span>ShopSignal</span>
        </a>
        <div class="pricing-actions">
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('stores/')) ?>">Public directory</a>
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Back to app</a>
          <?php if (!$isAuthenticated): ?>
            <a class="button primary" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Create free account</a>
          <?php elseif ($isAdmin): ?>
            <a class="button primary" href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php')) ?>">Manage users</a>
          <?php endif; ?>
        </div>
      </nav>

      <section class="pricing-hero">
        <p class="eyebrow"><span></span> Plans</p>
        <h1>Start with discovery. Upgrade when the data becomes part of your workflow.</h1>
        <p>Free users can browse a useful preview. Pro unlocks exact intelligence, exports, and the deeper research views.</p>
        <?php if ($billingError !== ''): ?><div class="auth-error pricing-message"><?= htmlspecialchars($billingError) ?></div><?php endif; ?>
        <?php if ($checkoutCancelled): ?><div class="pricing-message import-success">Checkout was cancelled. Your plan was not changed.</div><?php endif; ?>
      </section>

      <section class="pricing-grid" aria-label="ShopSignal plans">
        <article class="pricing-card <?= $currentPlan === 'free' ? 'current' : '' ?>">
          <div class="pricing-card-top">
            <span class="plan-pill">Free</span>
            <?php if ($currentPlan === 'free'): ?><small>Current plan</small><?php endif; ?>
          </div>
          <h2>$0</h2>
          <p>For testing the store index and saving early prospect ideas.</p>
          <ul>
            <li>Browse Shopify store previews</li>
            <li>Revenue and traffic ranges</li>
            <li>Save lists and saved views</li>
            <li>Limited store detail</li>
          </ul>
          <?php if ($isAuthenticated): ?>
            <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Continue on Free</a>
          <?php else: ?>
            <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Sign up free</a>
          <?php endif; ?>
        </article>

        <article class="pricing-card featured <?= in_array($currentPlan, ['pro', 'enterprise'], true) ? 'current' : '' ?>">
          <div class="pricing-card-top">
            <span class="plan-pill pro">Pro</span>
            <?php if (in_array($currentPlan, ['pro', 'enterprise'], true)): ?><small>Current plan</small><?php else: ?><small>Recommended</small><?php endif; ?>
          </div>
          <h2><?= htmlspecialchars($stripeEnabled ? $stripePriceLabel : 'Manual') ?></h2>
          <p>For prospecting, market research, exports, and repeat ecommerce analysis.</p>
          <ul>
            <li>Exact revenue, traffic, growth, and products</li>
            <li>Full store detail pages</li>
            <li>Signals, market trends, apps, and products</li>
            <li>CSV exports for explorer and lists</li>
          </ul>
          <?php if (!$isAuthenticated): ?>
            <a class="button primary" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Create account</a>
          <?php elseif ($isAdmin): ?>
            <a class="button primary" href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php')) ?>">Activate Pro for a user</a>
          <?php elseif (in_array($currentPlan, ['pro', 'enterprise'], true)): ?>
            <a class="button primary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Open Pro workspace</a>
          <?php elseif ($stripeEnabled): ?>
            <form method="post" action="<?= htmlspecialchars(shopSignalAssetUrl('checkout.php')) ?>" class="pricing-request-form">
              <?= shopSignalCsrfField() ?>
              <button class="button primary" type="submit">Upgrade with Stripe</button>
            </form>
            <form method="post" action="<?= htmlspecialchars(shopSignalAssetUrl('profile.php')) ?>" class="pricing-request-form manual-request-link">
              <?= shopSignalCsrfField() ?>
              <input type="hidden" name="action" value="request_pro" />
              <button class="button secondary" type="submit">Request manual access</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(shopSignalAssetUrl('profile.php')) ?>" class="pricing-request-form">
              <?= shopSignalCsrfField() ?>
              <input type="hidden" name="action" value="request_pro" />
              <button class="button primary" type="submit">Request Pro access</button>
            </form>
          <?php endif; ?>
        </article>

        <article class="pricing-card <?= $currentPlan === 'admin' ? 'current' : '' ?>">
          <div class="pricing-card-top">
            <span class="plan-pill admin">Admin</span>
            <?php if ($currentPlan === 'admin'): ?><small>Current access</small><?php endif; ?>
          </div>
          <h2>Internal</h2>
          <p>For maintaining data, imports, users, and plan access during testing.</p>
          <ul>
            <li>Everything in Pro</li>
            <li>User and plan management</li>
            <li>CSV imports and rollback</li>
            <li>Testing utilities</li>
          </ul>
          <?php if ($isAdmin): ?>
            <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php')) ?>">Open admin</a>
          <?php else: ?>
            <span class="button secondary disabled-link">Invite only</span>
          <?php endif; ?>
        </article>
      </section>

      <section class="pricing-note">
        <strong><?= $stripeEnabled ? 'Secure billing:' : 'Payment setup:' ?></strong>
        <span><?= $stripeEnabled ? 'Checkout and subscription management are hosted by Stripe. ShopSignal never receives card details.' : 'Add the Stripe settings to config.local.php to enable paid Checkout. Manual Pro activation remains available during testing.' ?></span>
      </section>
    </main>
  </body>
</html>
