<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequireAuth(true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function segmentsJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function ensureSegmentsSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS saved_segments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL UNIQUE,
            search_query VARCHAR(255) DEFAULT \'\',
            sort_key VARCHAR(40) DEFAULT \'growth\',
            filters_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ');
}

function savedSegmentsPayload(PDO $pdo): array
{
    $rows = $pdo->query('
        SELECT id, name, search_query, sort_key, filters_json, DATE_FORMAT(updated_at, \'%b %e, %Y\') AS updated_label
        FROM saved_segments
        ORDER BY updated_at DESC, id DESC
        LIMIT 20
    ')->fetchAll();

    return [
        'ok' => true,
        'segments' => array_map(
            static function (array $row): array {
                $filters = json_decode((string) ($row['filters_json'] ?? '{}'), true);
                return [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'search' => (string) ($row['search_query'] ?? ''),
                    'sort' => (string) ($row['sort_key'] ?? 'growth'),
                    'filters' => is_array($filters) ? $filters : [],
                    'updated_label' => (string) ($row['updated_label'] ?? ''),
                ];
            },
            $rows
        ),
    ];
}

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }

    ensureSegmentsSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = segmentsJsonInput();
        $action = (string) ($payload['action'] ?? '');

        if ($action === 'create_segment') {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Segment name is required.');
            }

            $filters = $payload['filters'] ?? [];
            $filters = is_array($filters) ? $filters : [];
            $statement = $pdo->prepare('
                INSERT INTO saved_segments (name, search_query, sort_key, filters_json)
                VALUES (:name, :search_query, :sort_key, :filters_json)
                ON DUPLICATE KEY UPDATE
                    search_query = VALUES(search_query),
                    sort_key = VALUES(sort_key),
                    filters_json = VALUES(filters_json),
                    updated_at = CURRENT_TIMESTAMP
            ');
            $statement->execute([
                'name' => mb_substr($name, 0, 160),
                'search_query' => mb_substr(trim((string) ($payload['search'] ?? '')), 0, 255),
                'sort_key' => mb_substr(trim((string) ($payload['sort'] ?? 'growth')), 0, 40),
                'filters_json' => json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        if ($action === 'delete_segment') {
            $segmentId = (int) ($payload['segment_id'] ?? 0);
            if ($segmentId <= 0) {
                throw new InvalidArgumentException('segment_id is required.');
            }

            $statement = $pdo->prepare('DELETE FROM saved_segments WHERE id = :id');
            $statement->execute(['id' => $segmentId]);
        }
    }

    echo json_encode(savedSegmentsPayload($pdo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to load saved views.',
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
