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

/** Phase 2: administrator-only account management and read-only self-profile. */
$router->get('/admin/users', [$peopleController, 'index']);
$router->get('/admin/students', [$peopleController, 'students']);
$router->get('/admin/lecturers', [$peopleController, 'lecturers']);
$router->get('/admin/users/new', [$peopleController, 'new']);
$router->post('/admin/users/new', [$peopleController, 'create']);
$router->get('/admin/users/view', [$peopleController, 'show']);
$router->get('/admin/users/edit', [$peopleController, 'edit']);
$router->post('/admin/users/edit', [$peopleController, 'update']);
$router->post('/admin/users/status', [$peopleController, 'status']);
$router->post('/admin/users/temporary-password', [$peopleController, 'temporaryPassword']);
$router->post('/admin/users/photo', [$peopleController, 'uploadPhoto']);
$router->get('/profile', [$peopleController, 'profile']);
$router->get('/student-photo', [$peopleController, 'photo']);

/** Phase 3: academic organisation; admin edits, academic officer read-only. */
foreach (\Prospak\Core\AcademicValidation::ENTITIES as $entity) {
    $router->get('/academics/'.$entity, static fn () => $academicController->index($entity));
    $router->get('/academics/'.$entity.'/form', static fn () => $academicController->form($entity));
    $router->post('/academics/'.$entity.'/save', static fn () => $academicController->save($entity));
}
$router->get('/academics/students', [$academicController, 'students']);
$router->get('/academics/placement', [$academicController, 'placement']);
$router->post('/academics/placement', [$academicController, 'savePlacement']);
