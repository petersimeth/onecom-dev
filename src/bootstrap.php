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
    shopSignalStartSession();
    return trim((string) ($_SESSION['shopsignal_user_name'] ?? $_SESSION['shopsignal_user'] ?? shopSignalConfig()['auth_user'] ?? 'admin')) ?: 'admin';
}

function shopSignalCurrentUser(): array
{
    shopSignalStartSession();
    shopSignalSyncSessionUserFromDatabase();
    return [
        'id' => (int) ($_SESSION['shopsignal_user_id'] ?? 0),
        'name' => shopSignalAuthUser(),
        'email' => (string) ($_SESSION['shopsignal_user_email'] ?? ''),
        'role' => (string) ($_SESSION['shopsignal_user_role'] ?? 'guest'),
        'plan' => (string) ($_SESSION['shopsignal_user_plan'] ?? 'guest'),
    ];
}

function shopSignalSyncSessionUserFromDatabase(): void
{
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;

    if (!($_SESSION['shopsignal_authenticated'] ?? false) || (int) ($_SESSION['shopsignal_user_id'] ?? 0) <= 0) {
        return;
    }

    $pdo = Database::connect(shopSignalConfig());
    if ($pdo === null) {
        return;
    }

    try {
        shopSignalEnsureUserSchema($pdo);
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['shopsignal_user_id']]);
        $user = $statement->fetch();
        if (!is_array($user) || $user['status'] !== 'active') {
            $_SESSION = [];
            return;
        }
        $_SESSION['shopsignal_user'] = (string) ($user['email'] ?? $user['name'] ?? 'admin');
        $_SESSION['shopsignal_user_name'] = (string) ($user['name'] ?? $user['email'] ?? 'Admin');
        $_SESSION['shopsignal_user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['shopsignal_user_role'] = (string) ($user['role'] ?? 'user');
        $_SESSION['shopsignal_user_plan'] = (string) ($user['plan'] ?? 'free');
    } catch (Throwable) {
        // Keep the session usable if a shared-hosting database hiccup occurs.
    }
}

function shopSignalHasActiveSession(): bool
{
    shopSignalStartSession();
    return (bool) ($_SESSION['shopsignal_authenticated'] ?? false);
}

function shopSignalIsAdmin(): bool
{
    return shopSignalCurrentUser()['role'] === 'admin';
}

function shopSignalCurrentPlan(): string
{
    $user = shopSignalCurrentUser();
    if ($user['role'] === 'admin') {
        return 'admin';
    }
    if (in_array($user['plan'], ['free', 'pro', 'enterprise'], true)) {
        return $user['plan'];
    }
    return shopSignalHasActiveSession() ? 'free' : 'guest';
}

function shopSignalHasProAccess(): bool
{
    return in_array(shopSignalCurrentPlan(), ['pro', 'enterprise', 'admin'], true);
}

function shopSignalHasFreeAccess(): bool
{
    return shopSignalHasActiveSession() && in_array(shopSignalCurrentPlan(), ['free', 'pro', 'enterprise', 'admin'], true);
}

function shopSignalRequirePro(bool $json = false): void
{
    shopSignalRequireAuth($json);
    if (shopSignalHasProAccess()) {
        return;
    }

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Upgrade required.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(403);
    echo 'Upgrade required.';
    exit;
}

function shopSignalRequireAdmin(): void
{
    shopSignalRequireAuth();
    if (shopSignalIsAdmin()) {
        return;
    }

    http_response_code(403);
    echo 'Admin access required.';
    exit;
}

function shopSignalEnsureUserSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM(\'user\', \'admin\') DEFAULT \'user\',
            plan VARCHAR(40) DEFAULT \'free\',
            status ENUM(\'active\', \'disabled\') DEFAULT \'active\',
            email_verified_at DATETIME NULL,
            verification_token_hash VARCHAR(255) NULL,
            verification_sent_at DATETIME NULL,
            last_login_at DATETIME NULL,
            stripe_customer_id VARCHAR(255) NULL,
            stripe_subscription_id VARCHAR(255) NULL,
            subscription_status VARCHAR(50) NULL,
            subscription_current_period_end DATETIME NULL,
            subscription_cancel_at_period_end TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_role (role),
            INDEX idx_user_status (status),
            INDEX idx_user_stripe_customer (stripe_customer_id),
            INDEX idx_user_stripe_subscription (stripe_subscription_id)
        )
    ');

    foreach ([
        'email_verified_at DATETIME NULL',
        'verification_token_hash VARCHAR(255) NULL',
        'verification_sent_at DATETIME NULL',
        'plan VARCHAR(40) DEFAULT \'free\'',
        'stripe_customer_id VARCHAR(255) NULL',
        'stripe_subscription_id VARCHAR(255) NULL',
        'subscription_status VARCHAR(50) NULL',
        'subscription_current_period_end DATETIME NULL',
        'subscription_cancel_at_period_end TINYINT(1) DEFAULT 0',
    ] as $columnDefinition) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $columnDefinition);
        } catch (Throwable) {
            // Column already exists on upgraded installs.
        }
    }

    $pdo->exec('
        UPDATE users
        SET email_verified_at = COALESCE(email_verified_at, created_at)
        WHERE email_verified_at IS NULL
          AND verification_token_hash IS NULL
    ');

    $lookup = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $lookup->execute(['email' => 'admin']);
    if (!$lookup->fetchColumn()) {
        $insert = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role, plan, status, email_verified_at)
            VALUES (\'admin\', \'admin\', :password_hash, \'admin\', \'free\', \'active\', NOW())
        ');
        $insert->execute(['password_hash' => password_hash('admin', PASSWORD_DEFAULT)]);
    }
}

function shopSignalEnsureStripeSchema(PDO $pdo): void
{
    shopSignalEnsureUserSchema($pdo);
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS stripe_webhook_events (
            id VARCHAR(255) PRIMARY KEY,
            event_type VARCHAR(120) NOT NULL,
            processed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_stripe_event_processed (processed_at),
            INDEX idx_stripe_event_type (event_type)
        )
    ');
}

function shopSignalStripeCheckoutEnabled(): bool
{
    $config = shopSignalConfig();
    return trim((string) ($config['stripe_secret_key'] ?? '')) !== ''
        && trim((string) ($config['stripe_pro_price_id'] ?? '')) !== '';
}

function shopSignalStripeWebhookEnabled(): bool
{
    return trim((string) (shopSignalConfig()['stripe_webhook_secret'] ?? '')) !== '';
}

function shopSignalStripeApiRequest(string $path, array $parameters): array
{
    $secretKey = trim((string) (shopSignalConfig()['stripe_secret_key'] ?? ''));
    if ($secretKey === '') {
        throw new RuntimeException('Stripe is not configured yet.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for Stripe.');
    }

    $handle = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));
    if ($handle === false) {
        throw new RuntimeException('Unable to initialize the Stripe request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if (!is_string($response)) {
        throw new RuntimeException('Stripe could not be reached' . ($curlError !== '' ? ': ' . $curlError : '.'));
    }
    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }
    if ($status < 200 || $status >= 300) {
        $stripeMessage = (string) ($payload['error']['message'] ?? 'Stripe rejected the request.');
        throw new RuntimeException($stripeMessage);
    }

    return $payload;
}

function shopSignalCreateStripeCheckout(array $user): string
{
    $config = shopSignalConfig();
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0 || !shopSignalStripeCheckoutEnabled()) {
        throw new RuntimeException('Stripe Checkout is not available yet.');
    }

    $parameters = [
        'mode' => 'subscription',
        'success_url' => shopSignalAbsoluteUrl('profile.php?checkout=success&session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => shopSignalAbsoluteUrl('pricing.php?checkout=cancelled'),
        'client_reference_id' => (string) $userId,
        'line_items' => [[
            'price' => trim((string) $config['stripe_pro_price_id']),
            'quantity' => 1,
        ]],
        'metadata' => ['user_id' => (string) $userId],
        'subscription_data' => ['metadata' => ['user_id' => (string) $userId]],
        'allow_promotion_codes' => 'true',
    ];

    $customerId = trim((string) ($user['stripe_customer_id'] ?? ''));
    if ($customerId !== '') {
        $parameters['customer'] = $customerId;
    } else {
        $parameters['customer_email'] = (string) ($user['email'] ?? '');
    }

    $session = shopSignalStripeApiRequest('checkout/sessions', $parameters);
    $url = trim((string) ($session['url'] ?? ''));
    if ($url === '' || !str_starts_with($url, 'https://')) {
        throw new RuntimeException('Stripe did not return a Checkout URL.');
    }
    return $url;
}

function shopSignalCreateStripePortal(array $user): string
{
    $customerId = trim((string) ($user['stripe_customer_id'] ?? ''));
    if ($customerId === '') {
        throw new RuntimeException('No Stripe billing account is connected to this user.');
    }

    $session = shopSignalStripeApiRequest('billing_portal/sessions', [
        'customer' => $customerId,
        'return_url' => shopSignalAbsoluteUrl('profile.php'),
    ]);
    $url = trim((string) ($session['url'] ?? ''));
    if ($url === '' || !str_starts_with($url, 'https://')) {
        throw new RuntimeException('Stripe did not return a billing portal URL.');
    }
    return $url;
}

function shopSignalVerifyStripeSignature(string $payload, string $signatureHeader, int $tolerance = 300): bool
{
    $secret = trim((string) (shopSignalConfig()['stripe_webhook_secret'] ?? ''));
    if ($secret === '' || $payload === '' || $signatureHeader === '') {
        return false;
    }

    $timestamp = 0;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't') {
            $timestamp = (int) $value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }
    if ($timestamp <= 0 || abs(time() - $timestamp) > $tolerance || $signatures === []) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

function shopSignalStripeUserId(PDO $pdo, array $object): int
{
    $userId = (int) ($object['metadata']['user_id'] ?? $object['client_reference_id'] ?? 0);
    if ($userId > 0) {
        return $userId;
    }

    $subscriptionId = trim((string) ($object['subscription'] ?? $object['id'] ?? ''));
    $customerId = trim((string) ($object['customer'] ?? ''));
    if ($subscriptionId !== '') {
        $statement = $pdo->prepare('SELECT id FROM users WHERE stripe_subscription_id = :subscription_id LIMIT 1');
        $statement->execute(['subscription_id' => $subscriptionId]);
        $userId = (int) ($statement->fetchColumn() ?: 0);
        if ($userId > 0) {
            return $userId;
        }
    }
    if ($customerId === '') {
        return 0;
    }
    $statement = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = :customer_id LIMIT 1');
    $statement->execute(['customer_id' => $customerId]);
    return (int) ($statement->fetchColumn() ?: 0);
}

function shopSignalApplyStripeSubscription(PDO $pdo, int $userId, array $subscription): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Stripe event could not be matched to a ShopSignal user.');
    }

    $status = trim((string) ($subscription['status'] ?? 'active')) ?: 'active';
    $proStatuses = ['active', 'trialing', 'past_due'];
    $periodEnd = (int) ($subscription['current_period_end'] ?? 0);
    $statement = $pdo->prepare('
        UPDATE users
        SET plan = :plan,
            stripe_customer_id = COALESCE(NULLIF(:customer_id, \'\'), stripe_customer_id),
            stripe_subscription_id = COALESCE(NULLIF(:subscription_id, \'\'), stripe_subscription_id),
            subscription_status = :subscription_status,
            subscription_current_period_end = :period_end,
            subscription_cancel_at_period_end = :cancel_at_period_end
        WHERE id = :id
    ');
    $statement->execute([
        'plan' => in_array($status, $proStatuses, true) ? 'pro' : 'free',
        'customer_id' => (string) ($subscription['customer'] ?? ''),
        'subscription_id' => (string) ($subscription['id'] ?? $subscription['subscription'] ?? ''),
        'subscription_status' => $status,
        'period_end' => $periodEnd > 0 ? date('Y-m-d H:i:s', $periodEnd) : null,
        'cancel_at_period_end' => !empty($subscription['cancel_at_period_end']) ? 1 : 0,
        'id' => $userId,
    ]);
}

function shopSignalEnsurePendingRegistrationSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pending_registrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            verification_token_hash VARCHAR(255) NOT NULL,
            verification_sent_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pending_registration_token (verification_token_hash),
            INDEX idx_pending_registration_email (email)
        )
    ');
}

function shopSignalEnsureProRequestSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pro_access_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            status ENUM(\'pending\', \'approved\', \'rejected\') DEFAULT \'pending\',
            message TEXT NULL,
            decided_by BIGINT UNSIGNED NULL,
            decided_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pro_request_status (status),
            INDEX idx_pro_request_created (created_at),
            CONSTRAINT fk_pro_request_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ');
}

function shopSignalCreateProRequest(PDO $pdo, int $userId, string $message = ''): void
{
    shopSignalEnsureProRequestSchema($pdo);
    $pdo->prepare('DELETE FROM pro_access_requests WHERE user_id = :user_id AND status = \'pending\'')
        ->execute(['user_id' => $userId]);
    $statement = $pdo->prepare('
        INSERT INTO pro_access_requests (user_id, status, message)
        VALUES (:user_id, \'pending\', :message)
    ');
    $statement->execute([
        'user_id' => $userId,
        'message' => mb_substr(trim($message), 0, 2000),
    ]);
}

function shopSignalCurrentProRequest(PDO $pdo, int $userId): ?array
{
    shopSignalEnsureProRequestSchema($pdo);
    $statement = $pdo->prepare('
        SELECT
            id,
            status,
            message,
            DATE_FORMAT(created_at, \'%b %e, %Y %H:%i\') AS created_label,
            DATE_FORMAT(decided_at, \'%b %e, %Y %H:%i\') AS decided_label
        FROM pro_access_requests
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $statement->execute(['user_id' => $userId]);
    $request = $statement->fetch();
    return is_array($request) ? $request : null;
}

function shopSignalPendingProRequests(PDO $pdo, int $limit = 50): array
{
    shopSignalEnsureProRequestSchema($pdo);
    $statement = $pdo->prepare('
        SELECT
            r.id,
            r.user_id,
            r.status,
            r.message,
            u.name,
            u.email,
            u.plan,
            DATE_FORMAT(r.created_at, \'%b %e, %Y %H:%i\') AS created_label
        FROM pro_access_requests r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.status = \'pending\'
        ORDER BY r.id ASC
        LIMIT :limit
    ');
    $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function shopSignalDecideProRequest(PDO $pdo, int $requestId, string $decision, int $adminUserId): void
{
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Invalid Pro request decision.');
    }

    shopSignalEnsureProRequestSchema($pdo);
    $statement = $pdo->prepare('SELECT * FROM pro_access_requests WHERE id = :id AND status = \'pending\' LIMIT 1');
    $statement->execute(['id' => $requestId]);
    $request = $statement->fetch();
    if (!is_array($request)) {
        throw new RuntimeException('Pro request not found or already handled.');
    }

    $pdo->beginTransaction();
    try {
        if ($decision === 'approved') {
            $pdo->prepare('UPDATE users SET plan = \'pro\' WHERE id = :id')->execute(['id' => (int) $request['user_id']]);
        }

        $pdo->prepare('
            UPDATE pro_access_requests
            SET status = :status,
                decided_by = :decided_by,
                decided_at = NOW()
            WHERE id = :id
        ')->execute([
            'status' => $decision,
            'decided_by' => $adminUserId > 0 ? $adminUserId : null,
            'id' => $requestId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function shopSignalUserCount(PDO $pdo): int
{
    shopSignalEnsureUserSchema($pdo);
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function shopSignalFindUserByEmail(PDO $pdo, string $email): ?array
{
    shopSignalEnsureUserSchema($pdo);
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => mb_strtolower(trim($email))]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function shopSignalSetAuthenticatedUser(array $user): void
{
    shopSignalStartSession();
    session_regenerate_id(true);
    $_SESSION['shopsignal_authenticated'] = true;
    $_SESSION['shopsignal_user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['shopsignal_user'] = (string) ($user['email'] ?? $user['name'] ?? 'admin');
    $_SESSION['shopsignal_user_name'] = (string) ($user['name'] ?? $user['email'] ?? 'Admin');
    $_SESSION['shopsignal_user_email'] = (string) ($user['email'] ?? '');
    $_SESSION['shopsignal_user_role'] = (string) ($user['role'] ?? 'user');
    $_SESSION['shopsignal_user_plan'] = (string) ($user['plan'] ?? 'free');
}

function shopSignalAbsoluteUrl(string $path): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($isHttps ? 'https://' : 'http://') . $host . shopSignalAssetUrl($path);
}

function shopSignalCreateVerificationToken(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare('
        UPDATE users
        SET verification_token_hash = :hash,
            verification_sent_at = NOW(),
            email_verified_at = NULL
        WHERE id = :id
    ');
    $statement->execute([
        'hash' => hash('sha256', $token),
        'id' => $userId,
    ]);

    return $token;
}

function shopSignalCreatePendingRegistration(PDO $pdo, string $name, string $email, string $passwordHash): array
{
    shopSignalEnsurePendingRegistrationSchema($pdo);
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare('
        INSERT INTO pending_registrations (name, email, password_hash, verification_token_hash, verification_sent_at)
        VALUES (:name, :email, :password_hash, :token_hash, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            verification_token_hash = VALUES(verification_token_hash),
            verification_sent_at = NOW(),
            updated_at = CURRENT_TIMESTAMP
    ');
    $statement->execute([
        'name' => mb_substr($name, 0, 160),
        'email' => $email,
        'password_hash' => $passwordHash,
        'token_hash' => hash('sha256', $token),
    ]);

    return ['token' => $token, 'name' => mb_substr($name, 0, 160), 'email' => $email];
}

function shopSignalSendVerificationEmail(array $user, string $token): bool
{
    $config = shopSignalConfig();
    $from = trim((string) ($config['mail_from'] ?? 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $appName = trim((string) ($config['app_name'] ?? 'ShopSignal'));
    $link = shopSignalAbsoluteUrl('verify-email.php?token=' . rawurlencode($token));
    $subject = 'Confirm your ' . $appName . ' email';
    $message = "Hi " . (string) ($user['name'] ?? 'there') . ",\n\n"
        . "Please confirm your email address by opening this link:\n\n"
        . $link . "\n\n"
        . "If you did not create this account, you can ignore this email.";
    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail((string) $user['email'], $subject, $message, implode("\r\n", $headers));
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
    $config = shopSignalConfig();
    $pdo = Database::connect($config);
    if ($pdo !== null) {
        $dbUser = shopSignalFindUserByEmail($pdo, $user);
        if ($dbUser && $dbUser['status'] === 'active' && password_verify($password, (string) $dbUser['password_hash'])) {
            if ($dbUser['email_verified_at'] === null) {
                return false;
            }
            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int) $dbUser['id']]);
            shopSignalSetAuthenticatedUser($dbUser);
            return true;
        }
    }

    $expectedUser = trim((string) ($config['auth_user'] ?? 'admin')) ?: 'admin';
    $passwordHash = (string) ($config['auth_password_hash'] ?? '');
    $valid = hash_equals($expectedUser, $user) && $passwordHash !== '' && password_verify($password, $passwordHash);

    if ($valid) {
        shopSignalSetAuthenticatedUser([
            'id' => 0,
            'name' => $expectedUser,
            'email' => '',
            'role' => 'admin',
        ]);
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

function shopSignalPreviewStores(array $stores): array
{
    return array_map(
        static function (array $store): array {
            $store['revenue'] = 0;
            $store['revenueLabel'] = 'Join to view';
            $store['traffic'] = 0;
            $store['trafficLabel'] = 'Hidden';
            $store['growth'] = '—';
            $store['signal'] = 'Locked';
            $store['stack'] = [];
            $store['products'] = 'Hidden';
            $store['previewLocked'] = true;
            return $store;
        },
        $stores
    );
}

function shopSignalFreeStores(array $stores): array
{
    return array_map(
        static function (array $store): array {
            $revenue = (float) ($store['revenue'] ?? 0);
            $traffic = (int) ($store['traffic'] ?? 0);
            $store['revenueLabel'] = $revenue >= 1_000_000 ? '$1M+' : ($revenue >= 250_000 ? '$250K–$1M' : ($revenue >= 50_000 ? '$50K–$250K' : 'Under $50K'));
            $store['trafficLabel'] = $traffic >= 1_000_000 ? '1M+' : ($traffic >= 250_000 ? '250K–1M' : ($traffic >= 50_000 ? '50K–250K' : 'Under 50K'));
            $store['growth'] = 'Upgrade';
            $store['signal'] = 'Limited';
            $store['stack'] = array_slice((array) ($store['stack'] ?? []), 0, 1);
            $store['products'] = 'Upgrade';
            $store['freeLimited'] = true;
            return $store;
        },
        $stores
    );
}

function shopSignalPreviewData(array $data): array
{
    $data['stores'] = shopSignalPreviewStores($data['stores'] ?? []);
    $data['profiles'] = [];
    $matchingStores = (int) ($data['stats']['matching_stores'] ?? count($data['stores']));
    $data['stats'] = [
        'matching_stores' => $matchingStores,
        'new_this_week' => 0,
        'median_revenue' => 'Hidden',
        'high_growth_stores' => 0,
        'updated_stores' => 0,
    ];
    return $data;
}

function shopSignalFreeData(array $data): array
{
    $data['stores'] = shopSignalFreeStores($data['stores'] ?? []);
    $data['profiles'] = [];
    $matchingStores = (int) ($data['stats']['matching_stores'] ?? count($data['stores']));
    $data['stats'] = [
        'matching_stores' => $matchingStores,
        'new_this_week' => 0,
        'median_revenue' => 'Upgrade',
        'high_growth_stores' => 0,
        'updated_stores' => 0,
    ];
    return $data;
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
