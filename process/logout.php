<?php
declare(strict_types=1);
AuthMiddleware::requireLogin();
(new AuthController())->signOut();
