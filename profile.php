<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAuth();

$currentUser = shopSignalCurrentUser();
$error = trim((string) ($_GET['billing_error'] ?? ''));
$message = ($_GET['checkout'] ?? '') === 'success'
    ? 'Checkout completed. Your Pro access will appear as soon as Stripe confirms the subscription.'
    : '';
$pdo = Database::connect(shopSignalConfig());
$dbUser = null;

if ($pdo !== null && $currentUser['id'] > 0) {
    shopSignalEnsureUserSchema($pdo);
    shopSignalEnsureProRequestSchema($pdo);
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $currentUser['id']]);
    $dbUser = $statement->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($pdo === null || !$dbUser) {
            throw new RuntimeException('Profile editing is available for database users only.');
        }

        $action = (string) ($_POST['action'] ?? 'update_profile');

        if ($action === 'request_pro') {
            if ((string) $dbUser['role'] === 'admin' || in_array((string) ($dbUser['plan'] ?? 'free'), ['pro', 'enterprise'], true)) {
                throw new RuntimeException('Your account already has full access.');
            }

            shopSignalCreateProRequest($pdo, (int) $dbUser['id'], (string) ($_POST['message'] ?? ''));
            $message = 'Pro access requested. An admin can now approve it from the dashboard.';
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid name and email.');
            }

            $existing = shopSignalFindUserByEmail($pdo, $email);
            if ($existing && (int) $existing['id'] !== (int) $dbUser['id']) {
                throw new RuntimeException('Another user already uses that email.');
            }

            $emailChanged = $email !== (string) $dbUser['email'];
            if ($password !== '') {
                if (mb_strlen($password) < 8) {
                    throw new RuntimeException('New password must be at least 8 characters.');
                }
                $statement = $pdo->prepare('
                    UPDATE users
                    SET name = :name,
                        email = :email,
                        password_hash = :password_hash,
                        email_verified_at = IF(:email_changed = 1, NULL, email_verified_at)
                    WHERE id = :id
                ');
                $statement->execute([
                    'name' => mb_substr($name, 0, 160),
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'email_changed' => $emailChanged ? 1 : 0,
                    'id' => (int) $dbUser['id'],
                ]);
            } else {
                $statement = $pdo->prepare('
                    UPDATE users
                    SET name = :name,
                        email = :email,
                        email_verified_at = IF(:email_changed = 1, NULL, email_verified_at)
                    WHERE id = :id
                ');
                $statement->execute([
                    'name' => mb_substr($name, 0, 160),
                    'email' => $email,
                    'email_changed' => $emailChanged ? 1 : 0,
                    'id' => (int) $dbUser['id'],
                ]);
            }

            $verificationToken = $emailChanged ? shopSignalCreateVerificationToken($pdo, (int) $dbUser['id']) : '';
            $dbUser = shopSignalFindUserByEmail($pdo, $email);
            if ($dbUser && $emailChanged) {
                shopSignalSendVerificationEmail($dbUser, $verificationToken);
            }
            $message = $emailChanged
                ? 'Profile updated. Please confirm your new email address before your next login.'
                : 'Profile updated.';
        }

        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $currentUser['id']]);
        $dbUser = $statement->fetch() ?: $dbUser;
        if ($dbUser) {
            shopSignalSetAuthenticatedUser($dbUser);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$displayUser = $dbUser ?: ['name' => $currentUser['name'], 'email' => $currentUser['email'], 'role' => $currentUser['role'], 'plan' => $currentUser['plan']];
$displayPlan = (string) ($displayUser['plan'] ?? 'free');
$proRequest = ($pdo !== null && $dbUser) ? shopSignalCurrentProRequest($pdo, (int) $dbUser['id']) : null;
$stripeEnabled = shopSignalStripeCheckoutEnabled();
$stripeCustomerId = trim((string) ($displayUser['stripe_customer_id'] ?? ''));
$subscriptionStatus = trim((string) ($displayUser['subscription_status'] ?? ''));
$subscriptionPeriodEnd = trim((string) ($displayUser['subscription_current_period_end'] ?? ''));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit profile — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Profile</p>
      <h1>Edit profile.</h1>
      <p>Role: <?= htmlspecialchars((string) $displayUser['role']) ?> · Plan: <?= htmlspecialchars($displayPlan) ?></p>
      <?php if ($stripeCustomerId !== ''): ?>
        <div class="profile-upgrade billing-card">
          <strong>Stripe billing</strong>
          <span>
            Subscription: <?= htmlspecialchars($subscriptionStatus !== '' ? ucfirst(str_replace('_', ' ', $subscriptionStatus)) : 'Pending') ?>
            <?php if ($subscriptionPeriodEnd !== ''): ?> · Current period ends <?= htmlspecialchars(date('M j, Y', strtotime($subscriptionPeriodEnd))) ?><?php endif; ?>
            <?php if (!empty($displayUser['subscription_cancel_at_period_end'])): ?> · Cancels at period end<?php endif; ?>
          </span>
          <form method="post" action="<?= htmlspecialchars(shopSignalAssetUrl('billing-portal.php')) ?>">
            <button class="button secondary" type="submit">Manage billing</button>
          </form>
        </div>
      <?php endif; ?>
      <?php if ($displayPlan === 'free'): ?>
        <div class="profile-upgrade">
          <strong>Want the full dataset?</strong>
          <?php if ($stripeEnabled): ?>
            <span>Subscribe securely with Stripe to activate Pro automatically.</span>
            <form method="post" action="<?= htmlspecialchars(shopSignalAssetUrl('checkout.php')) ?>" class="profile-upgrade-form">
              <button class="button primary" type="submit">Upgrade to Pro</button>
            </form>
            <span class="manual-access-label">Or request manual testing access:</span>
          <?php endif; ?>
          <?php if (($proRequest['status'] ?? '') === 'pending'): ?>
            <span>Your Pro request is pending since <?= htmlspecialchars((string) $proRequest['created_label']) ?>.</span>
            <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('pricing.php')) ?>">View Pro plan</a>
          <?php elseif (($proRequest['status'] ?? '') === 'rejected'): ?>
            <span>Your previous request was rejected<?= $proRequest['decided_label'] ? ' on ' . htmlspecialchars((string) $proRequest['decided_label']) : '' ?>. You can send a new request.</span>
            <form method="post" class="profile-upgrade-form">
              <input type="hidden" name="action" value="request_pro" />
              <textarea name="message" rows="3" placeholder="Optional note for the admin"></textarea>
              <button class="button primary" type="submit">Request Pro access again</button>
            </form>
          <?php else: ?>
            <span>Pro unlocks exact store details, exports, signals, market trends, apps, and products.</span>
            <form method="post" class="profile-upgrade-form">
              <input type="hidden" name="action" value="request_pro" />
              <textarea name="message" rows="3" placeholder="Optional note for the admin"></textarea>
              <button class="button primary" type="submit">Request Pro access</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="action" value="update_profile" />
        <label>Name <input name="name" value="<?= htmlspecialchars((string) $displayUser['name']) ?>" required /></label>
        <label>Email <input name="email" type="email" value="<?= htmlspecialchars((string) $displayUser['email']) ?>" required /></label>
        <label>New password <input name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password" /></label>
        <button class="button primary" type="submit">Save profile</button>
      </form>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Back to app</a></p>
    </main>
  </body>
</html>
