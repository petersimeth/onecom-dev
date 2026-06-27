<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }

    $storeId = max(0, (int) ($_GET['id'] ?? 0));
    if ($storeId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing store id.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $repository = new StoreRepository($pdo);
    $detail = $repository->getStoreDetail($storeId);

    if ($detail === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Store not found.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(
        [
            'ok' => true,
            'store' => $detail['store'],
            'profile' => $detail['profile'],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    http_response_code(500);
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to load store detail.',
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
