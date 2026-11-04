<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
use Prospak\Core\Clock;
$student = $person['role_slug'] === 'student';
$lecturer = $person['role_slug'] === 'lecturer';
?>
<div class="prospak-content-card">
 <?php require dirname(__DIR__) . '/components/alerts.php'; ?>
 <?php if (isset($credential) && is_string($credential)): ?>
 <div class="alert alert-warning border border-warning" role="status">
  <h2 class="h5">One-time temporary password</h2>
  <p class="mb-2">Show this temporary password only to the intended account holder through a trusted private channel. It is not stored in plaintext and will not be shown again after this page is rendered. They must change it at first sign-in.</p>
  <p class="mb-2">Account: <strong><?= View::escape($person['email']) ?></strong></p>
  <code class="prospak-credential fw-bold text-dark fs-6"><?= View::escape($credential) ?></code>
 </div>
 <?php endif; ?>
 <div class="d-flex justify-content-between flex-wrap gap-3 align-items-start mb-4">
  <div><h2 class="h4 mb-1"><?= View::escape($person['full_name']) ?></h2><p class="text-muted mb-0"><?= View::escape($person['role_label']) ?> · <?= View::escape($person['email']) ?></p></div>
  <?php if ($canManage): ?><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="<?= View::escape(Config::url('/admin/users/edit') . '?id=' . (int) $person['id']) ?>">Edit profile</a><a class="btn btn-outline-secondary" href="<?= View::escape(Config::url('/admin/users')) ?>">All users</a></div><?php endif; ?>
 </div>
 <div class="row g-4">
  <div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-body p-4">
   <h3 class="h5 mb-4">Profile details</h3>
   <?php if($student && $person['passport_filename']): ?><img class="prospak-passport mb-3" alt="Student passport photo" src="<?= View::escape(Config::url('/student-photo') . '?id=' . (int) $person['id']) ?>"><?php endif; ?>
   <dl class="row">
    <dt class="col-sm-5">Account ID</dt><dd class="col-sm-7"><?= (int)$person['id'] ?></dd>
    <dt class="col-sm-5">Role</dt><dd class="col-sm-7"><?= View::escape($person['role_label']) ?></dd>
    <dt class="col-sm-5">Email</dt><dd class="col-sm-7 text-break"><?= View::escape($person['email']) ?></dd>
    <?php if($student): ?>
     <dt class="col-sm-5">Matriculation number</dt><dd class="col-sm-7"><?= View::escape($person['matriculation_number']) ?></dd>
     <dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= View::escape($person['student_first_name'] . ' ' . $person['student_last_name']) ?></dd>
     <dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= View::escape($person['student_phone'] ?? '—') ?></dd>
     <dt class="col-sm-5">Gender</dt><dd class="col-sm-7"><?= View::escape($person['gender'] ? str_replace('_',' ',$person['gender']) : 'Not recorded') ?></dd>
     <dt class="col-sm-5">Date of birth</dt><dd class="col-sm-7"><?= View::escape($person['date_of_birth'] ?? '—') ?></dd>
     <dt class="col-sm-5">Admission year</dt><dd class="col-sm-7"><?= View::escape($person['admission_year'] ?? '—') ?></dd>
    <?php elseif($lecturer): ?>
     <dt class="col-sm-5">Staff number</dt><dd class="col-sm-7"><?= View::escape($person['staff_number']) ?></dd>
     <dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= View::escape($person['lecturer_first_name'] . ' ' . $person['lecturer_last_name']) ?></dd>
     <dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= View::escape($person['lecturer_phone'] ?? '—') ?></dd>
     <dt class="col-sm-5">Designation</dt><dd class="col-sm-7"><?= View::escape($person['designation'] ?? '—') ?></dd>
    <?php endif; ?>
    <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= View::escape(ucfirst($person['account_status'])) ?></dd>
    <dt class="col-sm-5">Password setup</dt><dd class="col-sm-7"><?= (int)$person['must_change_password']===1 ? 'First login change required':'Completed' ?></dd>
    <dt class="col-sm-5">Created (WAT)</dt><dd class="col-sm-7"><?= View::escape(Clock::display($person['created_at'])) ?></dd>
   </dl>
  </div></div></div>
  <?php if ($canManage): ?>
  <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body p-4">
   <h3 class="h5">Administrative actions</h3>
   <p class="small text-muted">All changes are logged. Suspensions invalidate existing sessions.</p>
   <form method="post" action="<?= View::escape(Config::url('/admin/users/status')) ?>" class="mb-3">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
    <input type="hidden" name="status" value="<?= $person['account_status']==='active'?'suspended':'active' ?>">
    <button type="submit" class="btn <?= $person['account_status']==='active'?'btn-outline-danger':'btn-outline-success' ?> w-100" <?= $person['id']===$user['id']?'disabled':'' ?>><?= $person['account_status']==='active'?'Suspend account':'Reactivate account' ?></button>
   </form>
   <form method="post" action="<?= View::escape(Config::url('/admin/users/temporary-password')) ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
    <button type="submit" class="btn btn-outline-warning w-100" <?= $person['id']===$user['id']?'disabled':'' ?>>Issue new temporary password</button>
   </form>
  </div></div>
  <?php if($student): ?><div class="card shadow-sm mt-4"><div class="card-body p-4">
   <h3 class="h5">Student passport photo</h3>
   <p class="small text-muted">JPEG, PNG or WebP, maximum 2 MB. Files are served only after access checks.</p>
   <form enctype="multipart/form-data" action="<?= View::escape(Config::url('/admin/users/photo')) ?>" method="post">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
    <input type="file" name="passport" class="form-control mb-3" accept="image/jpeg,image/png,image/webp" required>
    <button class="btn btn-primary" type="submit">Upload photo</button>
   </form>
  </div></div><?php endif; ?>
  </div>
  <?php endif; ?>
 </div>
</div>
