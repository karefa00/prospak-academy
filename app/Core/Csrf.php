<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(mixed $provided): bool
    {
        return is_string($provided) && hash_equals(self::token(), $provided);
    }
}
