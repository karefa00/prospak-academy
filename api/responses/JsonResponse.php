<?php
declare(strict_types=1);
final class JsonResponse {
    public static function send(array $data,int $status=200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        exit;
    }
}
