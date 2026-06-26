<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/StoreRepository.php';

/**
 * Shared-hosting friendly configuration.
 *
 * Copy config.example.php to config.local.php on the server. Environment
 * variables still take precedence when they are available.
 *
 * @return array<string, string>
 */
function shopSignalConfig(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $config = [];
    $localConfig = dirname(__DIR__) . '/config.local.php';

    if (is_file($localConfig)) {
        $loaded = require $localConfig;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }

    return $config;
}

function shopSignalStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('shopsignal_session');
        session_start();
    }
}

function shopSignalAuthEnabled(): bool
{
    $config = shopSignalConfig();
    return (bool) ($config['auth_enabled'] ?? false) || trim((string) ($config['auth_password_hash'] ?? '')) !== '';
}

function shopSignalAuthUser(): string
{
    return trim((string) (shopSignalConfig()['auth_user'] ?? 'admin')) ?: 'admin';
}

function shopSignalIsAuthenticated(): bool
{
    if (!shopSignalAuthEnabled()) {
        return true;
    }

    shopSignalStartSession();
    return (bool) ($_SESSION['shopsignal_authenticated'] ?? false);
}

function shopSignalAttemptLogin(string $user, string $password): bool
{
    if (!shopSignalAuthEnabled()) {
        return true;
    }

    $config = shopSignalConfig();
    $expectedUser = shopSignalAuthUser();
    $passwordHash = (string) ($config['auth_password_hash'] ?? '');
    $valid = hash_equals($expectedUser, $user) && $passwordHash !== '' && password_verify($password, $passwordHash);

    if ($valid) {
        shopSignalStartSession();
        session_regenerate_id(true);
        $_SESSION['shopsignal_authenticated'] = true;
        $_SESSION['shopsignal_user'] = $expectedUser;
    }

    return $valid;
}

function shopSignalLogout(): void
{
    shopSignalStartSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function shopSignalRequireAuth(bool $json = false): void
{
    if (shopSignalIsAuthenticated()) {
        return;
    }

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Authentication required.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = $_SERVER['REQUEST_URI'] ?? shopSignalAssetUrl('index.php');
    header('Location: ' . shopSignalAssetUrl('login.php') . '?next=' . rawurlencode($target));
    exit;
}

function shopSignalBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(dirname($scriptName), '/.');

    if (str_ends_with($basePath, '/api')) {
        $basePath = substr($basePath, 0, -4);
    }

    return $basePath === '' ? '/' : $basePath . '/';
}

function shopSignalAssetUrl(string $path): string
{
    return shopSignalBasePath() . ltrim($path, '/');
}

function shopSignalVersionedAssetUrl(string $path): string
{
    $url = shopSignalAssetUrl($path);
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');

    if (is_file($file)) {
        return $url . '?v=' . filemtime($file);
    }

    return $url;
}

/**
 * Loads the dashboard payload from the configured database.
 *
 * If no database is configured yet, the frontend's built-in sample records
 * remain available. This keeps local setup frictionless while making the page
 * database-ready.
 *
 * @return array{
 *   stores: array<int, array<string, mixed>>,
 *   profiles: array<string, array<string, mixed>>,
 *   stats: array<string, int|string>,
 *   source: string
 * }
 */
function loadShopSignalData(): array
{
    $fallback = [
        'stores' => [],
        'profiles' => [],
        'stats' => [
            'matching_stores' => 24891,
            'new_this_week' => 1284,
            'median_revenue' => '$142k',
            'high_growth_stores' => 3619,
            'updated_stores' => 48291,
        ],
        'source' => 'sample',
    ];

    try {
        $pdo = Database::connect(shopSignalConfig());
        if ($pdo === null) {
            return $fallback;
        }

        $repository = new StoreRepository($pdo);
        $stores = $repository->findStores(limit: 20);

        if ($stores === []) {
            return $fallback;
        }

        return [
            'stores' => $stores,
            'profiles' => $repository->findProfilesForStores($stores),
            'stats' => $repository->getDashboardStats(),
            'source' => 'database',
        ];
    } catch (Throwable $exception) {
        error_log('ShopSignal database fallback: ' . $exception->getMessage());
        return $fallback;
    }
}
