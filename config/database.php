<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = env_setting('DB_HOST', '127.0.0.1');
    $port = env_setting('DB_PORT', '3306');
    $name = env_setting('DB_DATABASE', 'prospak_academy');
    $user = env_setting('DB_USERNAME', 'prospak_app');
    $pass = env_setting('DB_PASSWORD', '');
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
