<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequireAuth(true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }

    $type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
    $allowedTypes = ['all', 'growth', 'technology', 'product', 'traffic', 'social'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'all';
    }

    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
    $repository = new StoreRepository($pdo);
    $payload = $repository->findSignals($type, $limit);

    echo json_encode(
        [
            'ok' => true,
            'signals' => $payload['signals'],
            'profiles' => $repository->findProfilesForStores($payload['stores']),
            'meta' => [
                'type' => $type,
                'counts' => $repository->getSignalTypeCounts(),
            ],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    http_response_code(500);
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to load signals.',
    ];

    if ((bool) ($config['db_debug'] ?? false)) {
        $payload['diagnostic'] = [
            'type' => get_class($exception),
            'code' => (string) $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
