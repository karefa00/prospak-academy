<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (Config::isProduction() && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
            throw new \RuntimeException('HTTPS is required in production.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('PROSPAKSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Config::basePath() ?: '/',
            'secure' => Config::isProduction() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $minutes = max(1, min(240, (int) Config::get('SESSION_IDLE_MINUTES', '30')));
        $now = time();
        if (isset($_SESSION['_last_activity']) && $now - (int) $_SESSION['_last_activity'] > $minutes * 60) {
            self::destroy();
            self::start();
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
