<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

shopSignalRequirePro(true);

function exportFiltersFromQuery(): array
{
    return [
        'category' => trim((string) ($_GET['category'] ?? '')),
        'country' => trim((string) ($_GET['country'] ?? '')),
        'min_revenue' => max(0, (int) ($_GET['min_revenue'] ?? 0)),
        'min_growth' => max(0, (float) ($_GET['min_growth'] ?? 0)),
        'technology' => trim((string) ($_GET['technology'] ?? '')),
        'product_category' => trim((string) ($_GET['product_category'] ?? '')),
    ];
}

function exportFilename(string $scope): string
{
    return 'shopsignal-' . preg_replace('/[^a-z0-9-]+/i', '-', $scope) . '-' . date('Y-m-d-His') . '.csv';
}

function streamCsvHeaders(string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
}

function csvOutput(): mixed
{
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open CSV output stream.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    return $output;
}

function exportHeaderRow(): array
{
    return [
        'Store ID',
        'Store',
        'Domain',
        'Category',
        'Country',
        'Estimated Monthly Revenue',
        'Monthly Traffic',
        'Growth %',
        'Growth Signal',
        'Founded',
        'Product Count',
        'Average Price',
        'Public Email',
        'Public Phone',
        'Technologies',
        'Store URL',
    ];
}

function streamStoreRows(PDOStatement $statement, mixed $output): int
{
    $count = 0;
    while ($row = $statement->fetch()) {
        fputcsv($output, [
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['domain'],
            (string) $row['category'],
            (string) ($row['country'] ?? ''),
            (float) $row['estimated_monthly_revenue'],
            (int) $row['monthly_traffic'],
            (float) $row['growth_percent'],
            (string) $row['growth_signal'],
            (string) $row['founded_year'],
            (int) $row['product_count'],
            (float) ($row['average_price'] ?? 0),
            (string) ($row['public_email'] ?? ''),
            (string) ($row['public_phone'] ?? ''),
            (string) ($row['technologies'] ?? ''),
            'https://' . (string) $row['domain'],
        ]);
        $count++;
    }

    return $count;
}

try {
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('Database is not configured.');
    }

    $scope = trim((string) ($_GET['scope'] ?? 'stores'));
    $sort = (string) ($_GET['sort'] ?? 'growth');
    $limit = max(1, min(5000, (int) ($_GET['limit'] ?? 5000)));
    $repository = new StoreRepository($pdo);
    $sortColumns = [
        'signal' => 's.growth_percent DESC',
        'growth' => 's.growth_percent DESC',
        'revenue' => 's.estimated_monthly_revenue DESC',
        'traffic' => 's.monthly_traffic DESC',
        'newest' => 's.founded_year DESC',
        'products' => 's.product_count DESC',
    ];
    $orderBy = $sortColumns[$sort] ?? $sortColumns['growth'];

    streamCsvHeaders(exportFilename($scope));
    $output = csvOutput();
    fputcsv($output, exportHeaderRow());

    if ($scope === 'list') {
        $listId = max(0, (int) ($_GET['list_id'] ?? 0));
        if ($listId <= 0) {
            throw new InvalidArgumentException('list_id is required for saved list export.');
        }

        $statement = $pdo->prepare('
            SELECT
                s.id,
                s.name,
                s.domain,
                s.category,
                s.country,
                s.estimated_monthly_revenue,
                s.monthly_traffic,
                s.growth_percent,
                s.growth_signal,
                s.founded_year,
                s.product_count,
                s.average_price,
                s.public_email,
                s.public_phone,
                COALESCE(tech.technologies, \'\') AS technologies
            FROM saved_list_stores sls
            INNER JOIN stores s ON s.id = sls.store_id
            LEFT JOIN (
                SELECT store_id, GROUP_CONCAT(technology_name ORDER BY technology_name SEPARATOR \', \') AS technologies
                FROM store_technologies
                GROUP BY store_id
            ) tech ON tech.store_id = s.id
            WHERE sls.list_id = :list_id
            ORDER BY sls.created_at DESC
            LIMIT :limit
        ');
        $statement->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        streamStoreRows($statement, $output);
        fclose($output);
        exit;
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    [$whereSql, $params] = $repository->buildStoreFilterSql($search, exportFiltersFromQuery());
    $statement = $pdo->prepare('
        SELECT
            s.id,
            s.name,
            s.domain,
            s.category,
            s.country,
            s.estimated_monthly_revenue,
            s.monthly_traffic,
            s.growth_percent,
            s.growth_signal,
            s.founded_year,
            s.product_count,
            s.average_price,
            s.public_email,
            s.public_phone,
            COALESCE(tech.technologies, \'\') AS technologies
        FROM stores s
        LEFT JOIN (
            SELECT store_id, GROUP_CONCAT(technology_name ORDER BY technology_name SEPARATOR \', \') AS technologies
            FROM store_technologies
            GROUP BY store_id
        ) tech ON tech.store_id = s.id
        WHERE ' . $whereSql . '
        ORDER BY ' . $orderBy . '
        LIMIT :limit
    ');
    $repository->bindStoreFilterParams($statement, $params);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    streamStoreRows($statement, $output);
    fclose($output);
} catch (Throwable $exception) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    $config = isset($config) && is_array($config) ? $config : shopSignalConfig();
    $payload = [
        'ok' => false,
        'message' => 'Unable to export CSV.',
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
