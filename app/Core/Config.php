<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Config
{
    public static function get(string $name, string $default = ''): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }

    public static function appKey(): string
    {
        $key = self::get('APP_KEY');
        if (!preg_match('/^[a-f0-9]{64}$/iD', $key)) {
            throw new \RuntimeException('APP_KEY must be a unique 64-character hex value.');
        }
        return hex2bin($key);
    }

    public static function appUrl(): string
    {
        $url = rtrim(self::get('APP_URL'), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \RuntimeException('APP_URL must be a valid HTTP(S) origin.');
        }
        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new \RuntimeException('APP_URL must not have credentials, a query or fragment.');
        }
        return $url;
    }

    public static function basePath(): string
    {
        return rtrim((string) (parse_url(self::appUrl(), PHP_URL_PATH) ?: ''), '/');
    }

    public static function url(string $path): string
    {
        return self::basePath() . '/' . ltrim($path, '/');
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'local') === 'production';
    }
}
