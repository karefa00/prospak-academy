<?php
$u=AuthMiddleware::user();$role=(string)($u['role_slug']??'');$current=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$items=[
  ['/dashboard','▦','Dashboard',['administrator','academic_officer','lecturer','student']],
  ['/users','♙','User accounts',['administrator']],
  ['/students','♧','Students',['administrator','academic_officer']],
  ['/courses','▤','Course catalogue',['administrator','academic_officer','lecturer','student']],
  ['/courses/assignments','▥','Lecturer assignments',['administrator','academic_officer','lecturer']],
  ['/enrollments','⊞','Enrolments',['administrator','academic_officer','lecturer','student']],
  ['/grades','◫','Score & grade management',['administrator','academic_officer','lecturer']],
  ['/results/publish','✓','Publish Results',['administrator']],
  ['/results','✦','My published grades',['student']],
  ['/grades/setup','⚙','Grade configuration',['administrator']],
  ['/settings/academics','▧','Academic settings',['administrator']],
  ['/profile','◉','My profile',['administrator','academic_officer','lecturer','student']],
  ['/change-password','⚿','Change password',['administrator','academic_officer','lecturer','student']],
];
?>
<aside class="portal-sidebar" id="portalSidebar" aria-label="Main navigation">
<a href="/dashboard" class="brand-lockup"><span class="brand-mark">PA</span><span><strong>Prospak Academy</strong><small>Academic Management</small></span></a>
<div class="sidebar-navigation" tabindex="0" aria-label="Scrollable menu"><div class="sidebar-caption">MAIN MENU</div>
<nav class="nav flex-column" aria-label="Sidebar links">
<?php foreach($items as [$path,$symbol,$label,$roles]):if(!in_array($role,$roles,true))continue; ?>
<a class="sidebar-link <?= $current===$path?'active':'' ?>" href="<?= e($path) ?>"><span class="sidebar-symbol" aria-hidden="true"><?= e($symbol) ?></span><?= e($label) ?></a>
<?php endforeach; ?></nav></div>
<form class="sidebar-logout" method="post" action="/logout"><?= csrf_field() ?><button class="btn btn-outline-light w-100" type="submit">Sign out</button></form>
</aside>
