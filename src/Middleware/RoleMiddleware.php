<?php
declare(strict_types=1);
final class RoleMiddleware {
    public static function requireRole(string ...$roles): array {
        $user=AuthMiddleware::requireLogin();
        if (!in_array((string)$user['role_slug'],$roles,true)) {
            http_response_code(403);
            View::render('errors/forbidden',['title'=>'Access denied'],'main');
            exit;
        }
        return $user;
    }
}
