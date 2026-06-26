<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

shopSignalRequireAuth();

function adminCsvTemplateHeaders(): array
{
    return [
        'name',
        'domain',
        'category',
        'country',
        'headquarters',
        'estimated_monthly_revenue',
        'monthly_traffic',
        'monthly_orders',
        'growth_percent',
        'growth_signal',
        'product_count',
        'average_price',
        'founded_year',
        'employee_range',
        'public_email',
        'public_phone',
        'store_language',
        'currency',
        'instagram_followers',
        'tiktok_followers',
        'facebook_followers',
    ];
}

function adminDownloadCsvTemplate(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shopsignal-store-import-template.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, adminCsvTemplateHeaders());
    fputcsv($output, [
        'Example Store',
        'examplestore.com',
        'Beauty',
        'United States',
        'Austin, TX',
        '125000',
        '48000',
        '1100',
        '12.5',
        'High',
        '240',
        '54.90',
        '2020',
        '11-50',
        'hello@examplestore.com',
        '+1 555 0100',
        'English',
        'USD',
        '25000',
        '8000',
        '12000',
    ]);
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

function adminImportStores(PDO $pdo, string $path): array
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
    $required = ['domain'];
    foreach ($required as $requiredHeader) {
        if (!in_array($requiredHeader, $headers, true)) {
            throw new RuntimeException('Missing required CSV column: ' . $requiredHeader);
        }
    }

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

    $summary = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }

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
            if ($statement->rowCount() === 1) {
                $summary['created']++;
            } else {
                $summary['updated']++;
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

if (isset($_GET['template'])) {
    adminDownloadCsvTemplate();
}

$result = null;
$error = '';
$databaseConnected = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::connect(shopSignalConfig());
        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }
        $databaseConnected = true;

        if (!isset($_FILES['store_csv']) || ($_FILES['store_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please choose a CSV file to import.');
        }

        $result = adminImportStores($pdo, (string) $_FILES['store_csv']['tmp_name']);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
} else {
    $databaseConnected = Database::connect(shopSignalConfig()) !== null;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
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
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('admin.php?template=1')) ?>">Download CSV schema</a>
          <a class="button secondary" href="<?= htmlspecialchars(shopSignalAssetUrl('index.php')) ?>">Back to app</a>
        </div>
      </div>

      <section class="admin-hero">
        <p class="eyebrow"><span></span> Admin import</p>
        <h1>Import Shopify stores from CSV.</h1>
        <p>Upload store rows, update existing domains, and use the downloadable schema as your source template.</p>
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
          <?php if (is_array($result)): ?>
            <div class="import-summary">
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
            <label>
              CSV file
              <input type="file" name="store_csv" accept=".csv,text/csv" required />
            </label>
            <button class="button primary" type="submit">Import stores</button>
          </form>
        </article>

        <article class="admin-card">
          <div class="section-heading">
            <h3>CSV schema</h3>
            <span>Required: domain</span>
          </div>
          <p class="admin-note">Use these column names. Empty optional values are imported as safe defaults.</p>
          <div class="schema-list">
            <?php foreach (adminCsvTemplateHeaders() as $header): ?>
              <code><?= htmlspecialchars($header) ?></code>
            <?php endforeach; ?>
          </div>
        </article>
      </section>
    </main>
  </body>
</html>
