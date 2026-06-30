<?php
declare(strict_types=1);

// Copy this file to config.local.php and enter the database details provided by
// your web host. config.local.php is protected by .htaccess and ignored by Git.
return [
    'db_dsn' => 'mysql:host=localhost;port=3306;dbname=shopsignal;charset=utf8mb4',
    'db_user' => 'shopsignal',
    'db_password' => 'change-me',
    // Temporarily enable this while testing api/status.php. Disable it after
    // the connection works because database errors can reveal server details.
    'db_debug' => false,
    // Optional: required when running scripts/generate-demo-stores.php through
    // the browser. Use a long random value and remove it after testing.
    'seed_token' => '',
    // Optional: enable simple session login for the app and APIs.
    // Generate the hash with:
    // php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
    'auth_enabled' => false,
    'auth_user' => 'admin',
    'auth_password_hash' => '',
    // Used for registration/profile email verification links.
    'mail_from' => 'no-reply@example.com',
    'mail_from_name' => 'ShopSignal',
    'app_name' => 'ShopSignal',
    // Email delivery.
    // Leave smtp_host empty to use PHP mail() (often lands in spam or fails
    // silently on shared hosting). Set smtp_host to send via SMTP instead —
    // strongly recommended so verification and password-reset emails arrive.
    // For IONOS mailboxes: smtp.ionos.com, port 587 (TLS) or 465 (SSL),
    // username = the full mailbox address, password = that mailbox's password.
    // Make sure mail_from matches an address you are allowed to send from
    // (ideally the smtp_username) and that SPF/DKIM are set for the domain.
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_security' => 'tls', // 'tls' (587), 'ssl' (465), or 'none'
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_timeout' => 20,
    'smtp_allow_self_signed' => false,
    // If an SMTP send fails, also try PHP mail() as a last resort.
    'mail_fallback_to_php' => true,
    // Optional token from Google Search Console's HTML tag verification method.
    'google_site_verification' => '',
    // Google tracking (all optional, left blank = off). Set whichever you use:
    //   GA4 analytics  -> google_analytics_id   = 'G-XXXXXXXXXX'
    //   Tag Manager    -> google_tag_manager_id = 'GTM-XXXXXXX'
    //   Google Ads     -> google_ads_id         = 'AW-XXXXXXXXX'
    // GA4 is the usual choice for site analytics.
    'google_analytics_id' => '',
    'google_tag_manager_id' => '',
    'google_ads_id' => '',
    // Secure one-way sync from the local Shopify spider. Generate a secret with:
    // php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'crawler_ingest_enabled' => false,
    'crawler_ingest_require_https' => true,
    'crawler_ingest_max_batch' => 100,
    'crawler_ingest_max_bytes' => 2097152,
    'crawler_ingest_clock_skew' => 300,
    'crawler_ingest_keys' => [
        'home-scraper' => 'replace-with-a-64-character-random-secret',
    ],
    // Optional Stripe Billing integration. Keep these values server-side only.
    // Create one recurring Stripe Price for the Pro plan and add a webhook for:
    // checkout.session.completed, customer.subscription.created/updated,
    // customer.subscription.deleted, invoice.paid, invoice.payment_failed.
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    'stripe_pro_price_id' => '',
    'stripe_pro_price_label' => '$29 / month',
];
