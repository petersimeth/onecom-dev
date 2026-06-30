<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAdmin();

$error = '';
$message = '';
$currentUser = shopSignalCurrentUser();
$defaultTo = (string) ($currentUser['email'] ?? '');
$transport = shopSignalSmtpConfigured() ? 'SMTP (' . htmlspecialchars((string) shopSignalConfig()['smtp_host']) . ')' : 'PHP mail()';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        shopSignalRequireCsrf();

        $to = mb_strtolower(trim((string) ($_POST['to'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid recipient email address.');
        }

        $appName = trim((string) (shopSignalConfig()['app_name'] ?? 'ShopSignal'));
        $sent = shopSignalSendMail(
            $to,
            $appName . ' email test',
            "This is a test message from {$appName}.\n\n"
            . "If you received it, transactional email is working.\n"
            . 'Sent at ' . date('r') . "\n"
        );

        if (!$sent) {
            throw new RuntimeException('The mailer reported a failure. Check the server error log for details (SMTP errors are logged there).');
        }
        $message = 'Test email sent to ' . $to . ' via ' . (shopSignalSmtpConfigured() ? 'SMTP' : 'PHP mail()') . '. Check the inbox (and spam folder).';
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
    <title>Email test — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
    <?php shopSignalGoogleHeadTags(); ?>
  </head>
  <body class="auth-page">
    <?php shopSignalGoogleBodyTag(); ?>
    <main class="auth-card">
      <div class="brand auth-brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></div>
      <p class="eyebrow"><span></span> Admin tools</p>
      <h1>Send a test email.</h1>
      <p>Current transport: <strong><?= $transport ?></strong></p>

      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <form method="post" class="auth-form">
        <?= shopSignalCsrfField() ?>
        <label>Send to <input name="to" type="email" autocomplete="email" value="<?= htmlspecialchars($defaultTo) ?>" required autofocus /></label>
        <button class="button primary" type="submit">Send test email</button>
      </form>
      <p class="auth-switch"><a href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php')) ?>">Back to admin</a></p>
    </main>
  </body>
</html>
