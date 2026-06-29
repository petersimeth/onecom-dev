<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/IngestionService.php';

JsonApi::headers(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    JsonApi::respond(['ok' => false, 'message' => 'POST required.'], 405);
}
if (!shopSignalIngestionEnabled()) {
    JsonApi::respond(['ok' => false, 'message' => 'Crawler ingestion is not enabled.'], 503);
}
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    JsonApi::respond(['ok' => false, 'message' => 'Content-Type must be application/json.'], 415);
}
$config = shopSignalConfig();
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost');
if (($config['crawler_ingest_require_https'] ?? true) && !$isLocal && !shopSignalIsHttpsRequest()) {
    JsonApi::respond(['ok' => false, 'message' => 'HTTPS is required.'], 400);
}

$maxBytes = max(1024, min(10 * 1024 * 1024, (int) ($config['crawler_ingest_max_bytes'] ?? 2097152)));
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBytes) {
    JsonApi::respond(['ok' => false, 'message' => 'Request body is too large.'], 413);
}
$body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if (!is_string($body) || $body === '' || strlen($body) > $maxBytes) {
    JsonApi::respond(['ok' => false, 'message' => 'Invalid request body.'], strlen((string) $body) > $maxBytes ? 413 : 400);
}

try {
    $auth = shopSignalVerifyIngestionRequest($body);
} catch (UnexpectedValueException) {
    JsonApi::respond(['ok' => false, 'message' => 'Authentication failed.'], 401);
}

try {
    $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON body must be an object.');
    }
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is unavailable.');
    }
    $service = new IngestionService($pdo, $config);
    $service->ensureSchema();
    $service->consumeNonce((string) $auth['key_id'], (string) $auth['nonce'], (int) $auth['expires_at']);
    $summary = $service->ingest($payload, (string) $auth['body_sha256'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    JsonApi::respond(['ok' => true, 'schema_version' => 1, 'server_time' => gmdate(DATE_ATOM), 'summary' => $summary]);
} catch (JsonException | InvalidArgumentException $exception) {
    JsonApi::respond(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (RuntimeException $exception) {
    $status = $exception->getCode() === 409 ? 409 : 500;
    if ($status === 500) {
        error_log('ShopSignal crawler ingestion: ' . $exception->getMessage());
    }
    JsonApi::respond(['ok' => false, 'message' => $status === 409 ? $exception->getMessage() : 'Ingestion failed.'], $status);
} catch (Throwable $exception) {
    error_log('ShopSignal crawler ingestion: ' . $exception->getMessage());
    JsonApi::respond(['ok' => false, 'message' => 'Ingestion failed.'], 500);
}
