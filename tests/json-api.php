<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/JsonApi.php';

$exception = new RuntimeException('Test failure', 42);
$diagnostic = JsonApi::diagnostic($exception);

if ($diagnostic !== [
    'type' => RuntimeException::class,
    'code' => '42',
    'message' => 'Test failure',
]) {
    fwrite(STDERR, "Unexpected diagnostic payload.\n");
    exit(1);
}

$production = JsonApi::errorPayload('Unable to load.', $exception, ['db_debug' => false]);
if (isset($production['diagnostic']) || $production['message'] !== 'Unable to load.') {
    fwrite(STDERR, "Production error payload leaked diagnostics.\n");
    exit(1);
}

$debug = JsonApi::errorPayload('Unable to load.', $exception, ['db_debug' => true]);
if (($debug['diagnostic']['message'] ?? '') !== 'Test failure') {
    fwrite(STDERR, "Debug error payload omitted diagnostics.\n");
    exit(1);
}

echo "JSON API contract passed.\n";
