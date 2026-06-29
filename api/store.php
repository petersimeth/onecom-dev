<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

JsonApi::headers();

try {
    $config = shopSignalConfig();
    $pdo = JsonApi::database($config);

    $storeId = max(0, (int) ($_GET['id'] ?? 0));
    if ($storeId <= 0) {
        JsonApi::respond(['ok' => false, 'message' => 'Missing store id.'], 400);
    }

    $repository = new StoreRepository($pdo);
    $detail = $repository->getStoreDetail($storeId);

    if ($detail === null) {
        JsonApi::respond(['ok' => false, 'message' => 'Store not found.'], 404);
    }

    JsonApi::respond([
        'ok' => true,
        'store' => $detail['store'],
        'profile' => $detail['profile'],
    ]);
} catch (Throwable $exception) {
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    JsonApi::serverError('Unable to load store detail.', $exception, $config);
}
