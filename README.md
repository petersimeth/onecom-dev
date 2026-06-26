# ShopSignal PHP prototype

The dashboard is now served by `index.php`. It uses PDO and automatically reads
store data from a database when `DB_DSN` is configured. Without credentials it
continues to use the frontend sample data.

## Run locally

```bash
php -S 127.0.0.1:4173
```

Open `http://127.0.0.1:4173/index.php`.

## Connect MySQL

1. Create a database and run `database/schema.sql`.
2. Configure the environment:

```bash
export DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=shopsignal;charset=utf8mb4"
export DB_USER="shopsignal"
export DB_PASSWORD="your-password"
php -S 127.0.0.1:4173
```

The page and `api/stores.php` will then use database rows automatically.

## Deploy in a onecom.io subfolder

The project is subfolder-safe. A suggested test URL is:

```text
https://onecom.io/shopsignal/
```

Upload the project files into the matching public web directory, for example:

```text
public_html/shopsignal/
```

The deployed folder should contain:

```text
shopsignal/
├── .htaccess
├── api/
├── database/
├── scripts/
├── src/
├── app.js
├── index.php
└── styles.css
```

For a database connection on shared hosting:

1. Create a MySQL database in the hosting control panel.
2. Import `database/schema.sql`.
3. Import `database/seed.sql` to load the six dashboard stores.
4. Copy `config.example.php` to `config.local.php`.
5. Enter the host-provided database name, username, and password.

`config.local.php` is intentionally excluded from Git and blocked from web
access by `.htaccess`.

The application derives its base path from the current request, so assets and
the API work at `/shopsignal/`, `/store-data/`, or another subfolder without
source changes.

## Verify MySQL

After configuring the database, open:

```text
https://onecom.io/shopsignal/api/status.php
```

A successful response looks like:

```json
{"connected":true,"driver":"mysql","database":"shopsignal","stores":6}
```

The dashboard header will also change from `Using sample data` to
`Live database connection`.

If the status endpoint reports a failed connection, temporarily set:

```php
'db_debug' => true,
```

in `config.local.php`, reload `api/status.php`, and inspect the `diagnostic`
field. Turn debugging off again after fixing the connection.

Common shared-hosting causes:

- The database host is not `localhost`; use the hostname shown in the panel.
- The provider prefixes database and user names, such as `account_shopsignal`.
- The MySQL user has not been assigned to the database.
- The user is assigned but lacks `SELECT`, `INSERT`, `UPDATE`, and `DELETE`.
- The password contains a typo or was changed after creating the config file.
- `schema.sql` has not been imported into the database named in the DSN.

## Generate demo stores for performance testing

After the database connection works, you can create a larger mock dataset.
The generator updates existing generated stores with the same prefix instead
of creating duplicates.

With SSH/CLI access:

```bash
php scripts/generate-demo-stores.php --count=20000 --batch=500 --prefix=perf
```

On shared hosting without SSH, add a temporary token to `config.local.php`:

```php
'seed_token' => 'replace-with-a-long-random-secret',
```

Then open:

```text
https://onecom.io/shopsignal/scripts/generate-demo-stores.php?token=replace-with-a-long-random-secret&count=20000&prefix=perf
```

The default run creates 20,000 stores plus mock technologies, products, and
signals so the list and detail views have realistic joined data. When you are
done, remove `seed_token` from `config.local.php` or delete the script from the
server.

## Saved lists

The Saved lists feature uses:

```text
api/lists.php
saved_lists
saved_list_stores
```

`api/lists.php` creates the two saved-list tables automatically on first use
and ensures a default `Prospects` list exists. Open a store detail panel and
click `Add to list`, then open `Saved lists` in the sidebar.

## Signals

The Signals view uses:

```text
api/signals.php
store_signals
```

It shows recent store activity joined with store profile data. Signal cards can
open the store detail drawer or save the store to the default saved list.

## Market trends

The Market trends view uses:

```text
api/market.php
stores
store_technologies
```

It summarizes category share, fastest-growing categories, country
concentration, and technology adoption from the current database rows.

## Apps & tech

The Apps & tech view uses:

```text
api/apps.php
store_technologies
stores
```

It summarizes detected apps, app categories, estimated app cost, and the top
stores using a selected technology.

## Products

The Products view uses:

```text
api/products.php
products
stores
```

It summarizes detected products, product categories, average price, featured
top products, and the stores attached to selected catalog categories.

## Store detail API

The store drawer can fetch full store data on demand:

```text
api/store.php?id=123
```

The endpoint returns the formatted store row plus profile data, detected apps,
top products, and recent signals for the selected store.

## CSV export

The export endpoint streams CSV files from the server:

```text
api/export.php?scope=stores&category=Beauty&technology=Klaviyo&min_growth=10
api/export.php?scope=list&list_id=1
```

Explorer exports reuse the active search, filters, and sort order. Saved-list
exports use the selected saved list. The current safety cap is 5,000 rows per
download.

## Saved views / segments

Saved Explorer views use:

```text
api/segments.php
saved_segments
```

Click `Save this view` to persist the current search, filters, and sort order.
Saved view pills appear above the results table and can be clicked to reload a
segment or removed with the small `×`.
