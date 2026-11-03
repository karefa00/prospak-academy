<?php
declare(strict_types=1);

namespace Prospak\Core;

final class View
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function render(string $template, array $data = [], string $layout = 'auth'): void
    {
        if (!preg_match('/^[a-z0-9_\/-]+$/D', $template) || str_contains($template, '..') || !in_array($layout, ['auth', 'portal'], true)) {
            throw new \InvalidArgumentException('Invalid template.');
        }
        $viewPath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('Template not found.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $viewPath;
            $content = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
        require dirname(__DIR__, 2) . '/templates/layouts/' . $layout . '.php';
    }
}
