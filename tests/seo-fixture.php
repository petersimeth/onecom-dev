<?php
declare(strict_types=1);

$path = $argv[1] ?? sys_get_temp_dir() . '/shopsignal-seo.sqlite';
if (is_file($path)) {
    unlink($path);
}
$pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('
    CREATE TABLE stores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        domain TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL,
        founded_year INTEGER,
        headquarters TEXT,
        country TEXT,
        product_count INTEGER DEFAULT 0,
        store_language TEXT,
        currency TEXT,
        logo_letter TEXT,
        logo_class TEXT,
        created_at TEXT,
        updated_at TEXT
    );
    CREATE TABLE store_technologies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        technology_name TEXT NOT NULL,
        category TEXT NOT NULL,
        last_seen_at TEXT
    );
    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        category TEXT,
        price REAL DEFAULT 0,
        currency_symbol TEXT DEFAULT \'$\',
        is_top_product INTEGER DEFAULT 0,
        last_seen_at TEXT
    );
    CREATE TABLE store_signals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        signal_type TEXT NOT NULL,
        title TEXT NOT NULL,
        occurred_label TEXT NOT NULL,
        occurred_at TEXT NOT NULL
    );
');

$stores = [
    ['Allbirds', 'allbirds.com', 'Footwear', 2016, 'San Francisco, CA', 'United States', 278, 'A'],
    ['Gymshark', 'gymshark.com', 'Apparel', 2012, 'Solihull, England', 'United Kingdom', 1842, 'G'],
    ['Brooklinen', 'brooklinen.com', 'Home & Living', 2014, 'Brooklyn, NY', 'United States', 412, 'B'],
    ['Glossier', 'glossier.com', 'Beauty', 2014, 'New York, NY', 'United States', 186, 'G'],
];
$insertStore = $pdo->prepare('INSERT INTO stores (name, domain, category, founded_year, headquarters, country, product_count, store_language, currency, logo_letter, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'English\', \'USD\', ?, datetime(\'now\'), datetime(\'now\'))');
foreach ($stores as $store) {
    $insertStore->execute($store);
}

$pdo->exec("INSERT INTO store_technologies (store_id, technology_name, category, last_seen_at) VALUES
    (1, 'Klaviyo', 'Email marketing', date('now')),
    (1, 'Yotpo', 'Reviews', date('now')),
    (2, 'Klaviyo', 'Email marketing', date('now')),
    (3, 'Klaviyo', 'Email marketing', date('now')),
    (4, 'Klaviyo', 'Email marketing', date('now'));
    INSERT INTO products (store_id, name, category, price, currency_symbol, is_top_product, last_seen_at) VALUES
    (1, 'Tree Runner', 'Footwear', 98, '$', 1, date('now')),
    (1, 'Wool Runner', 'Footwear', 110, '$', 1, date('now'));
    INSERT INTO store_signals (store_id, signal_type, title, occurred_label, occurred_at) VALUES
    (1, 'traffic', 'Traffic spike', '2 days ago', datetime('now', '-2 days'));");

echo $path . PHP_EOL;
