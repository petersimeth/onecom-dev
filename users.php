<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAdmin();

$pdo = Database::connect(shopSignalConfig());
$error = '';
$message = '';
$pendingProRequests = [];

if ($pdo === null) {
    $error = 'Database is not configured.';
} else {
    shopSignalEnsureUserSchema($pdo);
    shopSignalEnsurePendingRegistrationSchema($pdo);
    shopSignalEnsureProRequestSchema($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    try {
        $action = (string) ($_POST['action'] ?? '');
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($action === 'update_user') {
            $role = in_array($_POST['role'] ?? '', ['user', 'admin'], true) ? (string) $_POST['role'] : 'user';
            $plan = in_array($_POST['plan'] ?? '', ['free', 'pro', 'enterprise'], true) ? (string) $_POST['plan'] : 'free';
            $status = in_array($_POST['status'] ?? '', ['active', 'disabled'], true) ? (string) $_POST['status'] : 'active';
            if ($userId === shopSignalCurrentUser()['id'] && $status === 'disabled') {
                throw new RuntimeException('You cannot disable your own account.');
            }
            $statement = $pdo->prepare('UPDATE users SET role = :role, plan = :plan, status = :status WHERE id = :id');
            $statement->execute(['role' => $role, 'plan' => $plan, 'status' => $status, 'id' => $userId]);
            $message = 'User updated.';
        }

        if ($action === 'decide_pro_request') {
            $decision = (string) ($_POST['decision'] ?? '');
            shopSignalDecideProRequest($pdo, (int) ($_POST['request_id'] ?? 0), $decision, shopSignalCurrentUser()['id']);
            $message = $decision === 'approved' ? 'Pro access approved.' : 'Pro access request rejected.';
        }

        if ($action === 'delete_user') {
            if ($userId === shopSignalCurrentUser()['id']) {
                throw new RuntimeException('You cannot delete your own account.');
            }
            $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $userId]);
            $message = 'User deleted.';
        }

        if ($action === 'delete_non_admins') {
            $pdo->prepare('DELETE FROM users WHERE role <> \'admin\'')->execute();
            $pdo->exec('DELETE FROM pending_registrations');
            $message = 'All non-admin users and pending registrations were deleted.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$users = [];
if ($pdo !== null) {
    $users = $pdo->query('
        SELECT id, name, email, role, plan, status, email_verified_at, stripe_customer_id, subscription_status, subscription_current_period_end, subscription_cancel_at_period_end, DATE_FORMAT(created_at, \'%b %e, %Y\') AS created_label, DATE_FORMAT(last_login_at, \'%b %e, %Y %H:%i\') AS last_login_label
        FROM users
        ORDER BY role = \'admin\' DESC, id ASC
    ')->fetchAll();
    $pendingProRequests = shopSignalPendingProRequests($pdo);
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Users — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <div class="admin-top">
        <a class="brand admin-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" /><path d="m11.8 17.1 2.7 2.7 5.9-7" /></svg></span><span>ShopSignal</span></a>
        <div class="admin-actions">
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('register.php')) ?>">Register user</a>
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Back to app</a>
        </div>
      </div>

      <section class="admin-hero">
        <p class="eyebrow"><span></span> Admin</p>
        <h1>Users & roles.</h1>
        <p>Manage registered users, admin access, and account status.</p>
      </section>

      <section class="admin-card">
        <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="import-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($pendingProRequests !== []): ?>
          <div class="pending-users pro-request-panel">
            <h4>Pending Pro requests</h4>
            <?php foreach ($pendingProRequests as $request): ?>
              <article class="pending-user-row">
                <div>
                  <strong><?= htmlspecialchars((string) $request['name']) ?></strong>
                  <span><?= htmlspecialchars((string) $request['email']) ?> · Requested <?= htmlspecialchars((string) $request['created_label']) ?></span>
                  <?php if (trim((string) ($request['message'] ?? '')) !== ''): ?><p><?= htmlspecialchars((string) $request['message']) ?></p><?php endif; ?>
                </div>
                <div class="pending-actions">
                  <form method="post">
                    <input type="hidden" name="action" value="decide_pro_request" />
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>" />
                    <input type="hidden" name="decision" value="approved" />
                    <button class="button primary" type="submit">Approve Pro</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Reject this Pro request?');">
                    <input type="hidden" name="action" value="decide_pro_request" />
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>" />
                    <input type="hidden" name="decision" value="rejected" />
                    <button class="button secondary danger" type="submit">Reject</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" class="bulk-danger-form" onsubmit="return confirm('Delete all non-admin users and pending registrations? This is for testing and cannot be undone.');">
          <input type="hidden" name="action" value="delete_non_admins" />
          <button class="button secondary danger" type="submit">Delete all non-admin users</button>
        </form>
        <div class="user-table">
          <?php foreach ($users as $user): ?>
            <article class="user-row">
              <div>
                <strong><?= htmlspecialchars((string) $user['name']) ?></strong>
                <span>
                  <?= htmlspecialchars((string) $user['email']) ?>
                  · <?= $user['email_verified_at'] ? 'Verified' : 'Unverified' ?>
                  · <?= htmlspecialchars((string) ($user['plan'] ?? 'free')) ?> plan
                  · Billing <?= htmlspecialchars((string) (($user['subscription_status'] ?? '') ?: 'not connected')) ?>
                  <?= !empty($user['subscription_cancel_at_period_end']) ? ' · Cancels at period end' : '' ?>
                  · Joined <?= htmlspecialchars((string) $user['created_label']) ?>
                  · Last login <?= htmlspecialchars((string) ($user['last_login_label'] ?: 'never')) ?>
                </span>
              </div>
              <form method="post" class="user-controls">
                <input type="hidden" name="action" value="update_user" />
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <select name="role">
                  <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                  <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <select name="plan">
                  <option value="free" <?= ($user['plan'] ?? 'free') === 'free' ? 'selected' : '' ?>>Free</option>
                  <option value="pro" <?= ($user['plan'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro</option>
                  <option value="enterprise" <?= ($user['plan'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                </select>
                <select name="status">
                  <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="disabled" <?= $user['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
                <button class="button secondary" type="submit">Save</button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this user?');">
                <input type="hidden" name="action" value="delete_user" />
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <button class="button secondary danger" type="submit">Delete</button>
              </form>
            </article>
          <?php endforeach; ?>
          <?php if ($users === []): ?><p class="admin-note">No database users yet. Register the first user to create an admin.</p><?php endif; ?>
        </div>
      </section>
    </main>
  </body>
</html>
