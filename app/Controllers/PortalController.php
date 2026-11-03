<?php
declare(strict_types=1);

namespace Prospak\Controllers;

use Prospak\Core\Guard;
use Prospak\Core\Session;
use Prospak\Core\View;
use Prospak\Services\AuthService;

final class PortalController
{
    public function __construct(private AuthService $auth) {}

    public function dashboard(): void
    {
        $user = Guard::requirePermission($this->auth, 'dashboard.view');
        View::render('portal/home', ['title' => 'Portal access', 'user' => $user, 'flash' => Session::pullFlash()], 'portal');
    }

    public function adminSecurity(): void
    {
        $user = Guard::requirePermission($this->auth, 'admin.access');
        View::render('portal/security', ['title' => 'Administration access check', 'user' => $user], 'portal');
    }
}
