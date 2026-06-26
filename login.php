<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$next = (string) ($_GET['next'] ?? shopSignalAssetUrl('index.php'));
$error = '';

if (!str_starts_with($next, '/')) {
    $next = shopSignalAssetUrl('index.php');
}

if (shopSignalIsAuthenticated() && shopSignalAuthEnabled()) {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? $next);
    if (!str_starts_with($next, '/')) {
        $next = shopSignalAssetUrl('index.php');
    }

    if (shopSignalAttemptLogin($user, $password)) {
        header('Location: ' . ($next !== '' ? $next : shopSignalAssetUrl('index.php')));
        exit;
    }

    $error = 'The username or password was not correct.';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
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

      <form method="post" class="auth-form">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>" />
        <label>
          Username
          <input name="user" autocomplete="username" value="<?= htmlspecialchars(shopSignalAuthUser()) ?>" required />
        </label>
        <label>
          Password
          <input name="password" type="password" autocomplete="current-password" required />
        </label>
        <button class="button primary" type="submit">Sign in</button>
      </form>
    </main>
  </body>
</html>
