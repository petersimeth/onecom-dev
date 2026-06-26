<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequireAuth(true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function savedListsJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function ensureSavedListSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS saved_lists (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS saved_list_stores (
            list_id BIGINT UNSIGNED NOT NULL,
            store_id BIGINT UNSIGNED NOT NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (list_id, store_id),
            CONSTRAINT fk_saved_list_store_list
                FOREIGN KEY (list_id) REFERENCES saved_lists(id) ON DELETE CASCADE,
            CONSTRAINT fk_saved_list_store_store
                FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
            INDEX idx_saved_store_created (created_at),
            INDEX idx_saved_store_store (store_id)
        )
    ');
}

function defaultSavedListId(PDO $pdo): int
{
    $pdo->exec("
        INSERT INTO saved_lists (name, description)
        VALUES ('Prospects', 'Default stores saved from the explorer')
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");

    return (int) $pdo->query("SELECT id FROM saved_lists WHERE name = 'Prospects' LIMIT 1")->fetchColumn();
}

function savedListsPayload(PDO $pdo, ?int $selectedListId = null): array
{
    $defaultListId = defaultSavedListId($pdo);
    $selectedListId = $selectedListId ?: $defaultListId;

    $lists = $pdo->query('
        SELECT
            sl.id,
            sl.name,
            sl.description,
            COUNT(sls.store_id) AS store_count,
            DATE_FORMAT(sl.updated_at, \'%b %e, %Y\') AS updated_label
        FROM saved_lists sl
        LEFT JOIN saved_list_stores sls ON sls.list_id = sl.id
        GROUP BY sl.id, sl.name, sl.description, sl.updated_at
        ORDER BY sl.updated_at DESC, sl.id ASC
    ')->fetchAll();

    $selected = null;
    foreach ($lists as $list) {
        if ((int) $list['id'] === $selectedListId) {
            $selected = $list;
            break;
        }
    }
    $selected ??= $lists[0] ?? ['id' => $defaultListId, 'name' => 'Prospects', 'description' => '', 'store_count' => 0];
    $selectedListId = (int) $selected['id'];

    $repository = new StoreRepository($pdo);
    $stores = $repository->findStoresForSavedList($selectedListId, 100);

    return [
        'ok' => true,
        'lists' => array_map(
            static fn (array $list): array => [
                'id' => (int) $list['id'],
                'name' => (string) $list['name'],
                'description' => (string) ($list['description'] ?? ''),
                'store_count' => (int) $list['store_count'],
                'updated_label' => (string) ($list['updated_label'] ?? ''),
            ],
            $lists
        ),
        'selected_list' => [
            'id' => $selectedListId,
            'name' => (string) $selected['name'],
            'description' => (string) ($selected['description'] ?? ''),
            'store_count' => $repository->countStoresForSavedList($selectedListId),
        ],
        'stores' => $stores,
        'profiles' => $repository->findProfilesForStores($stores),
    ];
}

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }

    ensureSavedListSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = savedListsJsonInput();
        $action = (string) ($payload['action'] ?? '');
        $listId = (int) ($payload['list_id'] ?? 0);

        if ($action === 'create_list') {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('List name is required.');
            }

            $statement = $pdo->prepare('
                INSERT INTO saved_lists (name, description)
                VALUES (:name, :description)
                ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
            ');
            $statement->execute([
                'name' => mb_substr($name, 0, 160),
                'description' => mb_substr(trim((string) ($payload['description'] ?? '')), 0, 255),
            ]);

            $lookup = $pdo->prepare('SELECT id FROM saved_lists WHERE name = :name LIMIT 1');
            $lookup->execute(['name' => mb_substr($name, 0, 160)]);
            $listId = (int) $lookup->fetchColumn();
        }

        if ($action === 'add_store') {
            $listId = $listId > 0 ? $listId : defaultSavedListId($pdo);
            $storeId = (int) ($payload['store_id'] ?? 0);
            if ($storeId <= 0) {
                throw new InvalidArgumentException('store_id is required.');
            }

            $statement = $pdo->prepare('
                INSERT INTO saved_list_stores (list_id, store_id)
                VALUES (:list_id, :store_id)
                ON DUPLICATE KEY UPDATE created_at = created_at
            ');
            $statement->execute(['list_id' => $listId, 'store_id' => $storeId]);

            $pdo->prepare('UPDATE saved_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute(['id' => $listId]);
        }

        if ($action === 'remove_store') {
            $listId = $listId > 0 ? $listId : defaultSavedListId($pdo);
            $storeId = (int) ($payload['store_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM saved_list_stores WHERE list_id = :list_id AND store_id = :store_id');
            $statement->execute(['list_id' => $listId, 'store_id' => $storeId]);
        }

        echo json_encode(savedListsPayload($pdo, $listId ?: null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $listId = isset($_GET['list_id']) ? (int) $_GET['list_id'] : null;
    echo json_encode(savedListsPayload($pdo, $listId), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to load saved lists.',
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
