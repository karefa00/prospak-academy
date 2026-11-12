<div class="auth-form"><span class="eyebrow">WELCOME BACK</span><h2>Sign in to your portal</h2><p>Use the account created by your institution.</p>
<form method="post" action="/login" data-validate><?= csrf_field() ?>
<label class="form-label" for="login-email">Email address</label><input class="form-control form-control-lg" type="email" id="login-email" name="email" autocomplete="username" maxlength="254" required>
<label class="form-label mt-3" for="login-pwd">Password</label><div class="password-wrap"><input class="form-control form-control-lg" type="password" id="login-pwd" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-toggle-password="login-pwd" aria-label="Show password">Show</button></div>
<button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Sign in</button></form><div class="auth-note">Access is managed by your school administrator.</div></div>
