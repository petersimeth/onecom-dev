<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

set_time_limit(0);

$isCli = PHP_SAPI === 'cli';
$startedAt = microtime(true);

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

/**
 * @return never
 */
function finishDemoSeed(array $payload, int $statusCode = 200): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            echo $key . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES) . PHP_EOL;
            continue;
        }
        echo $key . ': ' . (string) $value . PHP_EOL;
    }
    exit;
}

function demoSeedOption(string $name, string $default = ''): string
{
    if (PHP_SAPI === 'cli') {
        $options = getopt('', [
            'count::',
            'batch::',
            'prefix::',
            'related::',
            'replace-related::',
            'help',
        ]);

        if (isset($options['help'])) {
            finishDemoSeed([
                'usage' => 'php scripts/generate-demo-stores.php --count=20000 --batch=500 --prefix=perf --related=1',
                'options' => [
                    'count' => 'Number of demo stores to create. Default: 20000.',
                    'batch' => 'Rows per transaction. Default: 500.',
                    'prefix' => 'Domain/name prefix for generated rows. Default: demo.',
                    'related' => 'Create mock technologies, products, and signals. 1 or 0. Default: 1.',
                    'replace-related' => 'Delete related rows for this prefix before inserting. 1 or 0. Default: 1.',
                ],
            ]);
        }

        return isset($options[$name]) ? (string) $options[$name] : $default;
    }

    return isset($_GET[$name]) ? (string) $_GET[$name] : $default;
}

function demoSeedBoolOption(string $name, bool $default): bool
{
    $raw = strtolower(demoSeedOption($name, $default ? '1' : '0'));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function demoSeedPick(array $items, int $index): mixed
{
    return $items[$index % count($items)];
}

function demoSeedSlug(string $value): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'demo');
    return trim($slug, '-') ?: 'demo';
}

function demoSeedMoney(int $index, int $min, int $max): int
{
    $range = $max - $min;
    return $min + (($index * 7919) % $range);
}

try {
    $config = shopSignalConfig();

    if (!$isCli) {
        $configuredToken = (string) ($config['seed_token'] ?? '');
        $providedToken = (string) ($_GET['token'] ?? '');

        if ($configuredToken === '' || !hash_equals($configuredToken, $providedToken)) {
            finishDemoSeed([
                'ok' => false,
                'message' => 'Missing or invalid seed token. Add seed_token to config.local.php and pass ?token=...',
            ], 403);
        }
    }

    $count = max(1, min(200000, (int) demoSeedOption('count', '20000')));
    $batchSize = max(50, min(5000, (int) demoSeedOption('batch', '500')));
    $prefix = demoSeedSlug(demoSeedOption('prefix', 'demo'));
    $withRelated = demoSeedBoolOption('related', true);
    $replaceRelated = demoSeedBoolOption('replace-related', true);

    $pdo = Database::connect($config);
    if ($pdo === null) {
        throw new RuntimeException('No database configured. Check config.local.php.');
    }

    $categories = [
        'Apparel', 'Beauty', 'Home', 'Food & Beverage', 'Jewelry', 'Electronics',
        'Pets', 'Fitness', 'Outdoor', 'Baby', 'Wellness', 'Accessories',
    ];
    $plans = ['Shopify', 'Shopify Advanced', 'Shopify Plus', 'Starter'];
    $countries = ['United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'Netherlands', 'France', 'Denmark', 'Sweden', 'Singapore'];
    $cities = ['Austin', 'London', 'Toronto', 'Melbourne', 'Berlin', 'Amsterdam', 'Paris', 'Copenhagen', 'Stockholm', 'Singapore'];
    $currencies = ['USD', 'GBP', 'CAD', 'AUD', 'EUR', 'EUR', 'EUR', 'DKK', 'SEK', 'SGD'];
    $languages = ['English', 'English', 'English', 'English', 'German', 'Dutch', 'French', 'Danish', 'Swedish', 'English'];
    $employeeRanges = ['1-10', '11-50', '51-200', '201-500', '501-1000'];
    $adjectives = ['North', 'Bright', 'Urban', 'Kind', 'Fresh', 'Wild', 'Modern', 'Little', 'True', 'Golden', 'Daily', 'Bold'];
    $nouns = ['Supply', 'Goods', 'Studio', 'Market', 'Lab', 'Collective', 'House', 'Club', 'Works', 'Co', 'Project', 'Store'];
    $technologySets = [
        [['Klaviyo', 'Email', 'KV'], ['Recharge', 'Subscriptions', 'RC'], ['Gorgias', 'Support', 'GG']],
        [['Yotpo', 'Reviews', 'YO'], ['Attentive', 'SMS', 'AT'], ['Postscript', 'SMS', 'PS']],
        [['Shop Pay', 'Payments', 'SP'], ['Afterpay', 'Payments', 'AP'], ['Okendo', 'Reviews', 'OK']],
        [['Google Analytics 4', 'Analytics', 'GA4'], ['Meta Pixel', 'Ads', 'MP'], ['Triple Whale', 'Analytics', 'TW']],
        [['Klaviyo', 'Email', 'KV'], ['Loop Returns', 'Returns', 'LR'], ['Smile.io', 'Loyalty', 'SM']],
    ];
    $signalTypes = ['growth', 'technology', 'product', 'traffic', 'social'];

    if ($replaceRelated && $withRelated) {
        $pdo->prepare('
            DELETE st
            FROM store_technologies st
            INNER JOIN stores s ON s.id = st.store_id
            WHERE s.domain LIKE :domain_pattern
        ')->execute(['domain_pattern' => $prefix . '-shop-%.mock.test']);

        $pdo->prepare('
            DELETE p
            FROM products p
            INNER JOIN stores s ON s.id = p.store_id
            WHERE s.domain LIKE :domain_pattern
        ')->execute(['domain_pattern' => $prefix . '-shop-%.mock.test']);

        $pdo->prepare('
            DELETE ss
            FROM store_signals ss
            INNER JOIN stores s ON s.id = ss.store_id
            WHERE s.domain LIKE :domain_pattern
        ')->execute(['domain_pattern' => $prefix . '-shop-%.mock.test']);
    }

    $storeStatement = $pdo->prepare('
        INSERT INTO stores (
            name,
            domain,
            category,
            shopify_plan,
            growth_signal,
            growth_percent,
            estimated_monthly_revenue,
            monthly_traffic,
            monthly_orders,
            conversion_rate,
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
            logo_class,
            created_at,
            updated_at
        ) VALUES (
            :name,
            :domain,
            :category,
            :shopify_plan,
            :growth_signal,
            :growth_percent,
            :estimated_monthly_revenue,
            :monthly_traffic,
            :monthly_orders,
            :conversion_rate,
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
            :logo_class,
            DATE_SUB(NOW(), INTERVAL :created_days_ago DAY),
            DATE_SUB(NOW(), INTERVAL :updated_days_ago DAY)
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            name = VALUES(name),
            category = VALUES(category),
            shopify_plan = VALUES(shopify_plan),
            growth_signal = VALUES(growth_signal),
            growth_percent = VALUES(growth_percent),
            estimated_monthly_revenue = VALUES(estimated_monthly_revenue),
            monthly_traffic = VALUES(monthly_traffic),
            monthly_orders = VALUES(monthly_orders),
            conversion_rate = VALUES(conversion_rate),
            average_price = VALUES(average_price),
            product_count = VALUES(product_count),
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
            updated_at = VALUES(updated_at)
    ');

    $technologyStatement = $pdo->prepare('
        INSERT INTO store_technologies (
            store_id,
            technology_name,
            category,
            short_code,
            detected_at,
            last_seen_at,
            monthly_cost
        ) VALUES (
            :store_id,
            :technology_name,
            :category,
            :short_code,
            DATE_SUB(CURRENT_DATE, INTERVAL :detected_days_ago DAY),
            CURRENT_DATE,
            :monthly_cost
        )
    ');

    $productStatement = $pdo->prepare('
        INSERT INTO products (
            store_id,
            name,
            category,
            price,
            currency_symbol,
            is_top_product,
            first_seen_at,
            last_seen_at
        ) VALUES (
            :store_id,
            :name,
            :category,
            :price,
            :currency_symbol,
            1,
            DATE_SUB(CURRENT_DATE, INTERVAL :first_seen_days_ago DAY),
            CURRENT_DATE
        )
    ');

    $signalStatement = $pdo->prepare('
        INSERT INTO store_signals (
            store_id,
            signal_type,
            title,
            description,
            occurred_at,
            occurred_label
        ) VALUES (
            :store_id,
            :signal_type,
            :title,
            :description,
            DATE_SUB(NOW(), INTERVAL :occurred_hours_ago HOUR),
            :occurred_label
        )
    ');

    $insertedStores = 0;
    $relatedRows = 0;

    for ($start = 1; $start <= $count; $start += $batchSize) {
        $end = min($count, $start + $batchSize - 1);
        $pdo->beginTransaction();

        for ($i = $start; $i <= $end; $i++) {
            $category = demoSeedPick($categories, $i);
            $countryIndex = $i % count($countries);
            $revenue = demoSeedMoney($i, 12000, 2400000);
            $traffic = demoSeedMoney($i, 1500, 1800000);
            $orders = max(20, (int) round($traffic * ((1.2 + ($i % 37) / 10) / 100)));
            $growth = round((($i * 17) % 2900) / 100 - 4, 2);
            $signal = $growth >= 10 ? 'High' : ($growth >= 3 ? 'Medium' : 'Low');
            $adjective = demoSeedPick($adjectives, $i);
            $noun = demoSeedPick($nouns, (int) floor($i / 3));
            $name = $adjective . ' ' . $noun . ' ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $domain = $prefix . '-shop-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT) . '.mock.test';
            $currency = demoSeedPick($currencies, $countryIndex);
            $currencySymbol = in_array($currency, ['EUR'], true) ? '€' : (in_array($currency, ['GBP'], true) ? '£' : '$');

            $storeStatement->execute([
                'name' => $name,
                'domain' => $domain,
                'category' => $category,
                'shopify_plan' => demoSeedPick($plans, (int) floor($i / 5)),
                'growth_signal' => $signal,
                'growth_percent' => $growth,
                'estimated_monthly_revenue' => $revenue,
                'monthly_traffic' => $traffic,
                'monthly_orders' => $orders,
                'conversion_rate' => round(1.2 + (($i * 13) % 460) / 100, 2),
                'average_price' => round(18 + (($i * 31) % 220), 2),
                'product_count' => 25 + (($i * 19) % 2400),
                'founded_year' => 2008 + ($i % 17),
                'headquarters' => demoSeedPick($cities, $countryIndex) . ', ' . demoSeedPick($countries, $countryIndex),
                'country' => demoSeedPick($countries, $countryIndex),
                'employee_range' => demoSeedPick($employeeRanges, (int) floor($i / 7)),
                'public_email' => 'hello@' . $domain,
                'public_phone' => null,
                'store_language' => demoSeedPick($languages, $countryIndex),
                'currency' => $currency,
                'social_total' => $traffic + (($i * 97) % 750000),
                'instagram_followers' => (($i * 151) % 900000),
                'tiktok_followers' => (($i * 89) % 700000),
                'facebook_followers' => (($i * 47) % 300000),
                'logo_letter' => strtoupper(substr($adjective, 0, 1)),
                'logo_class' => 'logo-' . demoSeedPick(['allbirds', 'gymshark', 'brooklinen', 'glossier', 'beardbrand', 'kylie'], $i),
                'created_days_ago' => $i % 365,
                'updated_days_ago' => $i % 21,
            ]);

            $storeId = (int) $pdo->lastInsertId();
            $insertedStores++;

            if (!$withRelated) {
                continue;
            }

            foreach (demoSeedPick($technologySets, $i) as $technologyIndex => $technology) {
                $technologyStatement->execute([
                    'store_id' => $storeId,
                    'technology_name' => $technology[0],
                    'category' => $technology[1],
                    'short_code' => $technology[2],
                    'detected_days_ago' => 30 + (($i + $technologyIndex * 19) % 730),
                    'monthly_cost' => 15 + (($i + $technologyIndex * 41) % 450),
                ]);
                $relatedRows++;
            }

            $productStatement->execute([
                'store_id' => $storeId,
                'name' => $category . ' Hero Product ' . str_pad((string) (($i % 999) + 1), 3, '0', STR_PAD_LEFT),
                'category' => $category,
                'price' => 12 + (($i * 29) % 380),
                'currency_symbol' => $currencySymbol,
                'first_seen_days_ago' => 10 + ($i % 500),
            ]);
            $relatedRows++;

            $signalStatement->execute([
                'store_id' => $storeId,
                'signal_type' => demoSeedPick($signalTypes, $i),
                'title' => $signal === 'High' ? 'High-growth storefront detected' : 'Storefront profile updated',
                'description' => 'Mock signal generated for performance testing and UI validation.',
                'occurred_hours_ago' => 2 + ($i % 240),
                'occurred_label' => ($i % 24) . 'h ago',
            ]);
            $relatedRows++;
        }

        $pdo->commit();
    }

    $totalStores = (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn();

    finishDemoSeed([
        'ok' => true,
        'message' => 'Demo stores generated.',
        'requested_count' => $count,
        'processed_stores' => $insertedStores,
        'related_rows_created' => $relatedRows,
        'total_stores' => $totalStores,
        'prefix' => $prefix,
        'seconds' => round(microtime(true) - $startedAt, 2),
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    finishDemoSeed([
        'ok' => false,
        'message' => $exception->getMessage(),
        'type' => get_class($exception),
    ], 500);
}
