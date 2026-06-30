<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        shopSignalRequireCsrf();

        $pdo = Database::connect(shopSignalConfig());
        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        // Send a fresh link only where one is actually needed (a pending
        // signup, or an existing account whose email is not yet confirmed).
        // We never disclose which case applied: the response below is identical
        // whether the address is unknown, already verified, or pending, so the
        // form cannot be used to discover which emails have accounts.
        shopSignalEnsurePendingRegistrationSchema($pdo);
        $pendingLookup = $pdo->prepare('SELECT * FROM pending_registrations WHERE email = :email LIMIT 1');
        $pendingLookup->execute(['email' => $email]);
        $pending = $pendingLookup->fetch();

        if ($pending) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('
                UPDATE pending_registrations
                SET verification_token_hash = :hash,
                    verification_sent_at = NOW()
                WHERE id = :id
            ')->execute(['hash' => hash('sha256', $token), 'id' => (int) $pending['id']]);
            shopSignalSendVerificationEmail($pending, $token);
        } else {
            $user = shopSignalFindUserByEmail($pdo, $email);
            if ($user && $user['email_verified_at'] === null) {
                $token = shopSignalCreateVerificationToken($pdo, (int) $user['id']);
                shopSignalSendVerificationEmail($user, $token);
            }
        }

        $message = 'If that email needs confirmation, a fresh verification link is on its way. Please check your inbox.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Resend verification — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
    <?php shopSignalGoogleHeadTags(); ?>
  </head>
  <body class="auth-page">
    <?php shopSignalGoogleBodyTag(); ?>
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Email verification</p>
      <h1>Resend link.</h1>
      <p>Enter your email and we’ll send a fresh confirmation link.</p>
      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?= shopSignalCsrfField() ?>
        <label>Email <input name="email" type="email" autocomplete="email" required /></label>
        <button class="button primary" type="submit">Send verification email</button>
      </form>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Back to login</a></p>
    </main>
  </body>
</html>
