<?php
declare(strict_types=1);

final class JsonApi
{
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public static function headers(bool $noIndex = false): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        if ($noIndex) {
            header('X-Robots-Tag: noindex, nofollow');
        }
    }

    public static function database(array $config): PDO
    {
        $pdo = Database::connect($config);
        if ($pdo === null) {
            throw new RuntimeException('Database is not configured.');
        }

        return $pdo;
    }

    public static function diagnostic(Throwable $exception): array
    {
        return [
            'type' => get_class($exception),
            'code' => (string) $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    public static function errorPayload(string $message, Throwable $exception, array $config): array
    {
        $payload = ['ok' => false, 'message' => $message];
        if ((bool) ($config['db_debug'] ?? false)) {
            $payload['diagnostic'] = self::diagnostic($exception);
        }

        return $payload;
    }

    public static function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, self::ENCODE_FLAGS);
        exit;
    }

    public static function serverError(string $message, Throwable $exception, array $config): never
    {
        self::respond(self::errorPayload($message, $exception, $config), 500);
    }
}
