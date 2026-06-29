# ShopSignal PHP prototype

The dashboard is now served by `index.php`. It uses PDO and automatically reads
store data from a database when `DB_DSN` is configured. Without credentials it
continues to use the frontend sample data.

## Run locally

```bash
php -S 127.0.0.1:4173
```

Open `http://127.0.0.1:4173/index.php`.

## Project structure

- `src/` contains reusable application services, repositories, database access,
  and the shared JSON API response contract.
- `api/` contains thin HTTP controllers that validate input and delegate to the
  application layer.
- Top-level PHP files render pages or handle browser workflows such as login,
  billing, and administration.
- `database/` contains the MySQL schema and development seed data.
- `tests/` contains executable PHP contract and fixture checks.

Administrative user mutations are handled by `AdminUserService`; JSON endpoints
use `JsonApi` for consistent headers, status codes, encoding, and safe diagnostic
responses. `admin.php` is restricted with `shopSignalRequireAdmin()`.

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

## Secure local crawler ingestion

The local Python spider can securely push stores, detected technologies,
products, and change signals into the IONOS MySQL database through:

```text
https://onecom.io/shopsignal/api/ingest.php
```

The database is never exposed remotely. The PHP endpoint authenticates each
raw JSON body with HMAC-SHA256, requires a fresh timestamp and one-time nonce,
limits payload size and batch count, and applies every batch in one database
transaction. Reusing a nonce is rejected; retrying a completed batch returns
its original summary without duplicating data.

Generate a secret on your home machine:

```bash
python3 -c "import secrets; print(secrets.token_hex(32))"
```

Add it to the server's protected `config.local.php`:

```php
'crawler_ingest_enabled' => true,
'crawler_ingest_require_https' => true,
'crawler_ingest_max_batch' => 100,
'crawler_ingest_max_bytes' => 2097152,
'crawler_ingest_clock_skew' => 300,
'crawler_ingest_keys' => [
    'home-scraper' => 'PASTE_THE_64_CHARACTER_SECRET_HERE',
],
```

Do not put that secret in this repository or a command-line argument. In the
scraper terminal, read it without echoing it and export it for that session:

```bash
read -s SHOPSIGNAL_INGEST_SECRET
export SHOPSIGNAL_INGEST_SECRET
export SHOPSIGNAL_INGEST_KEY_ID='home-scraper'
export SHOPSIGNAL_INGEST_URL='https://onecom.io/shopsignal/api/ingest.php'
```

Then run:

```bash
shopify-spider sync --database shopify_spider.sqlite3 --dry-run
shopify-spider sync --database shopify_spider.sqlite3 --full
```

Later runs omit `--full` and send only stores observed since the previous
successful sync. Sync is merge-only: missing local records never delete server
data. Add a second key ID before removing the old one to rotate secrets without
downtime. Recent batches and record counts appear on the admin dashboard.

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

## Public SEO directory

The authenticated dashboard is marked `noindex`. Search engines instead receive
server-rendered public pages with canonical URLs, unique metadata, JSON-LD, and
crawlable internal links:

```text
/shopsignal/stores/
/shopsignal/stores/123-example-com
/shopsignal/categories/beauty/
/shopsignal/countries/united-states/
/shopsignal/apps/klaviyo/
/shopsignal/methodology/
```

The sitemap index is available at:

```text
https://onecom.io/shopsignal/sitemap.xml
```

Store sitemaps are split automatically at 50,000 URLs. Category, country, and
app pages are included only when at least three matching stores exist. Thin
directory pages remain crawlable but use `noindex` until they reach that
threshold.

Add the Search Console HTML-tag token to `config.local.php` if desired:

```php
'google_site_verification' => 'paste-token-only-here',
```

Important: search engines only use `robots.txt` from the domain root. The
project exposes `/shopsignal/robots.txt` as a ready reference, but for the live
subfolder deployment also add these rules to `https://onecom.io/robots.txt`:

```text
User-agent: *
Disallow: /shopsignal/api/
Disallow: /shopsignal/scripts/

Sitemap: https://onecom.io/shopsignal/sitemap.xml
```

After upload, create a Google Search Console domain property, submit the
sitemap index, and inspect one store URL, one category URL, and the methodology
page before requesting indexing.

## Stripe Pro subscriptions

ShopSignal can use Stripe-hosted Checkout for recurring Pro subscriptions.
Create a recurring Price in Stripe, then add these server-only settings to
`config.local.php`:

```php
'stripe_secret_key' => 'sk_test_...',
'stripe_webhook_secret' => 'whsec_...',
'stripe_pro_price_id' => 'price_...',
'stripe_pro_price_label' => '$29 / month',
```

In the Stripe Dashboard, create a webhook endpoint pointing to:

```text
https://onecom.io/shopsignal/stripe-webhook.php
```

Subscribe it to these events:

```text
checkout.session.completed
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
invoice.paid
invoice.payment_failed
```

Checkout activates Pro through the webhook. Subscription updates, failed
payments, cancellations, and current-period dates are stored on the user row.
Users can open Stripe's customer portal from `profile.php`; configure the
portal in Stripe before testing that button. Keep test-mode keys in place until
the full checkout, renewal, failed-payment, and cancellation flows are verified.

## Simple login/auth

The app supports database users plus the older config-password fallback.
Registered users are stored in:

```text
users
```

Registration is available at `register.php`. Registration first creates a
pending registration, not a user row. The real user is created only after the
email confirmation link is opened. The first confirmed user becomes an admin;
later users are standard users until an admin changes their role in `users.php`
or the Users panel in `admin.php`. Every new user starts on the `free` plan.
Users can edit their profile at `profile.php`.

Registration sends an email confirmation link using PHP `mail()`. Configure the
sender in `config.local.php`:

```php
'mail_from' => 'no-reply@your-domain.com',
'app_name' => 'ShopSignal',
```

Users cannot sign in until the `verify-email.php?token=...` link creates and
verifies their account. If delivery fails, users can request a new link at
`resend-verification.php`.

To enable the auth gate, add this to `config.local.php`:

```php
'auth_enabled' => true,
```

For local/testing convenience, the app seeds a temporary verified admin user
when the users table is initialized:

```text
username: admin
password: admin
```

Change or delete this user before using the app publicly.

You can also keep a fallback config admin password:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

```php
'auth_user' => 'admin',
'auth_password_hash' => 'paste-generated-hash-here',
```

When enabled, unauthenticated app requests redirect to `login.php`; API
requests return `401 Authentication required`. Use `logout.php` to end the
session.

## Admin CSV import

The admin importer is available at:

```text
admin.php
```

Download the CSV schemas from the admin page, fill in rows, choose the import
type, and upload the file. Supported files:

- Stores: required `domain`; existing domains are updated and new domains are inserted.
- Technologies: required `domain`, `technology_name`; rows attach to existing stores.
- Products: required `domain`, `name`; rows attach to existing stores.
- Signals: required `domain`, `title`; rows attach to existing stores.

Related imports use the store `domain` to find the matching store ID, so import
stores before technologies, products, or signals.

Each import creates an import batch:

```text
import_batches
import_batch_items
```

The admin page shows recent batches and can roll back a batch. Rollback deletes
rows that were newly created by that batch. Rows that were updated by the batch
are kept because before/after snapshots are not stored yet. Rolling back a store
batch may also remove related rows through normal foreign-key cascade rules.
