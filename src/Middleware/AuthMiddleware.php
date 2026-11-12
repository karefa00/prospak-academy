<?php
declare(strict_types=1);
final class AuthMiddleware {
    public static function user(): ?array {
        static $cached = false;
        if ($cached !== false) return $cached;
        $id = positive_id($_SESSION['user_id'] ?? 0);
        if (!$id) return $cached = null;
        $repo = new UserRepository(db());
        $user = $repo->byId($id);
        if (!$user || $user['account_status'] !== 'active' || (int)$user['session_version'] !== (int)($_SESSION['session_version'] ?? 0)) {
            $_SESSION = [];
            return $cached = null;
        }
        return $cached = $user;
    }
    public static function requireLogin(): array {
        $user=self::user();
        if (!$user) redirect('/login');
        $path=(string)(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
        if ((int)$user['must_change_password'] === 1 && !in_array($path,['/change-password','/logout'],true)) redirect('/change-password');
        return $user;
    }
}
