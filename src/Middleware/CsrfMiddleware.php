<?php
declare(strict_types=1);
final class CsrfMiddleware {
    public static function verify(): void {
        $token=$_POST['_csrf'] ?? null;
        if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=UTF-8');
            exit('Invalid or expired CSRF token. Refresh the page.');
        }
    }
}
