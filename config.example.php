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
    'app_name' => 'ShopSignal',
    // Optional token from Google Search Console's HTML tag verification method.
    'google_site_verification' => '',
    // Optional Stripe Billing integration. Keep these values server-side only.
    // Create one recurring Stripe Price for the Pro plan and add a webhook for:
    // checkout.session.completed, customer.subscription.created/updated,
    // customer.subscription.deleted, invoice.paid, invoice.payment_failed.
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    'stripe_pro_price_id' => '',
    'stripe_pro_price_label' => '$29 / month',
];
