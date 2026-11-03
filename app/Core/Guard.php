<?php
declare(strict_types=1);

namespace Prospak\Core;

use Prospak\Services\AuthService;

final class Guard
{
    public static function requirePermission(AuthService $auth, string $permission): array
    {
        $user = $auth->user();
        if ($user === null) {
            header('Location: ' . Config::url('/login'), true, 303);
            exit;
        }
        if (!$auth->can($permission)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Forbidden';
            exit;
        }
        return $user;
    }
}
