<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$isPreview = !shopSignalHasActiveSession();
$isFree = shopSignalCurrentPlan() === 'free';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);

    if ($pdo === null) {
        $data = loadShopSignalData();
        if ($isPreview) {
            $data = shopSignalPreviewData($data);
        } elseif ($isFree) {
            $data = shopSignalFreeData($data);
        }
        echo json_encode(
            [
                'data' => $data['stores'],
                'profiles' => $data['profiles'],
                'meta' => [
                    'source' => $data['source'],
                    'stats' => $data['stats'],
                    'pagination' => [
                        'limit' => count($data['stores']),
                        'offset' => 0,
                        'returned' => count($data['stores']),
                        'total' => count($data['stores']),
                        'has_more' => false,
                    ],
                ],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    $sort = (string) ($_GET['sort'] ?? 'growth');
    $limit = max(1, min(250, (int) ($_GET['limit'] ?? 100)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $filters = [
        'category' => trim((string) ($_GET['category'] ?? '')),
        'country' => trim((string) ($_GET['country'] ?? '')),
        'min_revenue' => max(0, (int) ($_GET['min_revenue'] ?? 0)),
        'min_growth' => max(0, (float) ($_GET['min_growth'] ?? 0)),
        'technology' => trim((string) ($_GET['technology'] ?? '')),
        'product_category' => trim((string) ($_GET['product_category'] ?? '')),
    ];
    if ($isPreview) {
        $sort = in_array($sort, ['newest', 'traffic', 'revenue', 'products'], true) ? 'growth' : $sort;
        $filters['min_revenue'] = 0;
        $filters['min_growth'] = 0;
        $filters['technology'] = '';
        $filters['product_category'] = '';
    } elseif ($isFree) {
        $sort = in_array($sort, ['newest', 'traffic', 'revenue', 'products'], true) ? 'growth' : $sort;
        $filters['min_revenue'] = 0;
        $filters['min_growth'] = 0;
        $filters['technology'] = '';
        $filters['product_category'] = '';
    }

    $repository = new StoreRepository($pdo);
    $stores = $repository->findStores(
        search: $search,
        sort: $sort,
        limit: $limit,
        offset: $offset,
        filters: $filters
    );
    $total = $repository->countStores($search, $filters);
    if ($isPreview) {
        $stores = shopSignalPreviewStores($stores);
    } elseif ($isFree) {
        $stores = shopSignalFreeStores($stores);
    }
    $stats = $repository->getDashboardStats();
    if ($isPreview) {
        $stats = shopSignalPreviewData(['stores' => $stores, 'profiles' => [], 'stats' => ['matching_stores' => $total]])['stats'];
    } elseif ($isFree) {
        $stats = shopSignalFreeData(['stores' => $stores, 'profiles' => [], 'stats' => ['matching_stores' => $total]])['stats'];
    }

    echo json_encode(
        [
            'data' => $stores,
            'profiles' => ($isPreview || $isFree) ? [] : $repository->findProfilesForStores($stores),
            'meta' => [
                'source' => 'database',
                'stats' => $stats,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'returned' => count($stores),
                    'total' => $total,
                    'has_more' => ($offset + count($stores)) < $total,
                ],
            ],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $debug = (bool) ($config['db_debug'] ?? false);

    http_response_code(500);
    $payload = [
        'data' => [],
        'profiles' => [],
        'meta' => [
            'source' => 'error',
            'message' => 'Unable to load stores.',
        ],
    ];

    if ($debug) {
        $payload['meta']['diagnostic'] = [
            'type' => get_class($exception),
            'code' => (string) $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
