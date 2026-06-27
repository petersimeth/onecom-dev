<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$error = '';
$message = '';
$pdo = Database::connect(shopSignalConfig());
$userCount = $pdo ? shopSignalUserCount($pdo) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            throw new RuntimeException('Enter a name, valid email, and password with at least 8 characters.');
        }
        if (shopSignalFindUserByEmail($pdo, $email)) {
            throw new RuntimeException('A user with this email already exists.');
        }

        $pending = shopSignalCreatePendingRegistration($pdo, $name, $email, password_hash($password, PASSWORD_DEFAULT));
        $emailSent = shopSignalSendVerificationEmail($pending, (string) $pending['token']);
        $message = $emailSent
            ? 'Please check your email and click the confirmation link to create your account.'
            : 'The verification email could not be sent. Your account has not been created yet. Check mail_from/server mail settings.';
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
    <title>Register — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Create account</p>
      <h1>Join the workspace.</h1>
      <p><?= $userCount === 0 ? 'The first confirmed account becomes an admin on the free plan.' : 'Confirm your email to create a free user account.' ?></p>

      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <form method="post" class="auth-form">
        <label>Name <input name="name" autocomplete="name" required /></label>
        <label>Email <input name="email" type="email" autocomplete="email" required /></label>
        <label>Password <input name="password" type="password" autocomplete="new-password" minlength="8" required /></label>
        <button class="button primary" type="submit">Create account</button>
      </form>
      <p class="auth-switch">Already have an account? <a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Sign in</a></p>
    </main>
  </body>
</html>
