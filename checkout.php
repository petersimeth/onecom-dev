<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . shopSignalAssetUrl('pricing.php'));
    exit;
}

try {
    $currentUser = shopSignalCurrentUser();
    if ((int) $currentUser['id'] <= 0) {
        throw new RuntimeException('Checkout is available for database users only.');
    }
    if (shopSignalHasProAccess()) {
        header('Location: ' . shopSignalAssetUrl('profile.php'));
        exit;
    }

    $pdo = Database::connect(shopSignalConfig());
    if ($pdo === null) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    shopSignalEnsureStripeSchema($pdo);
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) $currentUser['id']]);
    $user = $statement->fetch();
    if (!is_array($user)) {
        throw new RuntimeException('User account was not found.');
    }

    header('Location: ' . shopSignalCreateStripeCheckout($user), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('ShopSignal Stripe Checkout: ' . $exception->getMessage());
    header('Location: ' . shopSignalAssetUrl('pricing.php') . '?billing_error=' . rawurlencode($exception->getMessage()));
    exit;
}
