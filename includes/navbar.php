<?php $navUser=AuthMiddleware::user(); ?>
<header class="portal-navbar"><button class="icon-button menu-toggle" aria-label="Toggle navigation" type="button" data-toggle-sidebar>☰</button>
<div class="navbar-heading"><strong><?= e($title??'Dashboard') ?></strong><small>Secure academic workspace</small></div>
<div class="navbar-user"><span class="avatar-pill"><?= e(strtoupper(substr((string)($navUser['full_name']??'P'),0,1))) ?></span><span class="navbar-user-name"><strong><?= e($navUser['full_name']??'') ?></strong><small><?= e(ucwords(str_replace('_',' ',(string)($navUser['role_slug']??'')))) ?></small></span></div>
</header>
