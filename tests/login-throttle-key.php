<?php
declare(strict_types=1);

// Verifies the login throttle bucket key in src/bootstrap.php.
// Run with: php tests/login-throttle-key.php

require_once dirname(__DIR__) . '/src/bootstrap.php';

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

// Email is normalised (trimmed + lowercased) so case/spacing can't dodge the limit.
$a = shopSignalLoginThrottleKey('User@Example.com ');
$b = shopSignalLoginThrottleKey('user@example.com');
if ($a !== $b) {
    fail('Throttle key is not normalised across case/whitespace: ' . $a . ' vs ' . $b);
}
if ($a !== '198.51.100.7|user@example.com') {
    fail('Unexpected throttle key format: ' . $a);
}

// A different client IP yields a different bucket.
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$c = shopSignalLoginThrottleKey('user@example.com');
if ($c === $a) {
    fail('Throttle key did not change with a different client IP.');
}

// A different email yields a different bucket.
$d = shopSignalLoginThrottleKey('someone-else@example.com');
if ($d === $c) {
    fail('Throttle key did not change with a different email.');
}

// The key is bounded to 190 characters (index-safe) even for long inputs.
$long = shopSignalLoginThrottleKey(str_repeat('x', 500) . '@example.com');
if (mb_strlen($long) > 190) {
    fail('Throttle key exceeded 190 characters: ' . mb_strlen($long));
}

echo "Login throttle key contract passed.\n";
