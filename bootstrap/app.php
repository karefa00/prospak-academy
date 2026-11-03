<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Prospak\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

\Prospak\Core\Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('UTC');
