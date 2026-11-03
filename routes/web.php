<?php
declare(strict_types=1);

use Prospak\Core\Router;
use Prospak\Core\Config;
use Prospak\Controllers\PortalController;

/** @var Router $router */
/** @var PortalController $portalController */
$router->get('/', static function (): void {
    header('Location: ' . Config::url('/login'), true, 302);
    exit;
});
$router->get('/dashboard', [$portalController, 'dashboard']);
$router->get('/admin/security-check', [$portalController, 'adminSecurity']);
