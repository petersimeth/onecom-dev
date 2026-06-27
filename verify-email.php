<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$error = '';
$message = '';
$token = (string) ($_GET['token'] ?? '');
$pdo = Database::connect(shopSignalConfig());

try {
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }
    if ($token === '') {
        throw new RuntimeException('Verification token is missing.');
    }

    shopSignalEnsureUserSchema($pdo);
    shopSignalEnsurePendingRegistrationSchema($pdo);
    $tokenHash = hash('sha256', $token);
    $statement = $pdo->prepare('
        SELECT *
        FROM pending_registrations
        WHERE verification_token_hash = :hash
        LIMIT 1
    ');
    $statement->execute(['hash' => $tokenHash]);
    $pending = $statement->fetch();

    if (!$pending) {
        throw new RuntimeException('This verification link is invalid or has already been used.');
    }

    if (shopSignalFindUserByEmail($pdo, (string) $pending['email'])) {
        $pdo->prepare('DELETE FROM pending_registrations WHERE id = :id')->execute(['id' => (int) $pending['id']]);
        throw new RuntimeException('A user with this email already exists. Please sign in instead.');
    }

    $role = shopSignalUserCount($pdo) === 0 ? 'admin' : 'user';
    $insert = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, plan, status, email_verified_at)
        VALUES (:name, :email, :password_hash, :role, \'free\', \'active\', NOW())
    ');
    $insert->execute([
        'name' => (string) $pending['name'],
        'email' => (string) $pending['email'],
        'password_hash' => (string) $pending['password_hash'],
        'role' => $role,
    ]);

    $pdo->prepare('DELETE FROM pending_registrations WHERE id = :id')->execute(['id' => (int) $pending['id']]);

    $message = 'Email confirmed and account created. You can sign in now.';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify email — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Email verification</p>
      <h1><?= $error === '' ? 'Confirmed.' : 'Could not verify.' ?></h1>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('login.php')) ?>">Go to login</a></p>
    </main>
  </body>
</html>
