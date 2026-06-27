<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . shopSignalAssetUrl('profile.php'));
    exit;
}

try {
    $currentUser = shopSignalCurrentUser();
    $pdo = Database::connect(shopSignalConfig());
    if ($pdo === null || (int) $currentUser['id'] <= 0) {
        throw new RuntimeException('Billing is available for database users only.');
    }
    shopSignalEnsureStripeSchema($pdo);
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $currentUser['id']]);
    $user = $statement->fetch();
    if (!is_array($user)) {
        throw new RuntimeException('User account was not found.');
    }

    header('Location: ' . shopSignalCreateStripePortal($user), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('ShopSignal Stripe portal: ' . $exception->getMessage());
    header('Location: ' . shopSignalAssetUrl('profile.php') . '?billing_error=' . rawurlencode($exception->getMessage()));
    exit;
}
