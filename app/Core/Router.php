<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $base = Config::basePath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        if (!isset($this->routes[$method][$path])) {
            $known = array_filter(array_keys($this->routes), fn (string $verb): bool => isset($this->routes[$verb][$path]));
            http_response_code($known ? 405 : 404);
            if ($known) {
                header('Allow: ' . implode(', ', $known));
            }
            header('Content-Type: text/plain; charset=UTF-8');
            echo $known ? 'Method Not Allowed' : 'Not Found';
            return;
        }
        ($this->routes[$method][$path])();
    }
}
