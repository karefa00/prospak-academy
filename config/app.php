<?php
declare(strict_types=1);
/** Load environment from outside web root; never output values. */
function env_setting(string $key, ?string $default = null): ?string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = PROJECT_ROOT . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', trim($line), 2);
                $k = trim($k);
                if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) continue;
                $values[$k] = trim(trim($v), "\"'");
            }
        }
    }
    $value = getenv($key);
    return $value !== false ? (string)$value : ($values[$key] ?? $default);
}
function app_url(string $path = '/'): string {
    return '/' . ltrim($path, '/');
}
