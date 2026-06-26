<?php
declare(strict_types=1);

final class Database
{
    /**
     * @param array<string, string> $config
     */
    public static function connect(array $config = []): ?PDO
    {
        $dsn = getenv('DB_DSN') ?: ($config['db_dsn'] ?? '');

        if ($dsn === '') {
            return null;
        }

        return new PDO(
            $dsn,
            getenv('DB_USER') ?: ($config['db_user'] ?? null),
            getenv('DB_PASSWORD') ?: ($config['db_password'] ?? null),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
