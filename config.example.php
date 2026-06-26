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
];
