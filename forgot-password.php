<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        shopSignalRequireCsrf();

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        $pdo = Database::connect(shopSignalConfig());
        if ($pdo !== null) {
            $user = shopSignalFindUserByEmail($pdo, $email);
            // Only send to active, confirmed accounts. We never reveal whether
            // the address matched, so the response is identical either way.
            if ($user && $user['status'] === 'active' && $user['email_verified_at'] !== null) {
                $token = shopSignalCreatePasswordReset($pdo, (int) $user['id']);
                shopSignalSendPasswordResetEmail($user, $token);
            }
        }

        // Same message regardless of whether the email exists (no enumeration).
        $message = 'If an account exists for that email, a reset link is on its way. Please check your inbox.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,follow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset password — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Account recovery</p>
      <h1>Forgot your password?</h1>
      <p>Enter your email and we’ll send you a link to choose a new password.</p>

      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <form method="post" class="auth-form">
        <?= shopSignalCsrfField() ?>
        <label>Email <input name="email" type="email" autocomplete="email" required autofocus /></label>
        <button class="button primary" type="submit">Send reset link</button>
      </form>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Back to login</a></p>
    </main>
  </body>
</html>
