<div class="page-header"><div><p class="eyebrow">USER MANAGEMENT</p><h1>Register staff</h1><p>Create an academic officer or lecturer account.</p></div><a href="/users" class="btn btn-outline-secondary">Back</a></div>
<section class="panel"><form action="/users/create" method="post" data-validate><?= csrf_field() ?><div class="form-grid">
<label class="form-label">Role<select class="form-select" name="role" data-staff-role required><option value="academic_officer">Academic Officer</option><option value="lecturer">Lecturer</option></select></label>
<label class="form-label">Full name<input class="form-control" name="full_name" required maxlength="150"></label>
<label class="form-label">Email address<input class="form-control" name="email" type="email" required maxlength="254"></label>
<label class="form-label">Temporary password<input class="form-control" name="temporary_password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label>
<label class="form-label" data-lecturer-only>Staff number<input class="form-control" name="staff_number" maxlength="40" placeholder="LEC001"></label>
<label class="form-label" data-lecturer-only>Department<select class="form-select" name="department_id"><option value="">Select department</option><?php foreach($departments as $r):?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></label></div><button class="btn btn-primary mt-3">Create staff account</button></form></section>
