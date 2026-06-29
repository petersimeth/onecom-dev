<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

JsonApi::headers();

try {
    $config = shopSignalConfig();
    $pdo = JsonApi::database($config);

    $type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
    $allowedTypes = ['all', 'growth', 'technology', 'product', 'traffic', 'social'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'all';
    }

    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
    $repository = new StoreRepository($pdo);
    $payload = $repository->findSignals($type, $limit);

    JsonApi::respond([
        'ok' => true,
        'signals' => $payload['signals'],
        'profiles' => $repository->findProfilesForStores($payload['stores']),
        'meta' => [
            'type' => $type,
            'counts' => $repository->getSignalTypeCounts(),
        ],
    ]);
} catch (Throwable $exception) {
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    JsonApi::serverError('Unable to load signals.', $exception, $config);
}
