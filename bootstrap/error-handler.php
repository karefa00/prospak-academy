<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', env_setting('APP_ENV', 'production') === 'local' ? '0' : '0');
set_exception_handler(static function (Throwable $e): void {
    $type = $e instanceof PDOException ? 'db' : 'runtime';
    $line = gmdate('c') . ' type=' . $type . ' class=' . get_class($e) . ' code=' . (string)$e->getCode() . PHP_EOL;
    @file_put_contents(PROJECT_ROOT . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    if (!headers_sent()) { http_response_code($type === 'db' ? 503 : 500); header('Content-Type: text/html; charset=UTF-8'); }
    $message = $type === 'db' ? 'Database temporarily unavailable.' : 'Unexpected application error.';
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Service error</title></head><body><h1>' . $message . '</h1></body></html>';
});
