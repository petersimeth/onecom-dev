<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$error = '';
$message = '';
$done = false;
// The token travels in the query string on first load and in a hidden field on
// submit. Keep whichever is present so the form can re-post on validation error.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

$pdo = Database::connect(shopSignalConfig());
$reset = ($pdo !== null && $token !== '') ? shopSignalFindPasswordReset($pdo, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        shopSignalRequireCsrf();

        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }
        if ($reset === null) {
            throw new RuntimeException('This reset link is invalid or has expired. Please request a new one.');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (mb_strlen($password) < 8) {
            throw new RuntimeException('Your new password must be at least 8 characters.');
        }
        if (!hash_equals($password, $confirm)) {
            throw new RuntimeException('The two passwords did not match.');
        }

        shopSignalConsumePasswordReset(
            $pdo,
            (int) $reset['reset_id'],
            (int) $reset['user']['id'],
            password_hash($password, PASSWORD_DEFAULT)
        );

        $message = 'Your password has been updated. You can sign in with your new password now.';
        $done = true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$tokenValid = $done || $reset !== null;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Choose a new password — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Account recovery</p>
      <h1>Choose a new password.</h1>

      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <?php if ($done): ?>
        <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Continue to login</a></p>
      <?php elseif (!$tokenValid): ?>
        <p>This reset link is invalid or has expired.</p>
        <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('forgot-password.php')) ?>">Request a new reset link</a></p>
      <?php else: ?>
        <p>Pick a password with at least 8 characters.</p>
        <form method="post" class="auth-form">
          <?= shopSignalCsrfField() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
          <label>New password
            <span class="password-field">
              <input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus />
              <button type="button" class="password-toggle" data-target="reset-password" aria-label="Show password">Show</button>
            </span>
          </label>
          <label>Confirm password
            <input name="password_confirm" type="password" autocomplete="new-password" minlength="8" required />
          </label>
          <button class="button primary" type="submit">Update password</button>
        </form>
        <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Back to login</a></p>
      <?php endif; ?>
    </main>
    <script>
      document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
          var input = document.getElementById(button.dataset.target);
          if (!input) { return; }
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          button.textContent = show ? 'Hide' : 'Show';
          button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
      });
    </script>
  </body>
</html>
