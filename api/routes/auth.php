<?php
declare(strict_types=1);
$user=AuthMiddleware::requireLogin();
JsonResponse::send(['id'=>(int)$user['id'],'full_name'=>$user['full_name'],'role'=>$user['role_slug']]);
