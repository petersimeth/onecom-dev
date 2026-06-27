<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/IngestionService.php';

shopSignalRequireAuth();

function adminCsvTemplates(): array
{
    return [
        'stores' => [
            'label' => 'Stores',
            'required' => 'domain',
            'headers' => [
                'name', 'domain', 'category', 'country', 'headquarters',
                'estimated_monthly_revenue', 'monthly_traffic', 'monthly_orders',
                'growth_percent', 'growth_signal', 'product_count', 'average_price',
                'founded_year', 'employee_range', 'public_email', 'public_phone',
                'store_language', 'currency', 'instagram_followers', 'tiktok_followers',
                'facebook_followers',
            ],
            'example' => [
                'Example Store', 'examplestore.com', 'Beauty', 'United States', 'Austin, TX',
                '125000', '48000', '1100', '12.5', 'High', '240', '54.90',
                '2020', '11-50', 'hello@examplestore.com', '+1 555 0100',
                'English', 'USD', '25000', '8000', '12000',
            ],
        ],
        'technologies' => [
            'label' => 'Technologies',
            'required' => 'domain, technology_name',
            'headers' => ['domain', 'technology_name', 'category', 'short_code', 'detected_at', 'last_seen_at', 'monthly_cost'],
            'example' => ['examplestore.com', 'Klaviyo', 'Email marketing', 'kl', '2026-01-01', '2026-06-26', '149'],
        ],
        'products' => [
            'label' => 'Products',
            'required' => 'domain, name',
            'headers' => ['domain', 'name', 'category', 'price', 'currency_symbol', 'product_url', 'is_top_product', 'first_seen_at', 'last_seen_at'],
            'example' => ['examplestore.com', 'Hydrating Serum', 'Skincare', '54.90', '$', 'https://examplestore.com/products/hydrating-serum', '1', '2026-01-01', '2026-06-26'],
        ],
        'signals' => [
            'label' => 'Signals',
            'required' => 'domain, title',
            'headers' => ['domain', 'signal_type', 'title', 'description', 'occurred_at', 'occurred_label'],
            'example' => ['examplestore.com', 'technology', 'New app detected', 'Klaviyo was detected on the storefront.', '2026-06-26 10:00:00', 'today'],
        ],
    ];
}

function adminCsvTemplateHeaders(string $type = 'stores'): array
{
    $templates = adminCsvTemplates();
    return $templates[$type]['headers'] ?? $templates['stores']['headers'];
}

function adminDownloadCsvTemplate(string $type): void
{
    $templates = adminCsvTemplates();
    $template = $templates[$type] ?? $templates['stores'];
    $safeType = preg_replace('/[^a-z0-9-]+/i', '-', isset($templates[$type]) ? $type : 'stores') ?: 'stores';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shopsignal-' . $safeType . '-import-template.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $template['headers']);
    fputcsv($output, $template['example']);
    fclose($output);
    exit;
}

function adminNormalizeHeader(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header) ?? '', '_'));
}

function adminValue(array $row, string $key, string $default = ''): string
{
    $value = trim((string) ($row[$key] ?? ''));
    return $value === '' ? $default : $value;
}

function adminIntValue(array $row, string $key): int
{
    $value = preg_replace('/[^0-9-]/', '', adminValue($row, $key, '0')) ?: '0';
    return max(0, (int) $value);
}

function adminFloatValue(array $row, string $key): float
{
    $value = preg_replace('/[^0-9.\-]/', '', adminValue($row, $key, '0')) ?: '0';
    return max(0, (float) $value);
}

function adminBoolValue(array $row, string $key): int
{
    $value = strtolower(adminValue($row, $key, '0'));
    return in_array($value, ['1', 'true', 'yes', 'on', 'top'], true) ? 1 : 0;
}

function adminDateValue(array $row, string $key): ?string
{
    $value = adminValue($row, $key);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function adminDateTimeValue(array $row, string $key): string
{
    $value = adminValue($row, $key);
    $timestamp = $value !== '' ? strtotime($value) : false;
    return date('Y-m-d H:i:s', $timestamp ?: time());
}

function adminNormalizeDomain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
    $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
    return trim($domain);
}

function adminGrowthSignal(string $value, float $growth): string
{
    $value = ucfirst(strtolower(trim($value)));
    if (in_array($value, ['Low', 'Medium', 'High'], true)) {
        return $value;
    }

    if ($growth >= 10) {
        return 'High';
    }
    if ($growth >= 4) {
        return 'Medium';
    }
    return 'Low';
}

function adminEnsureImportBatchSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS import_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            import_type VARCHAR(40) NOT NULL,
            filename VARCHAR(255) NULL,
            imported_by VARCHAR(160) NULL,
            processed_count INT UNSIGNED DEFAULT 0,
            created_count INT UNSIGNED DEFAULT 0,
            updated_count INT UNSIGNED DEFAULT 0,
            skipped_count INT UNSIGNED DEFAULT 0,
            errors_json TEXT NULL,
            rolled_back_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_import_batch_created (created_at),
            INDEX idx_import_batch_type (import_type)
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS import_batch_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id BIGINT UNSIGNED NOT NULL,
            table_name VARCHAR(80) NOT NULL,
            row_id BIGINT UNSIGNED NOT NULL,
            row_label VARCHAR(255) NULL,
            action_type VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_import_item_batch (batch_id),
            INDEX idx_import_item_row (table_name, row_id),
            CONSTRAINT fk_import_item_batch
                FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
        )
    ');
}

function adminCreateImportBatch(PDO $pdo, string $type, string $filename): int
{
    adminEnsureImportBatchSchema($pdo);
    $statement = $pdo->prepare('
        INSERT INTO import_batches (import_type, filename, imported_by)
        VALUES (:import_type, :filename, :imported_by)
    ');
    $statement->execute([
        'import_type' => $type,
        'filename' => mb_substr($filename, 0, 255),
        'imported_by' => shopSignalAuthUser(),
    ]);

    return (int) $pdo->lastInsertId();
}

function adminRecordImportItem(PDO $pdo, int $batchId, string $tableName, int $rowId, string $label, string $action): void
{
    static $statement = null;
    if (!$statement instanceof PDOStatement) {
        $statement = $pdo->prepare('
            INSERT INTO import_batch_items (batch_id, table_name, row_id, row_label, action_type)
            VALUES (:batch_id, :table_name, :row_id, :row_label, :action_type)
        ');
    }

    $statement->execute([
        'batch_id' => $batchId,
        'table_name' => $tableName,
        'row_id' => $rowId,
        'row_label' => mb_substr($label, 0, 255),
        'action_type' => $action,
    ]);
}

function adminFinalizeImportBatch(PDO $pdo, int $batchId, array $summary): void
{
    $statement = $pdo->prepare('
        UPDATE import_batches
        SET processed_count = :processed,
            created_count = :created,
            updated_count = :updated,
            skipped_count = :skipped,
            errors_json = :errors_json
        WHERE id = :id
    ');
    $statement->execute([
        'processed' => (int) $summary['processed'],
        'created' => (int) $summary['created'],
        'updated' => (int) $summary['updated'],
        'skipped' => (int) $summary['skipped'],
        'errors_json' => json_encode(array_slice($summary['errors'], 0, 25), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'id' => $batchId,
    ]);
}

function adminDeleteImportBatch(PDO $pdo, int $batchId): array
{
    adminEnsureImportBatchSchema($pdo);

    $batch = $pdo->prepare('SELECT id, rolled_back_at FROM import_batches WHERE id = :id LIMIT 1');
    $batch->execute(['id' => $batchId]);
    $batchRow = $batch->fetch();
    if (!$batchRow) {
        throw new RuntimeException('Import batch not found.');
    }
    if ($batchRow['rolled_back_at'] !== null) {
        throw new RuntimeException('This import batch was already rolled back.');
    }

    $items = $pdo->prepare('
        SELECT table_name, row_id
        FROM import_batch_items
        WHERE batch_id = :batch_id AND action_type = \'created\'
        ORDER BY FIELD(table_name, \'store_signals\', \'products\', \'store_technologies\', \'stores\')
    ');
    $items->execute(['batch_id' => $batchId]);

    $deleted = 0;
    $deleteStatements = [
        'store_signals' => $pdo->prepare('DELETE FROM store_signals WHERE id = :id'),
        'products' => $pdo->prepare('DELETE FROM products WHERE id = :id'),
        'store_technologies' => $pdo->prepare('DELETE FROM store_technologies WHERE id = :id'),
        'stores' => $pdo->prepare('DELETE FROM stores WHERE id = :id'),
    ];

    $pdo->beginTransaction();
    try {
        foreach ($items->fetchAll() as $item) {
            $table = (string) $item['table_name'];
            if (!isset($deleteStatements[$table])) {
                continue;
            }
            $deleteStatements[$table]->execute(['id' => (int) $item['row_id']]);
            $deleted += $deleteStatements[$table]->rowCount();
        }

        $pdo->prepare('UPDATE import_batches SET rolled_back_at = NOW() WHERE id = :id')
            ->execute(['id' => $batchId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return ['deleted' => $deleted];
}

function adminRecentImportBatches(PDO $pdo): array
{
    adminEnsureImportBatchSchema($pdo);
    return $pdo->query('
        SELECT
            id,
            import_type,
            filename,
            imported_by,
            processed_count,
            created_count,
            updated_count,
            skipped_count,
            rolled_back_at,
            DATE_FORMAT(created_at, \'%b %e, %Y %H:%i\') AS created_label
        FROM import_batches
        ORDER BY id DESC
        LIMIT 10
    ')->fetchAll();
}

function adminDashboardUsers(PDO $pdo): array
{
    shopSignalEnsureUserSchema($pdo);
    return $pdo->query('
        SELECT
            id,
            name,
            email,
            role,
            plan,
            status,
            stripe_customer_id,
            subscription_status,
            subscription_current_period_end,
            subscription_cancel_at_period_end,
            email_verified_at,
            DATE_FORMAT(created_at, \'%b %e, %Y\') AS created_label,
            DATE_FORMAT(last_login_at, \'%b %e, %Y %H:%i\') AS last_login_label
        FROM users
        ORDER BY role = \'admin\' DESC, id ASC
        LIMIT 100
    ')->fetchAll();
}

function adminSubscriptionCounts(PDO $pdo): array
{
    shopSignalEnsureUserSchema($pdo);
    $row = $pdo->query('
        SELECT
            SUM(subscription_status IN (\'active\', \'trialing\')) AS active_count,
            SUM(subscription_status = \'past_due\') AS past_due_count,
            SUM(subscription_cancel_at_period_end = 1) AS cancelling_count
        FROM users
    ')->fetch();
    return is_array($row) ? $row : ['active_count' => 0, 'past_due_count' => 0, 'cancelling_count' => 0];
}

function adminPendingRegistrations(PDO $pdo): array
{
    shopSignalEnsurePendingRegistrationSchema($pdo);
    return $pdo->query('
        SELECT id, name, email, DATE_FORMAT(created_at, \'%b %e, %Y %H:%i\') AS created_label
        FROM pending_registrations
        ORDER BY id DESC
        LIMIT 50
    ')->fetchAll();
}

function adminOpenCsv(string $path, array $requiredHeaders): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Unable to read uploaded CSV.');
    }

    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
        throw new RuntimeException('CSV is empty or missing a header row.');
    }

    $headers = array_map(static fn (string $header): string => adminNormalizeHeader($header), $headers);
    foreach ($requiredHeaders as $requiredHeader) {
        if (!in_array($requiredHeader, $headers, true)) {
            throw new RuntimeException('Missing required CSV column: ' . $requiredHeader);
        }
    }

    return [$handle, $headers];
}

function adminCsvSummary(): array
{
    return [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];
}

function adminCsvRow(array $headers, array $values): array
{
    $row = [];
    foreach ($headers as $index => $header) {
        $row[$header] = $values[$index] ?? '';
    }
    return $row;
}

function adminFindStoreIdByDomain(PDO $pdo, string $domain): int
{
    static $statement = null;
    if (!$statement instanceof PDOStatement) {
        $statement = $pdo->prepare('SELECT id FROM stores WHERE domain = :domain LIMIT 1');
    }

    $statement->execute(['domain' => $domain]);
    return (int) ($statement->fetchColumn() ?: 0);
}

function adminImportStores(PDO $pdo, string $path, int $batchId): array
{
    [$handle, $headers] = adminOpenCsv($path, ['domain']);

    $statement = $pdo->prepare('
        INSERT INTO stores (
            name,
            domain,
            category,
            growth_signal,
            growth_percent,
            estimated_monthly_revenue,
            monthly_traffic,
            monthly_orders,
            average_price,
            product_count,
            founded_year,
            headquarters,
            country,
            employee_range,
            public_email,
            public_phone,
            store_language,
            currency,
            social_total,
            instagram_followers,
            tiktok_followers,
            facebook_followers,
            logo_letter,
            logo_class
        ) VALUES (
            :name,
            :domain,
            :category,
            :growth_signal,
            :growth_percent,
            :estimated_monthly_revenue,
            :monthly_traffic,
            :monthly_orders,
            :average_price,
            :product_count,
            :founded_year,
            :headquarters,
            :country,
            :employee_range,
            :public_email,
            :public_phone,
            :store_language,
            :currency,
            :social_total,
            :instagram_followers,
            :tiktok_followers,
            :facebook_followers,
            :logo_letter,
            :logo_class
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            category = VALUES(category),
            growth_signal = VALUES(growth_signal),
            growth_percent = VALUES(growth_percent),
            estimated_monthly_revenue = VALUES(estimated_monthly_revenue),
            monthly_traffic = VALUES(monthly_traffic),
            monthly_orders = VALUES(monthly_orders),
            average_price = VALUES(average_price),
            product_count = VALUES(product_count),
            founded_year = VALUES(founded_year),
            headquarters = VALUES(headquarters),
            country = VALUES(country),
            employee_range = VALUES(employee_range),
            public_email = VALUES(public_email),
            public_phone = VALUES(public_phone),
            store_language = VALUES(store_language),
            currency = VALUES(currency),
            social_total = VALUES(social_total),
            instagram_followers = VALUES(instagram_followers),
            tiktok_followers = VALUES(tiktok_followers),
            facebook_followers = VALUES(facebook_followers),
            logo_letter = VALUES(logo_letter),
            logo_class = VALUES(logo_class),
            updated_at = CURRENT_TIMESTAMP
    ');

    $summary = adminCsvSummary();

    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = adminCsvRow($headers, $values);

            $domain = adminNormalizeDomain(adminValue($row, 'domain'));
            if ($domain === '') {
                $summary['skipped']++;
                $summary['errors'][] = 'Line ' . $line . ': missing domain.';
                continue;
            }

            $growth = adminFloatValue($row, 'growth_percent');
            $instagram = adminIntValue($row, 'instagram_followers');
            $tiktok = adminIntValue($row, 'tiktok_followers');
            $facebook = adminIntValue($row, 'facebook_followers');
            $name = adminValue($row, 'name', ucfirst((string) preg_replace('/\..*$/', '', $domain)));
            $logoLetter = mb_strtoupper(mb_substr($name, 0, 1));
            $existingStoreId = adminFindStoreIdByDomain($pdo, $domain);

            $statement->execute([
                'name' => mb_substr($name, 0, 180),
                'domain' => mb_substr($domain, 0, 255),
                'category' => mb_substr(adminValue($row, 'category', 'Uncategorized'), 0, 120),
                'growth_signal' => adminGrowthSignal(adminValue($row, 'growth_signal'), $growth),
                'growth_percent' => $growth,
                'estimated_monthly_revenue' => adminFloatValue($row, 'estimated_monthly_revenue'),
                'monthly_traffic' => adminIntValue($row, 'monthly_traffic'),
                'monthly_orders' => adminIntValue($row, 'monthly_orders'),
                'average_price' => adminFloatValue($row, 'average_price'),
                'product_count' => adminIntValue($row, 'product_count'),
                'founded_year' => adminIntValue($row, 'founded_year') ?: null,
                'headquarters' => mb_substr(adminValue($row, 'headquarters'), 0, 180),
                'country' => mb_substr(adminValue($row, 'country'), 0, 100),
                'employee_range' => mb_substr(adminValue($row, 'employee_range'), 0, 60),
                'public_email' => mb_substr(adminValue($row, 'public_email'), 0, 255),
                'public_phone' => mb_substr(adminValue($row, 'public_phone'), 0, 80),
                'store_language' => mb_substr(adminValue($row, 'store_language', 'English'), 0, 60),
                'currency' => mb_substr(adminValue($row, 'currency', 'USD'), 0, 30),
                'social_total' => $instagram + $tiktok + $facebook,
                'instagram_followers' => $instagram,
                'tiktok_followers' => $tiktok,
                'facebook_followers' => $facebook,
                'logo_letter' => $logoLetter,
                'logo_class' => 'logo-allbirds',
            ]);

            $summary['processed']++;
            $storeId = adminFindStoreIdByDomain($pdo, $domain);
            if ($existingStoreId <= 0) {
                $summary['created']++;
                adminRecordImportItem($pdo, $batchId, 'stores', $storeId, $domain, 'created');
            } else {
                $summary['updated']++;
                adminRecordImportItem($pdo, $batchId, 'stores', $storeId, $domain, 'updated');
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    } finally {
        fclose($handle);
    }

    return $summary;
}

function adminImportTechnologies(PDO $pdo, string $path, int $batchId): array
{
    [$handle, $headers] = adminOpenCsv($path, ['domain', 'technology_name']);
    $summary = adminCsvSummary();
    $exists = $pdo->prepare('SELECT id FROM store_technologies WHERE store_id = :store_id AND technology_name = :technology_name LIMIT 1');
    $delete = $pdo->prepare('DELETE FROM store_technologies WHERE store_id = :store_id AND technology_name = :technology_name');
    $insert = $pdo->prepare('
        INSERT INTO store_technologies (store_id, technology_name, category, short_code, detected_at, last_seen_at, monthly_cost)
        VALUES (:store_id, :technology_name, :category, :short_code, :detected_at, :last_seen_at, :monthly_cost)
    ');

    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = adminCsvRow($headers, $values);
            $domain = adminNormalizeDomain(adminValue($row, 'domain'));
            $storeId = $domain !== '' ? adminFindStoreIdByDomain($pdo, $domain) : 0;
            $technology = adminValue($row, 'technology_name');
            if ($storeId <= 0 || $technology === '') {
                $summary['skipped']++;
                $summary['errors'][] = 'Line ' . $line . ': missing store/domain or technology_name.';
                continue;
            }

            $exists->execute(['store_id' => $storeId, 'technology_name' => $technology]);
            $wasExisting = (bool) $exists->fetchColumn();
            $delete->execute(['store_id' => $storeId, 'technology_name' => $technology]);
            $insert->execute([
                'store_id' => $storeId,
                'technology_name' => mb_substr($technology, 0, 140),
                'category' => mb_substr(adminValue($row, 'category', 'Other'), 0, 120),
                'short_code' => mb_substr(adminValue($row, 'short_code', mb_substr($technology, 0, 2)), 0, 10),
                'detected_at' => adminDateValue($row, 'detected_at'),
                'last_seen_at' => adminDateValue($row, 'last_seen_at'),
                'monthly_cost' => adminFloatValue($row, 'monthly_cost'),
            ]);
            $rowId = (int) $pdo->lastInsertId();

            $summary['processed']++;
            $summary[$wasExisting ? 'updated' : 'created']++;
            adminRecordImportItem($pdo, $batchId, 'store_technologies', $rowId, $domain . ' · ' . $technology, $wasExisting ? 'updated' : 'created');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    } finally {
        fclose($handle);
    }

    return $summary;
}

function adminImportProducts(PDO $pdo, string $path, int $batchId): array
{
    [$handle, $headers] = adminOpenCsv($path, ['domain', 'name']);
    $summary = adminCsvSummary();
    $exists = $pdo->prepare('SELECT id FROM products WHERE store_id = :store_id AND name = :name LIMIT 1');
    $delete = $pdo->prepare('DELETE FROM products WHERE store_id = :store_id AND name = :name');
    $insert = $pdo->prepare('
        INSERT INTO products (store_id, name, category, price, currency_symbol, product_url, is_top_product, first_seen_at, last_seen_at)
        VALUES (:store_id, :name, :category, :price, :currency_symbol, :product_url, :is_top_product, :first_seen_at, :last_seen_at)
    ');

    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = adminCsvRow($headers, $values);
            $domain = adminNormalizeDomain(adminValue($row, 'domain'));
            $storeId = $domain !== '' ? adminFindStoreIdByDomain($pdo, $domain) : 0;
            $name = adminValue($row, 'name');
            if ($storeId <= 0 || $name === '') {
                $summary['skipped']++;
                $summary['errors'][] = 'Line ' . $line . ': missing store/domain or product name.';
                continue;
            }

            $exists->execute(['store_id' => $storeId, 'name' => $name]);
            $wasExisting = (bool) $exists->fetchColumn();
            $delete->execute(['store_id' => $storeId, 'name' => $name]);
            $insert->execute([
                'store_id' => $storeId,
                'name' => mb_substr($name, 0, 255),
                'category' => mb_substr(adminValue($row, 'category', 'Uncategorized'), 0, 120),
                'price' => adminFloatValue($row, 'price'),
                'currency_symbol' => mb_substr(adminValue($row, 'currency_symbol', '$'), 0, 4),
                'product_url' => mb_substr(adminValue($row, 'product_url'), 0, 500),
                'is_top_product' => adminBoolValue($row, 'is_top_product'),
                'first_seen_at' => adminDateValue($row, 'first_seen_at'),
                'last_seen_at' => adminDateValue($row, 'last_seen_at'),
            ]);
            $rowId = (int) $pdo->lastInsertId();

            $summary['processed']++;
            $summary[$wasExisting ? 'updated' : 'created']++;
            adminRecordImportItem($pdo, $batchId, 'products', $rowId, $domain . ' · ' . $name, $wasExisting ? 'updated' : 'created');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    } finally {
        fclose($handle);
    }

    return $summary;
}

function adminImportSignals(PDO $pdo, string $path, int $batchId): array
{
    [$handle, $headers] = adminOpenCsv($path, ['domain', 'title']);
    $summary = adminCsvSummary();
    $exists = $pdo->prepare('SELECT id FROM store_signals WHERE store_id = :store_id AND title = :title AND occurred_at = :occurred_at LIMIT 1');
    $delete = $pdo->prepare('DELETE FROM store_signals WHERE store_id = :store_id AND title = :title AND occurred_at = :occurred_at');
    $insert = $pdo->prepare('
        INSERT INTO store_signals (store_id, signal_type, title, description, occurred_at, occurred_label)
        VALUES (:store_id, :signal_type, :title, :description, :occurred_at, :occurred_label)
    ');

    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = adminCsvRow($headers, $values);
            $domain = adminNormalizeDomain(adminValue($row, 'domain'));
            $storeId = $domain !== '' ? adminFindStoreIdByDomain($pdo, $domain) : 0;
            $title = adminValue($row, 'title');
            if ($storeId <= 0 || $title === '') {
                $summary['skipped']++;
                $summary['errors'][] = 'Line ' . $line . ': missing store/domain or signal title.';
                continue;
            }

            $occurredAt = adminDateTimeValue($row, 'occurred_at');
            $exists->execute(['store_id' => $storeId, 'title' => $title, 'occurred_at' => $occurredAt]);
            $wasExisting = (bool) $exists->fetchColumn();
            $delete->execute(['store_id' => $storeId, 'title' => $title, 'occurred_at' => $occurredAt]);
            $insert->execute([
                'store_id' => $storeId,
                'signal_type' => mb_substr(adminValue($row, 'signal_type', 'general'), 0, 100),
                'title' => mb_substr($title, 0, 180),
                'description' => adminValue($row, 'description', $title),
                'occurred_at' => $occurredAt,
                'occurred_label' => mb_substr(adminValue($row, 'occurred_label', 'imported'), 0, 80),
            ]);
            $rowId = (int) $pdo->lastInsertId();

            $summary['processed']++;
            $summary[$wasExisting ? 'updated' : 'created']++;
            adminRecordImportItem($pdo, $batchId, 'store_signals', $rowId, $domain . ' · ' . $title, $wasExisting ? 'updated' : 'created');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    } finally {
        fclose($handle);
    }

    return $summary;
}

function adminImportByType(PDO $pdo, string $type, string $path, int $batchId): array
{
    return match ($type) {
        'technologies' => adminImportTechnologies($pdo, $path, $batchId),
        'products' => adminImportProducts($pdo, $path, $batchId),
        'signals' => adminImportSignals($pdo, $path, $batchId),
        default => adminImportStores($pdo, $path, $batchId),
    };
}

if (isset($_GET['template'])) {
    adminDownloadCsvTemplate((string) ($_GET['type'] ?? 'stores'));
}

$result = null;
$rollbackResult = null;
$error = '';
$databaseConnected = false;
$selectedImportType = (string) ($_POST['import_type'] ?? 'stores');
$templates = adminCsvTemplates();
$recentBatches = [];
$dashboardUsers = [];
$pendingRegistrations = [];
$pendingProRequests = [];
$subscriptionCounts = ['active_count' => 0, 'past_due_count' => 0, 'cancelling_count' => 0];
$ingestBatches = [];
$userMessage = '';
$pdo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::connect(shopSignalConfig());
        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }
        $databaseConnected = true;
        adminEnsureImportBatchSchema($pdo);
        shopSignalEnsureUserSchema($pdo);
        shopSignalEnsurePendingRegistrationSchema($pdo);
        shopSignalEnsureProRequestSchema($pdo);

        $adminAction = (string) ($_POST['admin_action'] ?? 'import');
        if ($adminAction === 'rollback') {
            $rollbackResult = adminDeleteImportBatch($pdo, (int) ($_POST['batch_id'] ?? 0));
        } elseif ($adminAction === 'update_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $role = in_array($_POST['role'] ?? '', ['user', 'admin'], true) ? (string) $_POST['role'] : 'user';
            $plan = in_array($_POST['plan'] ?? '', ['free', 'pro', 'enterprise'], true) ? (string) $_POST['plan'] : 'free';
            $status = in_array($_POST['status'] ?? '', ['active', 'disabled'], true) ? (string) $_POST['status'] : 'active';
            $verified = ($_POST['verified'] ?? '0') === '1';

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid name and email for the user.');
            }
            if ($userId === shopSignalCurrentUser()['id'] && ($status === 'disabled' || $role !== 'admin')) {
                throw new RuntimeException('You cannot disable or demote your own admin account.');
            }

            $existing = shopSignalFindUserByEmail($pdo, $email);
            if ($existing && (int) $existing['id'] !== $userId) {
                throw new RuntimeException('Another user already uses that email.');
            }

            $statement = $pdo->prepare('
                UPDATE users
                SET name = :name,
                    email = :email,
                    role = :role,
                    plan = :plan,
                    status = :status,
                    email_verified_at = CASE WHEN :verified = 1 THEN COALESCE(email_verified_at, NOW()) ELSE NULL END
                WHERE id = :id
            ');
            $statement->execute([
                'name' => mb_substr($name, 0, 160),
                'email' => $email,
                'role' => $role,
                'plan' => $plan,
                'status' => $status,
                'verified' => $verified ? 1 : 0,
                'id' => $userId,
            ]);
            $userMessage = 'User updated.';
        } elseif ($adminAction === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === shopSignalCurrentUser()['id']) {
                throw new RuntimeException('You cannot delete your own account.');
            }
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
            $userMessage = 'User deleted.';
        } elseif ($adminAction === 'delete_non_admins') {
            $pdo->exec('DELETE FROM users WHERE role <> \'admin\'');
            $pdo->exec('DELETE FROM pending_registrations');
            $userMessage = 'All non-admin users and pending registrations were deleted.';
        } elseif ($adminAction === 'delete_pending_registration') {
            $pdo->prepare('DELETE FROM pending_registrations WHERE id = :id')->execute(['id' => (int) ($_POST['pending_id'] ?? 0)]);
            $userMessage = 'Pending registration deleted.';
        } elseif ($adminAction === 'decide_pro_request') {
            $decision = (string) ($_POST['decision'] ?? '');
            shopSignalDecideProRequest($pdo, (int) ($_POST['request_id'] ?? 0), $decision, shopSignalCurrentUser()['id']);
            $userMessage = $decision === 'approved' ? 'Pro access approved.' : 'Pro access request rejected.';
        } else {
            if (!isset($templates[$selectedImportType])) {
                throw new RuntimeException('Unknown import type.');
            }

            if (!isset($_FILES['import_csv']) || ($_FILES['import_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please choose a CSV file to import.');
            }

            $batchId = adminCreateImportBatch($pdo, $selectedImportType, (string) ($_FILES['import_csv']['name'] ?? 'upload.csv'));
            $result = adminImportByType($pdo, $selectedImportType, (string) $_FILES['import_csv']['tmp_name'], $batchId);
            $result['batch_id'] = $batchId;
            adminFinalizeImportBatch($pdo, $batchId, $result);
        }
        $recentBatches = adminRecentImportBatches($pdo);
        $dashboardUsers = adminDashboardUsers($pdo);
        $pendingRegistrations = adminPendingRegistrations($pdo);
        $pendingProRequests = shopSignalPendingProRequests($pdo);
        $subscriptionCounts = adminSubscriptionCounts($pdo);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
} else {
    $pdo = Database::connect(shopSignalConfig());
    $databaseConnected = $pdo !== null;
    if ($pdo !== null) {
        $recentBatches = adminRecentImportBatches($pdo);
        $dashboardUsers = adminDashboardUsers($pdo);
        $pendingRegistrations = adminPendingRegistrations($pdo);
        $pendingProRequests = shopSignalPendingProRequests($pdo);
        $subscriptionCounts = adminSubscriptionCounts($pdo);
    }
}

if ($pdo instanceof PDO) {
    try {
        $recentBatches = $recentBatches ?: adminRecentImportBatches($pdo);
        $dashboardUsers = adminDashboardUsers($pdo);
        $pendingRegistrations = adminPendingRegistrations($pdo);
        $pendingProRequests = shopSignalPendingProRequests($pdo);
        $subscriptionCounts = adminSubscriptionCounts($pdo);
    } catch (Throwable) {
        // Keep the admin page usable even if an auxiliary panel cannot load.
    }
    try {
        $ingestionService = new IngestionService($pdo, shopSignalConfig());
        $ingestionService->ensureSchema();
        $ingestBatches = $ingestionService->recentBatches(20);
    } catch (Throwable) {
        // Keep the admin page usable while ingestion tables are being installed.
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin import — ShopSignal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(shopSignalVersionedAssetUrl('styles.css')) ?>" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <div class="admin-top">
        <a class="brand admin-brand" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32">
              <path d="M8.2 9.4 16 4l7.8 5.4v12.9L16 28l-7.8-5.7V9.4Z" />
              <path d="m11.8 17.1 2.7 2.7 5.9-7" />
            </svg>
          </span>
          <span>ShopSignal</span>
        </a>
        <div class="admin-actions">
          <?php foreach ($templates as $type => $template): ?>
            <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php?template=1&type=' . $type)) ?>"><?= htmlspecialchars((string) $template['label']) ?> schema</a>
          <?php endforeach; ?>
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Back to app</a>
        </div>
      </div>

      <section class="admin-hero">
        <p class="eyebrow"><span></span> Admin dashboard</p>
        <h1>Manage data and users.</h1>
        <p>Import Shopify data, review import batches, and edit users, roles, plans, and account status from one place.</p>
      </section>

      <section class="admin-grid">
        <article class="admin-card">
          <div class="section-heading">
            <h3>Upload CSV</h3>
            <span><?= $databaseConnected ? 'Database ready' : 'Database unavailable' ?></span>
          </div>
          <?php if ($error !== ''): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if (is_array($rollbackResult)): ?>
            <div class="import-success">
              Rollback complete. Deleted <?= number_format((int) $rollbackResult['deleted']) ?> newly-created rows from that batch.
            </div>
          <?php endif; ?>
          <?php if (is_array($result)): ?>
            <div class="import-summary">
              <div><span>Batch</span><strong>#<?= number_format((int) ($result['batch_id'] ?? 0)) ?></strong></div>
              <div><span>Processed</span><strong><?= number_format((int) $result['processed']) ?></strong></div>
              <div><span>Created</span><strong><?= number_format((int) $result['created']) ?></strong></div>
              <div><span>Updated</span><strong><?= number_format((int) $result['updated']) ?></strong></div>
              <div><span>Skipped</span><strong><?= number_format((int) $result['skipped']) ?></strong></div>
            </div>
            <?php if ($result['errors'] !== []): ?>
              <div class="import-errors">
                <?php foreach (array_slice($result['errors'], 0, 6) as $importError): ?>
                  <p><?= htmlspecialchars((string) $importError) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <form class="admin-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="admin_action" value="import" />
            <label>
              Import type
              <select name="import_type">
                <?php foreach ($templates as $type => $template): ?>
                  <option value="<?= htmlspecialchars($type) ?>" <?= $selectedImportType === $type ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $template['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              CSV file
              <input type="file" name="import_csv" accept=".csv,text/csv" required />
            </label>
            <button class="button primary" type="submit">Import CSV</button>
          </form>
        </article>

        <article class="admin-card">
          <div class="section-heading">
            <h3>CSV schema</h3>
            <span>Required: domain</span>
          </div>
          <p class="admin-note">Use these column names. Empty optional values are imported as safe defaults.</p>
          <?php foreach ($templates as $type => $template): ?>
            <div class="schema-block">
              <h4><?= htmlspecialchars((string) $template['label']) ?> <span>Required: <?= htmlspecialchars((string) $template['required']) ?></span></h4>
              <div class="schema-list">
                <?php foreach (adminCsvTemplateHeaders($type) as $header): ?>
                  <code><?= htmlspecialchars($header) ?></code>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </article>
      </section>

      <section class="admin-card admin-users-card">
        <div class="section-heading">
          <h3>Users</h3>
          <span><?= number_format(count($dashboardUsers)) ?> active records · <?= number_format(count($pendingRegistrations)) ?> pending</span>
        </div>
        <div class="subscription-summary">
          <div><span>Active Stripe</span><strong><?= number_format((int) $subscriptionCounts['active_count']) ?></strong></div>
          <div><span>Past due</span><strong><?= number_format((int) $subscriptionCounts['past_due_count']) ?></strong></div>
          <div><span>Cancelling</span><strong><?= number_format((int) $subscriptionCounts['cancelling_count']) ?></strong></div>
        </div>
        <?php if ($userMessage !== ''): ?>
          <div class="import-success"><?= htmlspecialchars($userMessage) ?></div>
        <?php endif; ?>
        <?php if ($pendingProRequests !== []): ?>
          <div class="pending-users pro-request-panel">
            <h4>Pending Pro requests</h4>
            <?php foreach ($pendingProRequests as $request): ?>
              <article class="pending-user-row">
                <div>
                  <strong><?= htmlspecialchars((string) $request['name']) ?></strong>
                  <span>
                    <?= htmlspecialchars((string) $request['email']) ?>
                    · Current plan: <?= htmlspecialchars((string) ($request['plan'] ?? 'free')) ?>
                    · Requested <?= htmlspecialchars((string) $request['created_label']) ?>
                  </span>
                  <?php if (trim((string) ($request['message'] ?? '')) !== ''): ?>
                    <p><?= htmlspecialchars((string) $request['message']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="pending-actions">
                  <form method="post">
                    <input type="hidden" name="admin_action" value="decide_pro_request" />
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>" />
                    <input type="hidden" name="decision" value="approved" />
                    <button class="button primary" type="submit">Approve Pro</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Reject this Pro request?');">
                    <input type="hidden" name="admin_action" value="decide_pro_request" />
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>" />
                    <input type="hidden" name="decision" value="rejected" />
                    <button class="button secondary danger" type="submit">Reject</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" class="bulk-danger-form" onsubmit="return confirm('Delete all non-admin users and pending registrations? This is for testing and cannot be undone.');">
          <input type="hidden" name="admin_action" value="delete_non_admins" />
          <button class="button secondary danger" type="submit">Delete all non-admin users</button>
        </form>

        <div class="user-table admin-dashboard-users">
          <?php foreach ($dashboardUsers as $user): ?>
            <article class="user-row admin-user-row">
              <form method="post" class="admin-user-edit">
                <input type="hidden" name="admin_action" value="update_user" />
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <label>Name <input name="name" value="<?= htmlspecialchars((string) $user['name']) ?>" required /></label>
                <label>Email <input name="email" type="email" value="<?= htmlspecialchars((string) $user['email']) ?>" required /></label>
                <label>Role
                  <select name="role">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                </label>
                <label>Plan
                  <select name="plan">
                    <option value="free" <?= ($user['plan'] ?? 'free') === 'free' ? 'selected' : '' ?>>Free</option>
                    <option value="pro" <?= ($user['plan'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro</option>
                    <option value="enterprise" <?= ($user['plan'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                  </select>
                </label>
                <label>Status
                  <select name="status">
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= $user['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                  </select>
                </label>
                <label>Verified
                  <select name="verified">
                    <option value="1" <?= $user['email_verified_at'] ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= !$user['email_verified_at'] ? 'selected' : '' ?>>Unverified</option>
                  </select>
                </label>
                <div class="admin-user-meta">
                  <span>Joined <?= htmlspecialchars((string) $user['created_label']) ?></span>
                  <span>Last login <?= htmlspecialchars((string) ($user['last_login_label'] ?: 'never')) ?></span>
                  <span>Billing <?= htmlspecialchars((string) (($user['subscription_status'] ?? '') ?: 'not connected')) ?><?= !empty($user['subscription_cancel_at_period_end']) ? ' · cancels at period end' : '' ?></span>
                </div>
                <button class="button secondary" type="submit">Save</button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this user?');">
                <input type="hidden" name="admin_action" value="delete_user" />
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <button class="button secondary danger" type="submit">Delete</button>
              </form>
            </article>
          <?php endforeach; ?>
          <?php if ($dashboardUsers === []): ?>
            <p class="admin-note">No confirmed users yet.</p>
          <?php endif; ?>
        </div>

        <?php if ($pendingRegistrations !== []): ?>
          <div class="pending-users">
            <h4>Pending email confirmations</h4>
            <?php foreach ($pendingRegistrations as $pending): ?>
              <article class="pending-user-row">
                <div>
                  <strong><?= htmlspecialchars((string) $pending['name']) ?></strong>
                  <span><?= htmlspecialchars((string) $pending['email']) ?> · Requested <?= htmlspecialchars((string) $pending['created_label']) ?></span>
                </div>
                <form method="post" onsubmit="return confirm('Delete this pending registration?');">
                  <input type="hidden" name="admin_action" value="delete_pending_registration" />
                  <input type="hidden" name="pending_id" value="<?= (int) $pending['id'] ?>" />
                  <button class="button secondary danger" type="submit">Delete pending</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="admin-card import-history-card">
        <div class="section-heading">
          <h3>Crawler sync history</h3>
          <span><?= shopSignalIngestionEnabled() ? 'Signed ingestion enabled' : 'Ingestion not configured' ?></span>
        </div>
        <?php if ($ingestBatches === []): ?>
          <p class="admin-note">No crawler batches received yet. Configure the signed local sync client, then completed batches will appear here.</p>
        <?php else: ?>
          <div class="sync-history">
            <?php foreach ($ingestBatches as $batch): ?>
              <article class="sync-batch">
                <div class="sync-batch-main">
                  <span class="plan-pill <?= $batch['status'] === 'completed' ? 'pro' : '' ?>"><?= htmlspecialchars((string) $batch['status']) ?></span>
                  <div><strong><?= htmlspecialchars((string) $batch['source_name']) ?></strong><span><?= htmlspecialchars((string) $batch['received_label']) ?> · <?= htmlspecialchars((string) $batch['remote_ip']) ?></span><code><?= htmlspecialchars((string) $batch['batch_id']) ?></code></div>
                </div>
                <div class="sync-batch-counts">
                  <span><b><?= number_format((int) $batch['store_count']) ?></b> sent</span>
                  <span><b><?= number_format((int) $batch['stores_created']) ?></b> new</span>
                  <span><b><?= number_format((int) $batch['stores_updated']) ?></b> updated</span>
                  <span><b><?= number_format((int) $batch['technologies_upserted']) ?></b> apps</span>
                  <span><b><?= number_format((int) $batch['products_upserted']) ?></b> products</span>
                  <span><b><?= number_format((int) $batch['signals_upserted']) ?></b> signals</span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="admin-card import-history-card">
        <div class="section-heading">
          <h3>Recent import batches</h3>
          <span>Rollback deletes newly-created rows only</span>
        </div>
        <?php if ($recentBatches === []): ?>
          <p class="admin-note">No import batches yet.</p>
        <?php else: ?>
          <div class="import-history">
            <?php foreach ($recentBatches as $batch): ?>
              <article class="import-batch <?= $batch['rolled_back_at'] ? 'rolled-back' : '' ?>">
                <div>
                  <strong>#<?= number_format((int) $batch['id']) ?> · <?= htmlspecialchars((string) $batch['import_type']) ?></strong>
                  <span><?= htmlspecialchars((string) ($batch['filename'] ?? '')) ?> · <?= htmlspecialchars((string) $batch['created_label']) ?></span>
                </div>
                <div class="import-batch-counts">
                  <span><?= number_format((int) $batch['processed_count']) ?> processed</span>
                  <span><?= number_format((int) $batch['created_count']) ?> created</span>
                  <span><?= number_format((int) $batch['updated_count']) ?> updated</span>
                  <span><?= number_format((int) $batch['skipped_count']) ?> skipped</span>
                </div>
                <?php if ($batch['rolled_back_at']): ?>
                  <span class="rollback-pill">Rolled back</span>
                <?php else: ?>
                  <form method="post" onsubmit="return confirm('Rollback this import batch? Only rows created by this batch will be deleted. Updated rows will remain.');">
                    <input type="hidden" name="admin_action" value="rollback" />
                    <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>" />
                    <button class="button secondary danger" type="submit">Rollback</button>
                  </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
