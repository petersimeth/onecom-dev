<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/IngestionService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    $respond(405, ['ok' => false, 'message' => 'POST required.']);
}
if (!shopSignalIngestionEnabled()) {
    $respond(503, ['ok' => false, 'message' => 'Crawler ingestion is not enabled.']);
}
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    $respond(415, ['ok' => false, 'message' => 'Content-Type must be application/json.']);
}
$config = shopSignalConfig();
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost');
if (($config['crawler_ingest_require_https'] ?? true) && !$isLocal && !shopSignalIsHttpsRequest()) {
    $respond(400, ['ok' => false, 'message' => 'HTTPS is required.']);
}

$maxBytes = max(1024, min(10 * 1024 * 1024, (int) ($config['crawler_ingest_max_bytes'] ?? 2097152)));
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBytes) {
    $respond(413, ['ok' => false, 'message' => 'Request body is too large.']);
}
$body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if (!is_string($body) || $body === '' || strlen($body) > $maxBytes) {
    $respond(strlen((string) $body) > $maxBytes ? 413 : 400, ['ok' => false, 'message' => 'Invalid request body.']);
}

try {
    $auth = shopSignalVerifyIngestionRequest($body);
} catch (UnexpectedValueException) {
    $respond(401, ['ok' => false, 'message' => 'Authentication failed.']);
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
    $respond(200, ['ok' => true, 'schema_version' => 1, 'server_time' => gmdate(DATE_ATOM), 'summary' => $summary]);
} catch (JsonException | InvalidArgumentException $exception) {
    $respond(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (RuntimeException $exception) {
    $status = $exception->getCode() === 409 ? 409 : 500;
    if ($status === 500) {
        error_log('ShopSignal crawler ingestion: ' . $exception->getMessage());
    }
    $respond($status, ['ok' => false, 'message' => $status === 409 ? $exception->getMessage() : 'Ingestion failed.']);
} catch (Throwable $exception) {
    error_log('ShopSignal crawler ingestion: ' . $exception->getMessage());
    $respond(500, ['ok' => false, 'message' => 'Ingestion failed.']);
}
