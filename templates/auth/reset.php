<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
?>
<h1 class="h3 mb-2">Set a new password</h1>
<p class="text-muted mb-4">Use the secure link sent to your email. Links are valid for 30 minutes and can be used only once.</p>
<?php require dirname(__DIR__) . '/components/alerts.php'; ?>
<?php if (!empty($selector)): ?>
<form id="reset-password-form" method="post" action="<?= View::escape(Config::url('/reset-password')) ?>">
 <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
 <input type="hidden" name="selector" value="<?= View::escape($selector) ?>">
 <input type="hidden" name="token" id="reset-token" value="">
 <div class="form-group mb-3"><label class="form-label" for="password">New password</label>
  <input type="password" id="password" name="password" required class="form-control" minlength="12" maxlength="72" autocomplete="new-password">
 </div>
 <div class="form-group mb-3"><label class="form-label" for="confirmation">Confirm new password</label>
  <input type="password" id="confirmation" name="password_confirmation" required class="form-control" minlength="12" maxlength="72" autocomplete="new-password">
 </div>
 <p class="small text-muted" id="reset-token-info">The new password must be 12–72 bytes long.</p>
 <button type="submit" class="btn btn-primary w-100">Set new password</button>
</form>
<?php endif; ?>
<p class="mt-3"><a href="<?= View::escape(Config::url('/forgot-password')) ?>">Request a new link</a></p>
