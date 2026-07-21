<?php
declare(strict_types=1);

namespace FFTicket;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = env_value('DB_HOST', 'localhost');
        $port = env_value('DB_PORT', '3306');
        $name = env_value('DB_NAME', 'ffticket');
        $charset = env_value('DB_CHARSET', 'utf8mb4');
        $user = env_value('DB_USER');
        $pass = env_value('DB_PASS');

        if ($user === null || $pass === null) {
            throw new RuntimeException('Database credentials are not configured.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database.', 0, $exception);
        }

        return self::$connection;
    }
}

