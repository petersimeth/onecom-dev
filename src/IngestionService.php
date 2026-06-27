<?php
declare(strict_types=1);

final class IngestionService
{
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS crawler_ingest_nonces (
                key_id VARCHAR(120) NOT NULL,
                nonce VARCHAR(100) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (key_id, nonce),
                INDEX idx_ingest_nonce_expiry (expires_at)
            )
        ');
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS crawler_ingest_batches (
                batch_id VARCHAR(100) PRIMARY KEY,
                request_sha256 CHAR(64) NOT NULL,
                source_name VARCHAR(120) NOT NULL,
                remote_ip VARCHAR(45) NULL,
                status ENUM(\'processing\', \'completed\', \'failed\') DEFAULT \'processing\',
                store_count INT UNSIGNED DEFAULT 0,
                stores_created INT UNSIGNED DEFAULT 0,
                stores_updated INT UNSIGNED DEFAULT 0,
                technologies_upserted INT UNSIGNED DEFAULT 0,
                products_upserted INT UNSIGNED DEFAULT 0,
                signals_upserted INT UNSIGNED DEFAULT 0,
                error_message VARCHAR(500) NULL,
                received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                INDEX idx_ingest_batch_received (received_at),
                INDEX idx_ingest_batch_status (status)
            )
        ');

        $this->ensureColumn('stores', 'crawler_description TEXT NULL');
        $this->ensureColumn('stores', 'crawler_confidence SMALLINT UNSIGNED DEFAULT 0');
        $this->ensureColumn('stores', 'crawler_signals_json TEXT NULL');
        $this->ensureColumn('stores', 'myshopify_domain VARCHAR(255) NULL');
        $this->ensureColumn('stores', 'crawler_source_url VARCHAR(500) NULL');
        $this->ensureColumn('stores', 'last_crawled_at DATETIME NULL');

        foreach (['store_technologies', 'products', 'store_signals'] as $table) {
            $this->ensureColumn($table, 'source_key CHAR(64) NULL');
            $this->ensureColumn($table, 'ingest_source VARCHAR(80) NULL');
            $this->ensureIndex($table, 'uq_' . $table . '_source', 'UNIQUE INDEX uq_' . $table . '_source (store_id, source_key)');
        }
        $this->ensureIndex('stores', 'idx_store_last_crawled', 'INDEX idx_store_last_crawled (last_crawled_at)');
    }

    public function consumeNonce(string $keyId, string $nonce, int $expiresAt): void
    {
        $this->pdo->prepare('DELETE FROM crawler_ingest_nonces WHERE expires_at < NOW()')->execute();
        try {
            $statement = $this->pdo->prepare('
                INSERT INTO crawler_ingest_nonces (key_id, nonce, expires_at)
                VALUES (:key_id, :nonce, :expires_at)
            ');
            $statement->execute([
                'key_id' => $keyId,
                'nonce' => $nonce,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('This signed request was already used.', 409);
            }
            throw $exception;
        }
    }

    public function ingest(array $payload, string $requestHash, string $remoteIp): array
    {
        $schemaVersion = (int) ($payload['schema_version'] ?? 0);
        if ($schemaVersion !== 1) {
            throw new InvalidArgumentException('Unsupported ingestion schema version.');
        }
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{16,100}$/', $batchId)) {
            throw new InvalidArgumentException('Invalid batch_id.');
        }
        $source = $this->text($payload['source'] ?? 'shopify-spider', 120, 'source');
        $stores = $payload['stores'] ?? null;
        $maxBatch = max(1, min(500, (int) ($this->config['crawler_ingest_max_batch'] ?? 100)));
        if (!is_array($stores) || $stores === [] || count($stores) > $maxBatch) {
            throw new InvalidArgumentException('stores must contain between 1 and ' . $maxBatch . ' records.');
        }

        $summary = [
            'store_count' => count($stores),
            'stores_created' => 0,
            'stores_updated' => 0,
            'technologies_upserted' => 0,
            'products_upserted' => 0,
            'signals_upserted' => 0,
        ];

        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT request_sha256, status, store_count, stores_created, stores_updated, technologies_upserted, products_upserted, signals_upserted FROM crawler_ingest_batches WHERE batch_id = :batch_id LIMIT 1 FOR UPDATE');
            $existing->execute(['batch_id' => $batchId]);
            $existingBatch = $existing->fetch();
            if (is_array($existingBatch)) {
                if (!hash_equals((string) $existingBatch['request_sha256'], $requestHash)) {
                    throw new RuntimeException('batch_id was already used for different content.', 409);
                }
                if ((string) $existingBatch['status'] === 'completed') {
                    $this->pdo->commit();
                    return $this->summaryFromRow($existingBatch) + ['batch_id' => $batchId, 'duplicate' => true];
                }
            }
            if (!is_array($existingBatch)) {
                $statement = $this->pdo->prepare('
                    INSERT INTO crawler_ingest_batches (batch_id, request_sha256, source_name, remote_ip, status, store_count)
                    VALUES (:batch_id, :request_sha256, :source_name, :remote_ip, \'processing\', :store_count)
                ');
                $statement->execute([
                    'batch_id' => $batchId,
                    'request_sha256' => $requestHash,
                    'source_name' => $source,
                    'remote_ip' => mb_substr($remoteIp, 0, 45),
                    'store_count' => count($stores),
                ]);
            }

            foreach ($stores as $index => $store) {
                if (!is_array($store)) {
                    throw new InvalidArgumentException('Store record ' . ($index + 1) . ' must be an object.');
                }
                $result = $this->upsertStore($store, $source);
                $summary[$result['created'] ? 'stores_created' : 'stores_updated']++;
                $summary['technologies_upserted'] += $this->upsertTechnologies((int) $result['store_id'], $store['technologies'] ?? [], $source);
                $summary['products_upserted'] += $this->upsertProducts((int) $result['store_id'], $store['products'] ?? [], $source);
                $summary['signals_upserted'] += $this->upsertSignals((int) $result['store_id'], $store['signals'] ?? [], $source);
            }

            $statement = $this->pdo->prepare('
                UPDATE crawler_ingest_batches
                SET status = \'completed\',
                    stores_created = :stores_created,
                    stores_updated = :stores_updated,
                    technologies_upserted = :technologies_upserted,
                    products_upserted = :products_upserted,
                    signals_upserted = :signals_upserted,
                    completed_at = NOW()
                WHERE batch_id = :batch_id
            ');
            $statement->execute([
                'stores_created' => $summary['stores_created'],
                'stores_updated' => $summary['stores_updated'],
                'technologies_upserted' => $summary['technologies_upserted'],
                'products_upserted' => $summary['products_upserted'],
                'signals_upserted' => $summary['signals_upserted'],
                'batch_id' => $batchId,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $summary + ['batch_id' => $batchId, 'duplicate' => false];
    }

    public function recentBatches(int $limit = 20): array
    {
        $statement = $this->pdo->prepare('
            SELECT batch_id, source_name, remote_ip, status, store_count, stores_created, stores_updated,
                   technologies_upserted, products_upserted, signals_upserted, error_message,
                   DATE_FORMAT(received_at, \'%b %e, %Y %H:%i\') AS received_label,
                   DATE_FORMAT(completed_at, \'%b %e, %Y %H:%i\') AS completed_label
            FROM crawler_ingest_batches
            ORDER BY received_at DESC
            LIMIT :limit
        ');
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function upsertStore(array $store, string $source): array
    {
        $domain = $this->domain($store['domain'] ?? '');
        $name = $this->text($store['name'] ?? $store['title'] ?? strtok($domain, '.'), 180, 'store name');
        $category = $this->optionalText($store['category'] ?? '', 120) ?: 'Uncategorized';
        $email = '';
        if (isset($store['emails']) && is_array($store['emails'])) {
            foreach ($store['emails'] as $candidate) {
                $candidate = mb_strtolower(trim((string) $candidate));
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $email = mb_substr($candidate, 0, 255);
                    break;
                }
            }
        }
        $existing = $this->pdo->prepare('SELECT id FROM stores WHERE domain = :domain LIMIT 1');
        $existing->execute(['domain' => $domain]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        $lastSeen = $this->dateTime($store['last_seen_at'] ?? null);
        $firstSeen = $this->dateTime($store['first_seen_at'] ?? null);
        $confidence = max(0, min(1000, (int) ($store['confidence'] ?? 0)));
        $detectionSignals = isset($store['detection_signals']) && is_array($store['detection_signals'])
            ? array_slice(array_values(array_map(static fn ($value): string => mb_substr(trim((string) $value), 0, 160), $store['detection_signals'])), 0, 50)
            : [];

        $statement = $this->pdo->prepare('
            INSERT INTO stores (
                name, domain, category, public_email, store_language, currency, logo_letter,
                crawler_description, crawler_confidence, crawler_signals_json, myshopify_domain,
                crawler_source_url, last_crawled_at, created_at, updated_at
            ) VALUES (
                :name, :domain, :category, :public_email, :store_language, :currency, :logo_letter,
                :crawler_description, :crawler_confidence, :crawler_signals_json, :myshopify_domain,
                :crawler_source_url, :last_crawled_at, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                name = IF(name = \'\' OR name = domain, VALUES(name), name),
                category = IF((category = \'\' OR category = \'Uncategorized\') AND VALUES(category) <> \'Uncategorized\', VALUES(category), category),
                public_email = IF(public_email IS NULL OR public_email = \'\', VALUES(public_email), public_email),
                store_language = IF(VALUES(store_language) <> \'\', VALUES(store_language), store_language),
                currency = IF(VALUES(currency) <> \'\', VALUES(currency), currency),
                crawler_description = VALUES(crawler_description),
                crawler_confidence = GREATEST(crawler_confidence, VALUES(crawler_confidence)),
                crawler_signals_json = VALUES(crawler_signals_json),
                myshopify_domain = IF(VALUES(myshopify_domain) <> \'\', VALUES(myshopify_domain), myshopify_domain),
                crawler_source_url = VALUES(crawler_source_url),
                last_crawled_at = GREATEST(COALESCE(last_crawled_at, VALUES(last_crawled_at)), VALUES(last_crawled_at)),
                updated_at = GREATEST(updated_at, VALUES(updated_at))
        ');
        $statement->execute([
            'name' => $name,
            'domain' => $domain,
            'category' => $category,
            'public_email' => $email,
            'store_language' => $this->optionalText($store['language'] ?? '', 60),
            'currency' => $this->optionalText($store['currency'] ?? '', 30),
            'logo_letter' => mb_strtoupper(mb_substr($name, 0, 1)),
            'crawler_description' => $this->optionalText($store['description'] ?? '', 5000),
            'crawler_confidence' => $confidence,
            'crawler_signals_json' => json_encode($detectionSignals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'myshopify_domain' => $this->optionalText($store['myshopify_domain'] ?? '', 255),
            'crawler_source_url' => $this->optionalText($store['final_url'] ?? $store['url'] ?? '', 500),
            'last_crawled_at' => $lastSeen,
            'created_at' => $firstSeen,
            'updated_at' => $lastSeen,
        ]);

        $idStatement = $this->pdo->prepare('SELECT id FROM stores WHERE domain = :domain LIMIT 1');
        $idStatement->execute(['domain' => $domain]);
        return ['store_id' => (int) $idStatement->fetchColumn(), 'created' => $existingId === 0];
    }

    private function upsertTechnologies(int $storeId, mixed $records, string $source): int
    {
        if (!is_array($records)) {
            throw new InvalidArgumentException('technologies must be an array.');
        }
        if (count($records) > 100) {
            throw new InvalidArgumentException('A store cannot contain more than 100 technologies per batch.');
        }
        $statement = $this->pdo->prepare('
            INSERT INTO store_technologies (store_id, technology_name, category, short_code, detected_at, last_seen_at, source_key, ingest_source)
            VALUES (:store_id, :name, :category, :short_code, :detected_at, :last_seen_at, :source_key, :ingest_source)
            ON DUPLICATE KEY UPDATE category = VALUES(category), short_code = VALUES(short_code), last_seen_at = VALUES(last_seen_at), ingest_source = VALUES(ingest_source)
        ');
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Technology records must be objects.');
            }
            $name = $this->text($record['name'] ?? '', 140, 'technology name');
            $statement->execute([
                'store_id' => $storeId,
                'name' => $name,
                'category' => $this->optionalText($record['category'] ?? 'Technology', 120) ?: 'Technology',
                'short_code' => $this->optionalText($record['short_code'] ?? '', 10),
                'detected_at' => $this->date($record['detected_at'] ?? null),
                'last_seen_at' => $this->date($record['last_seen_at'] ?? null),
                'source_key' => hash('sha256', mb_strtolower($name)),
                'ingest_source' => mb_substr($source, 0, 80),
            ]);
            $count++;
        }
        return $count;
    }

    private function upsertProducts(int $storeId, mixed $records, string $source): int
    {
        if (!is_array($records)) {
            throw new InvalidArgumentException('products must be an array.');
        }
        if (count($records) > 200) {
            throw new InvalidArgumentException('A store cannot contain more than 200 products per batch.');
        }
        $statement = $this->pdo->prepare('
            INSERT INTO products (store_id, name, category, price, currency_symbol, product_url, is_top_product, first_seen_at, last_seen_at, source_key, ingest_source)
            VALUES (:store_id, :name, :category, :price, :currency_symbol, :product_url, :is_top_product, :first_seen_at, :last_seen_at, :source_key, :ingest_source)
            ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), price = VALUES(price), currency_symbol = VALUES(currency_symbol), product_url = VALUES(product_url), last_seen_at = VALUES(last_seen_at), ingest_source = VALUES(ingest_source)
        ');
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Product records must be objects.');
            }
            $name = $this->text($record['name'] ?? '', 255, 'product name');
            $url = $this->optionalText($record['url'] ?? $record['product_url'] ?? '', 500);
            $keyMaterial = $url !== '' ? mb_strtolower($url) : mb_strtolower($name . '|' . (string) ($record['category'] ?? ''));
            $statement->execute([
                'store_id' => $storeId,
                'name' => $name,
                'category' => $this->optionalText($record['category'] ?? '', 120),
                'price' => max(0, min(99999999, (float) ($record['price'] ?? 0))),
                'currency_symbol' => $this->currencySymbol($record['currency_symbol'] ?? $record['currency'] ?? '$'),
                'product_url' => $url,
                'is_top_product' => !empty($record['is_top_product']) ? 1 : 0,
                'first_seen_at' => $this->date($record['first_seen_at'] ?? null),
                'last_seen_at' => $this->date($record['last_seen_at'] ?? null),
                'source_key' => hash('sha256', $keyMaterial),
                'ingest_source' => mb_substr($source, 0, 80),
            ]);
            $count++;
        }
        return $count;
    }

    private function upsertSignals(int $storeId, mixed $records, string $source): int
    {
        if (!is_array($records)) {
            throw new InvalidArgumentException('signals must be an array.');
        }
        if (count($records) > 200) {
            throw new InvalidArgumentException('A store cannot contain more than 200 signals per batch.');
        }
        $statement = $this->pdo->prepare('
            INSERT INTO store_signals (store_id, signal_type, title, description, occurred_at, occurred_label, source_key, ingest_source)
            VALUES (:store_id, :signal_type, :title, :description, :occurred_at, :occurred_label, :source_key, :ingest_source)
            ON DUPLICATE KEY UPDATE description = VALUES(description), occurred_label = VALUES(occurred_label), ingest_source = VALUES(ingest_source)
        ');
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException('Signal records must be objects.');
            }
            $type = $this->text($record['type'] ?? $record['signal_type'] ?? 'observation', 100, 'signal type');
            $title = $this->text($record['title'] ?? '', 180, 'signal title');
            $occurredAt = $this->dateTime($record['occurred_at'] ?? null);
            $statement->execute([
                'store_id' => $storeId,
                'signal_type' => $type,
                'title' => $title,
                'description' => $this->optionalText($record['description'] ?? '', 5000),
                'occurred_at' => $occurredAt,
                'occurred_label' => $this->optionalText($record['occurred_label'] ?? 'Recently', 80) ?: 'Recently',
                'source_key' => hash('sha256', mb_strtolower($type . '|' . $title . '|' . $occurredAt)),
                'ingest_source' => mb_substr($source, 0, 80),
            ]);
            $count++;
        }
        return $count;
    }

    private function ensureColumn(string $table, string $definition): void
    {
        $column = strtok($definition, ' ');
        if (!is_string($column) || !in_array($table, ['stores', 'store_technologies', 'products', 'store_signals'], true)) {
            throw new InvalidArgumentException('Invalid ingestion schema migration.');
        }
        $columns = $this->pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
        foreach ($columns as $existing) {
            if (strcasecmp((string) ($existing['Field'] ?? ''), $column) === 0) {
                return;
            }
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $definition);
    }

    private function ensureIndex(string $table, string $indexName, string $definition): void
    {
        if (!in_array($table, ['stores', 'store_technologies', 'products', 'store_signals'], true)) {
            throw new InvalidArgumentException('Invalid ingestion schema migration.');
        }
        $indexes = $this->pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll();
        foreach ($indexes as $existing) {
            if (strcasecmp((string) ($existing['Key_name'] ?? ''), $indexName) === 0) {
                return;
            }
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $definition);
    }

    private function summaryFromRow(array $row): array
    {
        return array_map('intval', array_intersect_key($row, array_flip([
            'store_count', 'stores_created', 'stores_updated', 'technologies_upserted', 'products_upserted', 'signals_upserted',
        ])));
    }

    private function domain(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid store domain.');
        }
        $domain = mb_strtolower(trim((string) $value));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#[/:].*$#', '', $domain) ?? $domain;
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Invalid store domain.');
        }
        return mb_substr($domain, 0, 255);
    }

    private function text(mixed $value, int $limit, string $field): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException($field . ' must be text.');
        }
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException($field . ' is required.');
        }
        return mb_substr($value, 0, $limit);
    }

    private function optionalText(mixed $value, int $limit): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('A text field contained an invalid value.');
        }
        return mb_substr(trim((string) $value), 0, $limit);
    }

    private function dateTime(mixed $value): string
    {
        if ($value === null) {
            return date('Y-m-d H:i:s');
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid date value.');
        }
        if (trim((string) $value) === '') {
            return date('Y-m-d H:i:s');
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid date value.');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function date(mixed $value): string
    {
        return substr($this->dateTime($value), 0, 10);
    }

    private function currencySymbol(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '$';
        }
        $value = strtoupper(trim((string) $value));
        return match ($value) {
            'USD', '$' => '$', 'EUR', '€' => '€', 'GBP', '£' => '£', 'JPY', '¥' => '¥',
            default => mb_substr($value !== '' ? $value : '$', 0, 4),
        };
    }
}
