<div class="page-header"><div><p class="eyebrow">ACCOUNT SECURITY</p><h2>Change password</h2><p><?= (int)$user['must_change_password']===1?'You must change your temporary password before continuing.':'Update your account password securely.' ?></p></div></div>
<section class="panel" style="max-width:620px"><form action="/change-password" method="post" data-validate><?= csrf_field() ?>
<div class="form-group"><label class="form-label">Current password<input class="form-control" type="password" name="current_password" autocomplete="current-password" required></label></div>
<div class="form-group"><label class="form-label">New password (10–72 characters)<input class="form-control" type="password" name="new_password" minlength="10" maxlength="72" autocomplete="new-password" required></label></div>
<div class="form-group"><label class="form-label">Confirm new password<input class="form-control" type="password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password" required></label></div>
<button class="btn btn-primary">Update password</button></form></section>
