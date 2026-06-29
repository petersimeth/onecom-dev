<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$next = (string) ($_GET['next'] ?? shopSignalAssetUrl('index.php'));
$error = '';
$submittedUser = '';

if (!str_starts_with($next, '/')) {
    $next = shopSignalAssetUrl('index.php');
}

$alreadySignedIn = shopSignalHasActiveSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    shopSignalRequireCsrf();

    $user = trim((string) ($_POST['user'] ?? ''));
    $submittedUser = $user;
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? $next);
    if (!str_starts_with($next, '/')) {
        $next = shopSignalAssetUrl('index.php');
    }

    $pdo = Database::connect(shopSignalConfig());
    $lockedSeconds = $pdo ? shopSignalLoginLockedSeconds($pdo, $user) : 0;

    if ($lockedSeconds > 0) {
        $minutes = (int) ceil($lockedSeconds / 60);
        $error = 'Too many sign-in attempts. Please wait about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' and try again.';
    } elseif (shopSignalAttemptLogin($user, $password)) {
        if ($pdo) {
            shopSignalClearFailedLogins($pdo, $user);
            if (($_POST['remember'] ?? '') === '1') {
                shopSignalIssueRememberToken($pdo, (int) ($_SESSION['shopsignal_user_id'] ?? 0));
            }
        }
        header('Location: ' . ($next !== '' ? $next : shopSignalAssetUrl('index.php')));
        exit;
    } else {
        if ($pdo) {
            shopSignalRecordFailedLogin($pdo, $user);
        }
        // Intentionally generic so the form never reveals whether an email
        // exists or whether it is merely unverified. Users who still need to
        // confirm their email can use the resend link below.
        $error = 'The email or password was not correct, or your email is not yet confirmed.';
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,follow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32">
            <path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" />
            <path d="m11.8 17.1 2.7 2.7 5.9-7" />
          </svg>
        </span>
        <span>ShopSignal</span>
      </div>
      <p class="eyebrow"><span></span> Secure workspace</p>
      <h1>Welcome back.</h1>
      <p>Sign in to access the Shopify intelligence dashboard.</p>

      <?php if (($_GET['account_deleted'] ?? '') === '1'): ?>
        <div class="import-success">Your account has been deleted. We’re sorry to see you go.</div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="auth-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!shopSignalAuthEnabled()): ?>
        <div class="auth-error">Auth is not enabled yet. Add auth settings to config.local.php.</div>
      <?php endif; ?>

      <?php if ($alreadySignedIn): ?>
        <div class="import-success">
          You are already signed in as <?= htmlspecialchars(shopSignalAuthUser()) ?>.
          You can sign in with another account by logging out first.
        </div>
        <p class="auth-switch">
          <a href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Continue to app</a>
          ·
          <a href="<?= htmlspecialchars(shopSignalAssetUrl('logout.php')) ?>">Logout</a>
        </p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <?= shopSignalCsrfField() ?>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>" />
        <label>
          Email or config admin username
          <input name="user" autocomplete="username" value="<?= htmlspecialchars($submittedUser) ?>" required autofocus />
        </label>
        <label>
          Password
          <span class="password-field">
            <input id="login-password" name="password" type="password" autocomplete="current-password" required />
            <button type="button" class="password-toggle" data-target="login-password" aria-label="Show password">Show</button>
          </span>
        </label>
        <label class="auth-checkbox">
          <input type="checkbox" name="remember" value="1" />
          <span>Keep me signed in for 30 days</span>
        </label>
        <button class="button primary" type="submit">Sign in</button>
      </form>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('forgot-password.php')) ?>">Forgot your password?</a></p>
      <p class="auth-switch">Need an account? <a href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Register</a></p>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('resend-verification.php')) ?>">Resend verification email</a></p>
    </main>
    <script src="<?= htmlspecialchars(shopSignalVersionedAssetUrl('auth.js')) ?>" defer></script>
  </body>
</html>
