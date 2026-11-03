<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
?>
<h1 class="h3 mb-2">Recover your password</h1>
<p class="text-muted mb-4">Enter your account email. We will send a reset link when email delivery is configured.</p>
<?php require dirname(__DIR__) . '/components/alerts.php'; ?>
<form method="post" action="<?= View::escape(Config::url('/forgot-password')) ?>">
 <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
 <div class="form-group mb-4"><label class="form-label" for="email">Email</label>
  <input class="form-control" id="email" name="email" type="email" maxlength="254" required autocomplete="email">
 </div>
 <button class="btn btn-primary w-100" type="submit">Request reset link</button>
</form>
<p class="mt-3"><a href="<?= View::escape(Config::url('/login')) ?>">Back to sign in</a></p>
