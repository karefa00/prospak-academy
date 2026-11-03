<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
?>
<h1 class="mb-2 h3">Sign in</h1>
<p class="text-muted mb-4">Use your Prospak Academy account to continue.</p>
<?php require dirname(__DIR__) . '/components/alerts.php'; ?>
<form method="post" action="<?= View::escape(Config::url('/login')) ?>" autocomplete="on">
 <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
 <div class="form-group mb-3">
  <label for="email" class="form-label">Email</label>
  <input type="email" id="email" name="email" class="form-control" required maxlength="254" autocomplete="username" value="<?= View::escape($oldEmail ?? '') ?>">
 </div>
 <div class="form-group mb-3">
  <label for="password" class="form-label">Password</label>
  <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
 </div>
 <div class="d-flex justify-content-end mb-4"><a href="<?= View::escape(Config::url('/forgot-password')) ?>">Forgot password?</a></div>
 <button type="submit" class="btn btn-primary w-100">Sign in</button>
</form>
<p class="text-center text-muted mt-4 small">Accounts are created by Prospak Academy administrators. Public registration is unavailable.</p>
