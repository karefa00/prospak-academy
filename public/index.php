<?php
declare(strict_types=1);

use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\Database;
use Prospak\Core\Router;
use Prospak\Core\Session;
use Prospak\Services\AuthService;
use Prospak\Controllers\AuthController;
use Prospak\Controllers\PortalController;
use Prospak\Controllers\PeopleController;
use Prospak\Controllers\AcademicController;
use Prospak\Services\AcademicService;
use Prospak\Services\PeopleService;

require dirname(__DIR__) . '/bootstrap/app.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'");

try {
    Config::appKey();
    Config::appUrl();
    Session::start();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !Csrf::check($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invalid or expired form token. Reload the page and try again.';
        exit;
    }
    $db = new Database();
    $auth = new AuthService($db);
    $authController = new AuthController($auth);
    $portalController = new PortalController($auth);
    $peopleController = new PeopleController($auth, new PeopleService($db));
    $academicController = new AcademicController($auth, new AcademicService($db));
    $router = new Router();
    require dirname(__DIR__) . '/routes/auth.php';
    require dirname(__DIR__) . '/routes/web.php';
    $router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), (string) ($_SERVER['REQUEST_URI'] ?? '/'));
} catch (\Throwable $exception) {
    $status = $exception instanceof \PDOException ? 503 : 500;
    // No exception messages, credentials, request bodies, or reset tokens are written to logs.
    $record = sprintf("%s type=%s code=%s\n", gmdate('c'), get_class($exception), (string) $exception->getCode());
    @file_put_contents(dirname(__DIR__) . '/storage/logs/application.log', $record, FILE_APPEND | LOCK_EX);
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $status === 503 ? 'Service temporarily unavailable.' : 'An unexpected error occurred.';
}
