<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

JsonApi::headers();

try {
    $config = shopSignalConfig();
    $pdo = JsonApi::database($config);

    $category = trim((string) ($_GET['category'] ?? ''));
    $repository = new StoreRepository($pdo);

    JsonApi::respond([
        'ok' => true,
        'products' => $repository->getProductIntelligence($category),
    ]);
} catch (Throwable $exception) {
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    JsonApi::serverError('Unable to load product intelligence data.', $exception, $config);
}
