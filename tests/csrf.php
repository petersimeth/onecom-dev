<?php
declare(strict_types=1);

// Verifies the CSRF token helpers in src/bootstrap.php.
// Run with: php tests/csrf.php
//
// bootstrap.php's remember-me resume is a no-op under CLI, so including it here
// has no session/database side effects.

require_once dirname(__DIR__) . '/src/bootstrap.php';

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$token = shopSignalCsrfToken();

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    fail('CSRF token is not 64 hex characters: ' . $token);
}

// The token is stable for the life of the session.
if (shopSignalCsrfToken() !== $token) {
    fail('CSRF token changed within the same session.');
}

// The correct token validates.
if (!shopSignalCsrfValid($token)) {
    fail('A valid CSRF token was rejected.');
}

// Wrong, empty and null tokens are rejected.
if (shopSignalCsrfValid('not-the-token')) {
    fail('An incorrect CSRF token was accepted.');
}
if (shopSignalCsrfValid('')) {
    fail('An empty CSRF token was accepted.');
}
if (shopSignalCsrfValid(null)) {
    fail('A null CSRF token was accepted.');
}

// A token of the right shape but wrong value must still fail (constant-time compare).
if (shopSignalCsrfValid(str_repeat('a', 64))) {
    fail('A same-length but incorrect token was accepted.');
}

echo "CSRF contract passed.\n";
