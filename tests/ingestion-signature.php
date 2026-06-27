<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$body = '{"schema_version": 1}';
$signature = shopSignalIngestionSignature(
    $body,
    '1800000000',
    'nonce-1234567890abcdef',
    str_repeat('a', 64)
);
$expected = '47fff34eebe2f3d5d5e270e988ccf355d663a0ef167db120519348f9d7994c51';
if (!hash_equals($expected, $signature)) {
    fwrite(STDERR, "Ingestion signature contract failed.\n");
    exit(1);
}
echo "Ingestion signature contract passed.\n";
