<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
?>
<div class="card shadow-sm prospak-password-card">
 <div class="card-header"><h2 class="h5 mb-0">Account security</h2></div>
 <div class="card-body">
  <p class="text-muted">Changing your password invalidates your other signed-in sessions.</p>
  <?php require dirname(__DIR__) . '/components/alerts.php'; ?>
  <form method="post" action="<?= View::escape(Config::url('/change-password')) ?>">
   <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
   <div class="form-group mb-3"><label class="form-label" for="current">Current password</label><input class="form-control" id="current" name="current_password" type="password" autocomplete="current-password" required></div>
   <div class="form-group mb-3"><label class="form-label" for="new">New password</label><input class="form-control" id="new" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
   <div class="form-group mb-4"><label class="form-label" for="confirm">Confirm new password</label><input class="form-control" id="confirm" name="new_password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
   <button class="btn btn-primary" type="submit">Update password</button>
  </form>
 </div>
</div>
