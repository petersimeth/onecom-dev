<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = shopSignalConfig();
$debug = filter_var($config['db_debug'] ?? false, FILTER_VALIDATE_BOOL);

try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('The PHP pdo_mysql extension is not enabled.');
    }

    $pdo = Database::connect($config);

    if ($pdo === null) {
        echo json_encode([
            'connected' => false,
            'message' => 'No database configuration found.',
            'checks' => [
                'config_local_php' => is_file(dirname(__DIR__) . '/config.local.php'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
            ],
        ]);
        exit;
    }

    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tableStatement = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = :database
          AND table_name = \'stores\'
    ');
    $tableStatement->execute(['database' => $database]);
    $schemaImported = (bool) $tableStatement->fetchColumn();

    if (!$schemaImported) {
        throw new RuntimeException('MySQL connected, but the stores table is missing. Import database/schema.sql.');
    }

    $storeCount = (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn();

    echo json_encode([
        'connected' => true,
        'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'database' => $database,
        'schema_imported' => true,
        'stores' => $storeCount,
        'seed_imported' => $storeCount > 0,
    ]);
} catch (Throwable $exception) {
    http_response_code(503);
    $response = [
        'connected' => false,
        'message' => 'Database connection failed. Check config.local.php and the imported schema.',
        'checks' => [
            'config_local_php' => is_file(dirname(__DIR__) . '/config.local.php'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
        ],
    ];

    if ($debug) {
        $response['diagnostic'] = [
            'type' => get_class($exception),
            'code' => (string) $exception->getCode(),
            'error' => $exception->getMessage(),
        ];
    } else {
        $response['hint'] = 'Set db_debug to true in config.local.php temporarily to see the MySQL error.';
    }

    echo json_encode($response);
}
