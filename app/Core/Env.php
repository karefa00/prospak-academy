<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Env
{
    /** Local .env values never override values set by the deployment environment. */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/D', $line, $matches)) {
                throw new \RuntimeException('Invalid .env configuration line.');
            }
            $name = $matches[1];
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                if (substr($value, -1) !== $value[0]) {
                    throw new \RuntimeException('Invalid .env quote.');
                }
                $value = substr($value, 1, -1);
            }
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}
