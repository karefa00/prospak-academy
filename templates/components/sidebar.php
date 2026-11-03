<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<aside class="prospak-sidebar p-4">
 <a class="navbar-brand d-flex align-items-center mb-4" href="<?= View::escape(Config::url('/dashboard')) ?>">
  <span class="bg-primary text-white p-2 rounded me-2">PA</span><span class="prospak-brand fs-5">Prospak Academy</span>
 </a>
 <p class="small text-uppercase text-muted fw-bold">Account</p>
 <nav aria-label="Account navigation" class="nav flex-column gap-2">
  <a class="nav-link <?= $currentPath === Config::url('/dashboard') ? 'active' : '' ?>" href="<?= View::escape(Config::url('/dashboard')) ?>">Portal access</a>
  <?php if (($user['role_slug'] ?? '') === 'administrator'): ?>
  <a class="nav-link <?= $currentPath === Config::url('/admin/security-check') ? 'active' : '' ?>" href="<?= View::escape(Config::url('/admin/security-check')) ?>">Admin access check</a>
  <?php endif; ?>
  <a class="nav-link <?= $currentPath === Config::url('/change-password') ? 'active' : '' ?>" href="<?= View::escape(Config::url('/change-password')) ?>">Change password</a>
 </nav>
 <hr>
 <form method="post" action="<?= View::escape(Config::url('/logout')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <button type="submit" class="btn btn-outline-secondary w-100">Sign out</button>
 </form>
 <p class="prospak-footnote mt-4">Phase 1 · Secure account foundation</p>
</aside>
