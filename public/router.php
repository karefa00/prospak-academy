<?php
declare(strict_types=1);

// Development only: php -S 127.0.0.1:8000 -t public public/router.php
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$file = __DIR__ . (string) $path;
$real = realpath($file);
$root = realpath(__DIR__);
if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real) && $real !== realpath(__FILE__)) {
    return false;
}
require __DIR__ . '/index.php';
