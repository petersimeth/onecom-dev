<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$configFile = __DIR__ . '/config.local.php';
$result = [
    'checker_version' => '2026-06-26-1',
    'php_version' => PHP_VERSION,
    'config_found' => is_file($configFile),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
];

try {
    if (!is_file($configFile)) {
        throw new RuntimeException('config.local.php was not found beside this checker.');
    }

    $config = require $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('config.local.php must return an array.');
    }

    $dsn = (string) ($config['db_dsn'] ?? '');
    $result['dsn_present'] = $dsn !== '';
    $result['dsn_safe'] = preg_replace('/password=[^;]*/i', 'password=***', $dsn);
    $result['user_present'] = trim((string) ($config['db_user'] ?? '')) !== '';

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('The pdo_mysql PHP extension is not enabled.');
    }

    if ($dsn === '') {
        throw new RuntimeException('db_dsn is empty.');
    }

    $pdo = new PDO(
        $dsn,
        (string) ($config['db_user'] ?? ''),
        (string) ($config['db_password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $result['connected'] = true;
    $result['database'] = $database;

    $statement = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = :database
          AND table_name = \'stores\'
    ');
    $statement->execute(['database' => $database]);
    $result['stores_table'] = (bool) $statement->fetchColumn();
    $result['stores'] = $result['stores_table']
        ? (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn()
        : null;
} catch (Throwable $exception) {
    http_response_code(503);
    $result['connected'] = false;
    $result['error_type'] = get_class($exception);
    $result['error_code'] = (string) $exception->getCode();
    $result['error'] = $exception->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
