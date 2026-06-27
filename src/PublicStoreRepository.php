<?php
declare(strict_types=1);

final class PublicStoreRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findStore(int $id): ?array
    {
        $statement = $this->pdo->prepare('
            SELECT id, name, domain, category, founded_year, headquarters, country,
                   product_count, store_language, currency, logo_letter, logo_class,
                   created_at, updated_at
            FROM stores
            WHERE id = :id
            LIMIT 1
        ');
        $statement->execute(['id' => $id]);
        $store = $statement->fetch();
        if (!is_array($store)) {
            return null;
        }

        $technologyStatement = $this->pdo->prepare('
            SELECT technology_name, category, last_seen_at
            FROM store_technologies
            WHERE store_id = :store_id
            ORDER BY last_seen_at DESC, technology_name ASC
            LIMIT 4
        ');
        $technologyStatement->execute(['store_id' => $id]);

        $productStatement = $this->pdo->prepare('
            SELECT name, category, price, currency_symbol
            FROM products
            WHERE store_id = :store_id
            ORDER BY is_top_product DESC, last_seen_at DESC, id DESC
            LIMIT 6
        ');
        $productStatement->execute(['store_id' => $id]);

        $signalStatement = $this->pdo->prepare('
            SELECT signal_type, title, occurred_label, occurred_at
            FROM store_signals
            WHERE store_id = :store_id
            ORDER BY occurred_at DESC
            LIMIT 4
        ');
        $signalStatement->execute(['store_id' => $id]);

        $relatedStatement = $this->pdo->prepare('
            SELECT id, name, domain, category, country, product_count, founded_year, logo_letter, logo_class
            FROM stores
            WHERE category = :category AND id <> :id
            ORDER BY updated_at DESC, id DESC
            LIMIT 6
        ');
        $relatedStatement->execute(['category' => (string) $store['category'], 'id' => $id]);

        $store['technologies'] = $technologyStatement->fetchAll();
        $store['products'] = $productStatement->fetchAll();
        $store['signals'] = $signalStatement->fetchAll();
        $store['related'] = $relatedStatement->fetchAll();
        return $store;
    }

    public function resolveDimension(string $type, string $slug): ?array
    {
        $query = match ($type) {
            'category' => 'SELECT category AS value, COUNT(*) AS store_count FROM stores WHERE category <> \'\' GROUP BY category',
            'country' => 'SELECT country AS value, COUNT(*) AS store_count FROM stores WHERE country IS NOT NULL AND country <> \'\' GROUP BY country',
            'app' => 'SELECT technology_name AS value, COUNT(DISTINCT store_id) AS store_count FROM store_technologies GROUP BY technology_name',
            default => '',
        };
        if ($query === '') {
            return null;
        }
        foreach ($this->pdo->query($query)->fetchAll() as $row) {
            if (shopSignalSeoSlug((string) $row['value']) === $slug) {
                return ['value' => (string) $row['value'], 'store_count' => (int) $row['store_count']];
            }
        }
        return null;
    }

    public function countStores(string $type = 'stores', string $value = ''): int
    {
        [$join, $where, $params] = $this->dimensionSql($type, $value);
        $statement = $this->pdo->prepare('SELECT COUNT(DISTINCT s.id) FROM stores s ' . $join . ' WHERE ' . $where);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    public function findStores(string $type = 'stores', string $value = '', int $limit = 24, int $offset = 0): array
    {
        [$join, $where, $params] = $this->dimensionSql($type, $value);
        $statement = $this->pdo->prepare('
            SELECT DISTINCT s.id, s.name, s.domain, s.category, s.country, s.product_count,
                   s.founded_year, s.logo_letter, s.logo_class, s.updated_at
            FROM stores s
            ' . $join . '
            WHERE ' . $where . '
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT :limit OFFSET :offset
        ');
        foreach ($params as $key => $parameter) {
            $statement->bindValue(':' . $key, $parameter);
        }
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function topDimensions(string $type, int $limit = 12): array
    {
        $sql = match ($type) {
            'category' => 'SELECT category AS value, COUNT(*) AS store_count FROM stores WHERE category <> \'\' GROUP BY category ORDER BY store_count DESC, value ASC LIMIT :limit',
            'country' => 'SELECT country AS value, COUNT(*) AS store_count FROM stores WHERE country IS NOT NULL AND country <> \'\' GROUP BY country ORDER BY store_count DESC, value ASC LIMIT :limit',
            'app' => 'SELECT technology_name AS value, COUNT(DISTINCT store_id) AS store_count FROM store_technologies GROUP BY technology_name ORDER BY store_count DESC, value ASC LIMIT :limit',
            default => '',
        };
        if ($sql === '') {
            return [];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function countDimensions(string $type): int
    {
        $sql = match ($type) {
            'category' => 'SELECT COUNT(DISTINCT category) FROM stores WHERE category <> \'\'',
            'country' => 'SELECT COUNT(DISTINCT country) FROM stores WHERE country IS NOT NULL AND country <> \'\'',
            'app' => 'SELECT COUNT(DISTINCT technology_name) FROM store_technologies',
            default => 'SELECT 0',
        };
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function sitemapStoreCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn();
    }

    public function sitemapStores(int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, domain, updated_at FROM stores ORDER BY id ASC LIMIT :limit OFFSET :offset');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function sitemapDimensions(string $type): array
    {
        $sql = match ($type) {
            'category' => 'SELECT category AS value, COUNT(*) AS store_count, MAX(updated_at) AS updated_at FROM stores WHERE category <> \'\' GROUP BY category ORDER BY store_count DESC LIMIT 1000',
            'country' => 'SELECT country AS value, COUNT(*) AS store_count, MAX(updated_at) AS updated_at FROM stores WHERE country IS NOT NULL AND country <> \'\' GROUP BY country ORDER BY store_count DESC LIMIT 1000',
            'app' => 'SELECT technology_name AS value, COUNT(DISTINCT store_id) AS store_count, MAX(last_seen_at) AS updated_at FROM store_technologies GROUP BY technology_name ORDER BY store_count DESC LIMIT 1000',
            default => '',
        };
        return $sql !== '' ? $this->pdo->query($sql)->fetchAll() : [];
    }

    private function dimensionSql(string $type, string $value): array
    {
        return match ($type) {
            'category' => ['', 's.category = :value', ['value' => $value]],
            'country' => ['', 's.country = :value', ['value' => $value]],
            'app' => ['INNER JOIN store_technologies seo_tech ON seo_tech.store_id = s.id', 'seo_tech.technology_name = :value', ['value' => $value]],
            default => ['', '1 = 1', []],
        };
    }
}
