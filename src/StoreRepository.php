<?php
declare(strict_types=1);

final class StoreRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStores(
        string $search = '',
        string $sort = 'growth',
        int $limit = 50,
        int $offset = 0,
        array $filters = []
    ): array {
        $sortColumns = [
            'signal' => 's.growth_percent DESC',
            'growth' => 's.growth_percent DESC',
            'revenue' => 's.estimated_monthly_revenue DESC',
            'traffic' => 's.monthly_traffic DESC',
            'newest' => 's.founded_year DESC',
            'products' => 's.product_count DESC',
        ];
        $orderBy = $sortColumns[$sort] ?? $sortColumns['growth'];
        $limit = max(1, min($limit, 250));
        $offset = max(0, $offset);
        [$whereSql, $params] = $this->buildStoreFilterSql($search, $filters);

        $sql = '
            SELECT
                s.id,
                s.name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(st.stack, \'\') AS stack
            FROM stores s
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(
                        short_code
                        ORDER BY id
                        SEPARATOR \',\'
                    ) AS stack
                FROM store_technologies
                GROUP BY store_id
            ) st ON st.store_id = s.id
            WHERE ' . $whereSql . '
            ORDER BY ' . $orderBy . '
            LIMIT :limit OFFSET :offset
        ';

        $statement = $this->pdo->prepare($sql);
        $this->bindStoreFilterParams($statement, $params);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'formatStore'], $statement->fetchAll());
    }

    public function countStores(string $search = '', array $filters = []): int
    {
        [$whereSql, $params] = $this->buildStoreFilterSql($search, $filters);
        $statement = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM stores s
            WHERE ' . $whereSql . '
        ');
        $this->bindStoreFilterParams($statement, $params);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{store: array<string, mixed>, profile: array<string, mixed>}|null
     */
    public function getStoreDetail(int $storeId): ?array
    {
        $store = $this->findStoreById($storeId);
        if ($store === null) {
            return null;
        }

        return [
            'store' => $store,
            'profile' => $this->findProfile($storeId),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStoreById(int $storeId): ?array
    {
        $statement = $this->pdo->prepare('
            SELECT
                s.id,
                s.name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(st.stack, \'\') AS stack
            FROM stores s
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(short_code ORDER BY id SEPARATOR \',\') AS stack
                FROM store_technologies
                GROUP BY store_id
            ) st ON st.store_id = s.id
            WHERE s.id = :id
            LIMIT 1
        ');
        $statement->bindValue(':id', $storeId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        return $row ? $this->formatStore($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, array{value: mixed, type: int}>}
     */
    public function buildStoreFilterSql(string $search, array $filters): array
    {
        $clauses = [
            '(
                :search = \'\'
                OR s.name LIKE :search_name
                OR s.domain LIKE :search_domain
                OR s.category LIKE :search_category
            )',
        ];
        $params = [
            'search' => ['value' => $search, 'type' => PDO::PARAM_STR],
            'search_name' => ['value' => '%' . $search . '%', 'type' => PDO::PARAM_STR],
            'search_domain' => ['value' => '%' . $search . '%', 'type' => PDO::PARAM_STR],
            'search_category' => ['value' => '%' . $search . '%', 'type' => PDO::PARAM_STR],
        ];

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $clauses[] = 's.category = :category';
            $params['category'] = ['value' => $category, 'type' => PDO::PARAM_STR];
        }

        $country = trim((string) ($filters['country'] ?? ''));
        if ($country !== '') {
            $clauses[] = 's.country = :country';
            $params['country'] = ['value' => $country, 'type' => PDO::PARAM_STR];
        }

        $minRevenue = (int) ($filters['min_revenue'] ?? 0);
        if ($minRevenue > 0) {
            $clauses[] = 's.estimated_monthly_revenue >= :min_revenue';
            $params['min_revenue'] = ['value' => $minRevenue, 'type' => PDO::PARAM_INT];
        }

        $minGrowth = (float) ($filters['min_growth'] ?? 0);
        if ($minGrowth > 0) {
            $clauses[] = 's.growth_percent >= :min_growth';
            $params['min_growth'] = ['value' => $minGrowth, 'type' => PDO::PARAM_STR];
        }

        $technology = trim((string) ($filters['technology'] ?? ''));
        if ($technology !== '') {
            $clauses[] = 'EXISTS (
                SELECT 1
                FROM store_technologies filter_tech
                WHERE filter_tech.store_id = s.id
                  AND filter_tech.technology_name LIKE :technology
            )';
            $params['technology'] = ['value' => '%' . $technology . '%', 'type' => PDO::PARAM_STR];
        }

        $productCategory = trim((string) ($filters['product_category'] ?? ''));
        if ($productCategory !== '') {
            $clauses[] = 'EXISTS (
                SELECT 1
                FROM products filter_products
                WHERE filter_products.store_id = s.id
                  AND filter_products.category = :product_category
            )';
            $params['product_category'] = ['value' => $productCategory, 'type' => PDO::PARAM_STR];
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * @param array<string, array{value: mixed, type: int}> $params
     */
    public function bindStoreFilterParams(PDOStatement $statement, array $params): void
    {
        foreach ($params as $name => $payload) {
            $statement->bindValue(':' . $name, $payload['value'], $payload['type']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStoresForSavedList(int $listId, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 250));
        $offset = max(0, $offset);

        $statement = $this->pdo->prepare('
            SELECT
                s.id,
                s.name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(st.stack, \'\') AS stack
            FROM saved_list_stores sls
            INNER JOIN stores s ON s.id = sls.store_id
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(short_code ORDER BY id SEPARATOR \',\') AS stack
                FROM store_technologies
                GROUP BY store_id
            ) st ON st.store_id = s.id
            WHERE sls.list_id = :list_id
            ORDER BY sls.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $statement->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'formatStore'], $statement->fetchAll());
    }

    public function countStoresForSavedList(int $listId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM saved_list_stores WHERE list_id = :list_id');
        $statement->execute(['list_id' => $listId]);
        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{signals: array<int, array<string, mixed>>, stores: array<int, array<string, mixed>>}
     */
    public function findSignals(string $type = 'all', int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $where = $type !== 'all' ? 'WHERE ss.signal_type = :type' : '';

        $statement = $this->pdo->prepare('
            SELECT
                ss.id,
                ss.store_id,
                ss.signal_type,
                ss.title,
                ss.description,
                ss.occurred_label,
                DATE_FORMAT(ss.occurred_at, \'%b %e, %Y %H:%i\') AS occurred_at_label,
                s.name AS store_name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(st.stack, \'\') AS stack
            FROM store_signals ss
            INNER JOIN stores s ON s.id = ss.store_id
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(short_code ORDER BY id SEPARATOR \',\') AS stack
                FROM store_technologies
                GROUP BY store_id
            ) st ON st.store_id = s.id
            ' . $where . '
            ORDER BY ss.occurred_at DESC
            LIMIT :limit
        ');

        if ($type !== 'all') {
            $statement->bindValue(':type', $type);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $signals = [];
        $stores = [];
        foreach ($statement->fetchAll() as $row) {
            $store = $this->formatStore([
                'id' => $row['store_id'],
                'name' => $row['store_name'],
                'domain' => $row['domain'],
                'category' => $row['category'],
                'revenue' => $row['revenue'],
                'traffic' => $row['traffic'],
                'growth' => $row['growth'],
                'growth_signal_label' => $row['growth_signal_label'],
                'founded' => $row['founded'],
                'products' => $row['products'],
                'logo_class' => $row['logo_class'],
                'logo_letter' => $row['logo_letter'],
                'stack' => $row['stack'],
            ]);
            $stores[(int) $store['id']] = $store;
            $signals[] = [
                'id' => (int) $row['id'],
                'type' => (string) $row['signal_type'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'occurred_label' => (string) $row['occurred_label'],
                'occurred_at_label' => (string) $row['occurred_at_label'],
                'store' => $store,
            ];
        }

        return [
            'signals' => $signals,
            'stores' => array_values($stores),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getSignalTypeCounts(): array
    {
        $rows = $this->pdo->query('
            SELECT signal_type, COUNT(*) AS signal_count
            FROM store_signals
            GROUP BY signal_type
        ')->fetchAll();

        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $count = (int) $row['signal_count'];
            $counts[(string) $row['signal_type']] = $count;
            $counts['all'] += $count;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMarketTrends(): array
    {
        $summary = $this->pdo->query('
            SELECT
                COUNT(*) AS total_stores,
                AVG(growth_percent) AS average_growth,
                AVG(estimated_monthly_revenue) AS average_revenue,
                SUM(monthly_traffic) AS total_traffic,
                SUM(product_count) AS total_products,
                SUM(growth_percent >= 10) AS high_growth_stores
            FROM stores
        ')->fetch() ?: [];

        $categories = $this->fetchTrendRows('
            SELECT
                category AS label,
                COUNT(*) AS store_count,
                AVG(growth_percent) AS average_growth,
                AVG(estimated_monthly_revenue) AS average_revenue
            FROM stores
            GROUP BY category
            ORDER BY store_count DESC
            LIMIT 8
        ');

        $growthCategories = $this->fetchTrendRows('
            SELECT
                category AS label,
                COUNT(*) AS store_count,
                AVG(growth_percent) AS average_growth,
                AVG(estimated_monthly_revenue) AS average_revenue
            FROM stores
            GROUP BY category
            HAVING store_count >= 3
            ORDER BY average_growth DESC
            LIMIT 8
        ');

        $countries = $this->fetchTrendRows('
            SELECT
                country AS label,
                COUNT(*) AS store_count,
                AVG(growth_percent) AS average_growth,
                AVG(estimated_monthly_revenue) AS average_revenue
            FROM stores
            WHERE country IS NOT NULL AND country <> \'\'
            GROUP BY country
            ORDER BY store_count DESC
            LIMIT 8
        ');

        $technologies = $this->fetchTrendRows('
            SELECT
                technology_name AS label,
                COUNT(DISTINCT store_id) AS store_count,
                AVG(monthly_cost) AS average_revenue,
                0 AS average_growth
            FROM store_technologies
            GROUP BY technology_name
            ORDER BY store_count DESC
            LIMIT 8
        ');

        return [
            'summary' => [
                'total_stores' => (int) ($summary['total_stores'] ?? 0),
                'average_growth' => round((float) ($summary['average_growth'] ?? 0), 1),
                'average_revenue' => $this->formatCompactMoney((float) ($summary['average_revenue'] ?? 0)),
                'total_traffic' => $this->formatCompactNumber((int) ($summary['total_traffic'] ?? 0)),
                'total_products' => $this->formatCompactNumber((int) ($summary['total_products'] ?? 0)),
                'high_growth_stores' => (int) ($summary['high_growth_stores'] ?? 0),
            ],
            'categories' => $categories,
            'growth_categories' => $growthCategories,
            'countries' => $countries,
            'technologies' => $technologies,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTrendRows(string $sql): array
    {
        return array_map(
            fn (array $row): array => [
                'label' => (string) ($row['label'] ?? 'Unknown'),
                'store_count' => (int) ($row['store_count'] ?? 0),
                'average_growth' => round((float) ($row['average_growth'] ?? 0), 1),
                'average_revenue' => $this->formatCompactMoney((float) ($row['average_revenue'] ?? 0)),
            ],
            $this->pdo->query($sql)->fetchAll()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getTechnologyIntelligence(string $technology = ''): array
    {
        $summary = $this->pdo->query('
            SELECT
                COUNT(*) AS detected_apps,
                COUNT(DISTINCT store_id) AS stores_with_apps,
                COUNT(DISTINCT technology_name) AS unique_apps,
                AVG(monthly_cost) AS average_app_cost
            FROM store_technologies
        ')->fetch() ?: [];

        $topApps = $this->pdo->query('
            SELECT
                technology_name,
                category,
                short_code,
                COUNT(DISTINCT store_id) AS store_count,
                AVG(monthly_cost) AS average_cost,
                MAX(last_seen_at) AS last_seen_at
            FROM store_technologies
            GROUP BY technology_name, category, short_code
            ORDER BY store_count DESC, technology_name ASC
            LIMIT 12
        ')->fetchAll();

        $categories = $this->pdo->query('
            SELECT
                category,
                COUNT(*) AS app_count,
                COUNT(DISTINCT store_id) AS store_count,
                COUNT(DISTINCT technology_name) AS unique_apps
            FROM store_technologies
            GROUP BY category
            ORDER BY store_count DESC
        ')->fetchAll();

        $selectedTechnology = $technology !== '' ? $technology : (string) ($topApps[0]['technology_name'] ?? '');
        $stores = $selectedTechnology !== '' ? $this->findStoresUsingTechnology($selectedTechnology, 12) : [];

        return [
            'summary' => [
                'detected_apps' => (int) ($summary['detected_apps'] ?? 0),
                'stores_with_apps' => (int) ($summary['stores_with_apps'] ?? 0),
                'unique_apps' => (int) ($summary['unique_apps'] ?? 0),
                'average_app_cost' => $this->formatCompactMoney((float) ($summary['average_app_cost'] ?? 0)),
            ],
            'top_apps' => array_map(
                fn (array $row): array => [
                    'name' => (string) $row['technology_name'],
                    'category' => (string) $row['category'],
                    'short_code' => (string) ($row['short_code'] ?? ''),
                    'store_count' => (int) $row['store_count'],
                    'average_cost' => $this->formatCompactMoney((float) ($row['average_cost'] ?? 0)),
                    'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                ],
                $topApps
            ),
            'categories' => array_map(
                static fn (array $row): array => [
                    'category' => (string) $row['category'],
                    'app_count' => (int) $row['app_count'],
                    'store_count' => (int) $row['store_count'],
                    'unique_apps' => (int) $row['unique_apps'],
                ],
                $categories
            ),
            'selected_technology' => $selectedTechnology,
            'stores' => $stores,
            'profiles' => $this->findProfilesForStores($stores),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findStoresUsingTechnology(string $technology, int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));

        $statement = $this->pdo->prepare('
            SELECT
                s.id,
                s.name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(stack.stack, \'\') AS stack
            FROM store_technologies selected
            INNER JOIN stores s ON s.id = selected.store_id
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(short_code ORDER BY id SEPARATOR \',\') AS stack
                FROM store_technologies
                GROUP BY store_id
            ) stack ON stack.store_id = s.id
            WHERE selected.technology_name = :technology
            ORDER BY s.estimated_monthly_revenue DESC
            LIMIT :limit
        ');
        $statement->bindValue(':technology', $technology);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'formatStore'], $statement->fetchAll());
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductIntelligence(string $category = ''): array
    {
        $summary = $this->pdo->query('
            SELECT
                COUNT(*) AS detected_products,
                COUNT(DISTINCT store_id) AS stores_with_products,
                COUNT(DISTINCT category) AS unique_categories,
                AVG(price) AS average_price,
                SUM(is_top_product = 1) AS top_products
            FROM products
        ')->fetch() ?: [];

        $categories = $this->pdo->query('
            SELECT
                COALESCE(NULLIF(category, \'\'), \'Uncategorized\') AS category,
                COUNT(*) AS product_count,
                COUNT(DISTINCT store_id) AS store_count,
                AVG(price) AS average_price
            FROM products
            GROUP BY COALESCE(NULLIF(category, \'\'), \'Uncategorized\')
            ORDER BY product_count DESC, category ASC
            LIMIT 12
        ')->fetchAll();

        $selectedCategory = $category !== '' ? $category : (string) ($categories[0]['category'] ?? '');
        $topProducts = $this->findProducts('', 12, true);
        $categoryProducts = $selectedCategory !== '' ? $this->findProducts($selectedCategory, 12) : [];
        $storesForProfiles = array_merge(
            array_map(static fn (array $product): array => $product['store'], $topProducts),
            array_map(static fn (array $product): array => $product['store'], $categoryProducts)
        );

        return [
            'summary' => [
                'detected_products' => (int) ($summary['detected_products'] ?? 0),
                'stores_with_products' => (int) ($summary['stores_with_products'] ?? 0),
                'unique_categories' => (int) ($summary['unique_categories'] ?? 0),
                'average_price' => $this->formatMoney((float) ($summary['average_price'] ?? 0)),
                'top_products' => (int) ($summary['top_products'] ?? 0),
            ],
            'categories' => array_map(
                fn (array $row): array => [
                    'category' => (string) $row['category'],
                    'product_count' => (int) $row['product_count'],
                    'store_count' => (int) $row['store_count'],
                    'average_price' => $this->formatMoney((float) ($row['average_price'] ?? 0)),
                ],
                $categories
            ),
            'selected_category' => $selectedCategory,
            'top_products' => $topProducts,
            'category_products' => $categoryProducts,
            'profiles' => $this->findProfilesForStores($storesForProfiles),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findProducts(string $category = '', int $limit = 12, bool $topOnly = false): array
    {
        $limit = max(1, min($limit, 50));
        $where = [];

        if ($category !== '') {
            $where[] = 'COALESCE(NULLIF(p.category, \'\'), \'Uncategorized\') = :category';
        }
        if ($topOnly) {
            $where[] = 'p.is_top_product = 1';
        }

        $sql = '
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                COALESCE(NULLIF(p.category, \'\'), \'Uncategorized\') AS product_category,
                p.price,
                p.currency_symbol,
                p.product_url,
                p.is_top_product,
                p.last_seen_at,
                s.id,
                s.name,
                s.domain,
                s.category,
                s.estimated_monthly_revenue AS revenue,
                s.monthly_traffic AS traffic,
                s.growth_percent AS growth,
                s.growth_signal AS growth_signal_label,
                s.founded_year AS founded,
                s.product_count AS products,
                s.logo_class,
                s.logo_letter,
                COALESCE(stack.stack, \'\') AS stack
            FROM products p
            INNER JOIN stores s ON s.id = p.store_id
            LEFT JOIN (
                SELECT
                    store_id,
                    GROUP_CONCAT(short_code ORDER BY id SEPARATOR \',\') AS stack
                FROM store_technologies
                GROUP BY store_id
            ) stack ON stack.store_id = s.id
        ';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= '
            ORDER BY p.is_top_product DESC, p.price DESC, s.estimated_monthly_revenue DESC
            LIMIT :limit
        ';

        $statement = $this->pdo->prepare($sql);
        if ($category !== '') {
            $statement->bindValue(':category', $category);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): array => $this->formatProductWithStore($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatProductWithStore(array $row): array
    {
        $symbol = (string) ($row['currency_symbol'] ?: '$');
        $price = (float) ($row['price'] ?? 0);

        return [
            'id' => (int) $row['product_id'],
            'name' => (string) $row['product_name'],
            'category' => (string) $row['product_category'],
            'price' => $price,
            'price_label' => $symbol . number_format($price, $price === floor($price) ? 0 : 2),
            'product_url' => (string) ($row['product_url'] ?? ''),
            'is_top_product' => (bool) $row['is_top_product'],
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'store' => $this->formatStore($row),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $stores
     * @return array<string, array<string, mixed>>
     */
    public function findProfilesForStores(array $stores): array
    {
        $profiles = [];

        foreach ($stores as $store) {
            $profiles[$store['name']] = $this->findProfile((int) $store['id']);
        }

        return $profiles;
    }

    /**
     * @return array<string, mixed>
     */
    private function findProfile(int $storeId): array
    {
        $statement = $this->pdo->prepare('
            SELECT
                headquarters AS location,
                country,
                employee_range AS employees,
                public_email AS email,
                public_phone AS phone,
                store_language AS language,
                currency,
                average_price,
                monthly_orders,
                conversion_rate,
                social_total,
                instagram_followers,
                tiktok_followers,
                facebook_followers
            FROM stores
            WHERE id = :id
        ');
        $statement->execute(['id' => $storeId]);
        $profile = $statement->fetch() ?: [];

        $profile['avgPrice'] = $this->formatMoney((float) ($profile['average_price'] ?? 0));
        $profile['orders'] = $this->formatCompactNumber((int) ($profile['monthly_orders'] ?? 0));
        $profile['conversion'] = number_format((float) ($profile['conversion_rate'] ?? 0), 1) . '%';
        $profile['social'] = $this->formatCompactNumber((int) ($profile['social_total'] ?? 0));
        $profile['instagram'] = $this->formatCompactNumber((int) ($profile['instagram_followers'] ?? 0));
        $profile['tiktok'] = $this->formatCompactNumber((int) ($profile['tiktok_followers'] ?? 0));
        $profile['facebook'] = $this->formatCompactNumber((int) ($profile['facebook_followers'] ?? 0));
        $profile['apps'] = $this->fetchRows(
            'SELECT technology_name, category, DATE_FORMAT(detected_at, \'%b %Y\') FROM store_technologies WHERE store_id = :store_id ORDER BY detected_at DESC',
            $storeId
        );
        $profile['products'] = $this->fetchRows(
            'SELECT name, category, CONCAT(currency_symbol, FORMAT(price, 0)) FROM products WHERE store_id = :store_id ORDER BY is_top_product DESC, id DESC LIMIT 10',
            $storeId
        );
        $profile['signals'] = $this->fetchRows(
            'SELECT occurred_label, title, description FROM store_signals WHERE store_id = :store_id ORDER BY occurred_at DESC LIMIT 10',
            $storeId
        );

        return $profile;
    }

    /**
     * @return array<string, int|string>
     */
    public function getDashboardStats(): array
    {
        $row = $this->pdo->query('
            SELECT
                COUNT(*) AS matching_stores,
                SUM(created_at >= CURRENT_DATE - INTERVAL 7 DAY) AS new_this_week,
                SUM(growth_percent >= 10) AS high_growth_stores,
                SUM(updated_at >= CURRENT_DATE - INTERVAL 7 DAY) AS updated_stores,
                AVG(estimated_monthly_revenue) AS average_revenue
            FROM stores
        ')->fetch() ?: [];

        return [
            'matching_stores' => (int) ($row['matching_stores'] ?? 0),
            'new_this_week' => (int) ($row['new_this_week'] ?? 0),
            'median_revenue' => $this->formatCompactMoney((float) ($row['average_revenue'] ?? 0)),
            'high_growth_stores' => (int) ($row['high_growth_stores'] ?? 0),
            'updated_stores' => (int) ($row['updated_stores'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function fetchRows(string $sql, int $storeId): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['store_id' => $storeId]);
        return array_map('array_values', $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatStore(array $row): array
    {
        $stack = array_values(array_filter(explode(',', (string) ($row['stack'] ?? ''))));

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'domain' => (string) $row['domain'],
            'category' => (string) $row['category'],
            'revenue' => (float) $row['revenue'],
            'revenueLabel' => $this->formatCompactMoney((float) $row['revenue']),
            'traffic' => (int) $row['traffic'],
            'trafficLabel' => $this->formatCompactNumber((int) $row['traffic']),
            'growth' => (float) $row['growth'],
            'signal' => (string) $row['growth_signal_label'],
            'logo' => (string) ($row['logo_letter'] ?: mb_substr((string) $row['name'], 0, 1)),
            'logoClass' => (string) ($row['logo_class'] ?: 'logo-allbirds'),
            'stack' => $stack,
            'founded' => (string) $row['founded'],
            'products' => number_format((int) $row['products']),
        ];
    }

    private function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, $amount === floor($amount) ? 0 : 2);
    }

    private function formatCompactMoney(float $amount): string
    {
        return '$' . $this->formatCompactNumber((int) $amount);
    }

    private function formatCompactNumber(int $number): string
    {
        if ($number >= 1_000_000) {
            return rtrim(rtrim(number_format($number / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($number >= 1_000) {
            return rtrim(rtrim(number_format($number / 1_000, 1), '0'), '.') . 'K';
        }
        return number_format($number);
    }
}
