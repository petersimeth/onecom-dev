<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$next = (string) ($_GET['next'] ?? shopSignalAssetUrl('index.php'));
$error = '';

if (!str_starts_with($next, '/')) {
    $next = shopSignalAssetUrl('index.php');
}

$alreadySignedIn = shopSignalHasActiveSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? $next);
    if (!str_starts_with($next, '/')) {
        $next = shopSignalAssetUrl('index.php');
    }

    $pdo = Database::connect(shopSignalConfig());
    $dbUser = $pdo ? shopSignalFindUserByEmail($pdo, $user) : null;

    if ($dbUser && password_verify($password, (string) $dbUser['password_hash']) && $dbUser['email_verified_at'] === null) {
        $error = 'Please confirm your email address before signing in.';
    } elseif (shopSignalAttemptLogin($user, $password)) {
        header('Location: ' . ($next !== '' ? $next : shopSignalAssetUrl('index.php')));
        exit;
    } else {
        $error = 'The username or password was not correct.';
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
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>" />
        <label>
          Email or config admin username
          <input name="user" autocomplete="username" value="" required />
        </label>
        <label>
          Password
          <input name="password" type="password" autocomplete="current-password" required />
        </label>
        <button class="button primary" type="submit">Sign in</button>
      </form>
      <p class="auth-switch">Need an account? <a href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Register</a></p>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('resend-verification.php')) ?>">Resend verification email</a></p>
    </main>
  </body>
</html>
