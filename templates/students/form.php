<div class="page-header"><div><p class="eyebrow">STUDENT ADMISSION</p><h1>Register student</h1><p>The account is created with a temporary password and profile.</p></div><a href="/students" class="btn btn-outline-secondary">Back</a></div>
<section class="panel"><form action="/students/create" method="post" enctype="multipart/form-data" data-validate><?= csrf_field() ?><div class="form-grid">
<label class="form-label">First name<input class="form-control" name="first_name" required maxlength="75"></label>
<label class="form-label">Last name<input class="form-control" name="last_name" required maxlength="75"></label>
<label class="form-label">Student email<input type="email" class="form-control" name="email" required maxlength="254"></label>
<label class="form-label">Matriculation number<input class="form-control" name="matriculation_number" maxlength="40" placeholder="PA2026001" required></label>
<label class="form-label">Programme<select class="form-select" name="programme_id" required><option value="">Select programme</option><?php foreach($programmes as $r):?><option value="<?= (int)$r['id'] ?>"><?= e($r['department'].' · '.$r['name']) ?></option><?php endforeach; ?></select></label>
<label class="form-label">Academic level<select class="form-select" name="academic_level_id" required><option value="">Select level</option><?php foreach($levels as $r):?><option value="<?= (int)$r['id'] ?>"><?= e($r['code'].' · '.$r['name']) ?></option><?php endforeach; ?></select></label>
<label class="form-label">Temporary password<input class="form-control" name="temporary_password" type="password" minlength="10" maxlength="72" autocomplete="new-password" required></label>
<label class="form-label">Passport photo (optional; JPG, PNG, WebP; max 2MB)<input type="file" class="form-control" name="passport" accept="image/jpeg,image/png,image/webp"></label>
</div><p class="form-hint">Passwords are stored as bcrypt hashes. Passport photos are stored outside the web root.</p><button class="btn btn-primary">Register student</button></form></section>
