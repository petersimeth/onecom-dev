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

    $repository = new StoreRepository($pdo);

    echo json_encode(
        [
            'ok' => true,
            'market' => $repository->getMarketTrends(),
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    http_response_code(500);
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to load market trends.',
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
