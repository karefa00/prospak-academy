<?php
declare(strict_types=1);

use Prospak\Core\Router;
use Prospak\Controllers\AuthController;

/** @var Router $router */
/** @var AuthController $authController */
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/forgot-password', [$authController, 'showForgot']);
$router->post('/forgot-password', [$authController, 'forgot']);
$router->get('/reset-password', [$authController, 'showReset']);
$router->post('/reset-password', [$authController, 'reset']);
$router->get('/change-password', [$authController, 'showChange']);
$router->post('/change-password', [$authController, 'change']);
// No GET/POST /register. Accounts are provisioned by administrators only (Phase 2).
