<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Validation
{
    public static function email(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    public static function password(mixed $value): bool
    {
        // Bcrypt ignores bytes beyond 72. Reject rather than silently truncate.
        return is_string($value) && strlen($value) >= 12 && strlen($value) <= 72 && !str_contains($value, "\0");
    }

    public static function name(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' && strlen($value) <= 150 && !preg_match('/[\x00-\x1f\x7f]/', $value) ? $value : null;
    }
}
