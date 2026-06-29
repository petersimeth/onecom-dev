<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

JsonApi::headers();

try {
    $config = shopSignalConfig();
    $pdo = JsonApi::database($config);

    $technology = trim((string) ($_GET['technology'] ?? ''));
    $repository = new StoreRepository($pdo);

    JsonApi::respond([
        'ok' => true,
        'apps' => $repository->getTechnologyIntelligence($technology),
    ]);
} catch (Throwable $exception) {
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    JsonApi::serverError('Unable to load apps and technology data.', $exception, $config);
}
