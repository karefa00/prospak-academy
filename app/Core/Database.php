<?php
declare(strict_types=1);

namespace Prospak\Core;

use PDO;

final class Database
{
    private ?PDO $connection = null;

    public function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE', 'prospak_academy');
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || !preg_match('/^[a-zA-Z0-9_]+$/D', $database)) {
            throw new \RuntimeException('Invalid database connection configuration.');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $this->connection = $pdo;
    }
}
