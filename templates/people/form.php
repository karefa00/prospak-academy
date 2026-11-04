<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
use Prospak\Core\PeopleValidation;
$v = static fn(string $key): string => View::escape(is_string($values[$key] ?? null) || is_numeric($values[$key] ?? null) ? $values[$key] : '');
$role = is_string($values['role'] ?? null) ? $values['role'] : '';
$academic = in_array($role,['student','lecturer'],true);
?>
<div class="prospak-content-card">
 <div class="d-flex justify-content-between align-items-center mb-3 gap-3"><div><h2 class="h4 mb-1"><?= $isEdit ? 'Edit account' : 'Create administrator-managed account' ?></h2><p class="text-muted mb-0">All fields are validated on the server. An initial temporary password will be displayed once.</p></div><a class="btn btn-outline-secondary" href="<?= View::escape(Config::url('/admin/users')) ?>">Back</a></div>
 <?php if (!empty($errors)): ?><div class="alert alert-danger" role="alert">Please correct the form errors. <?= View::escape($errors['form'] ?? '') ?></div><?php endif; ?>
 <div class="card shadow-sm"><div class="card-body p-4">
  <form method="post" action="<?= View::escape(Config::url($isEdit ? '/admin/users/edit' : '/admin/users/new')) ?>" id="people-form" data-edit="<?= $isEdit ? '1':'0' ?>">
   <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
   <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$targetId ?>"><?php endif; ?>
   <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="role">Account role *</label>
     <?php if ($isEdit && in_array($existingRole,['student','lecturer'],true)): ?>
      <input type="hidden" name="role" id="role" value="<?= View::escape($existingRole) ?>"><p class="form-control-plaintext fw-semibold"><?= View::escape(ucfirst($existingRole)) ?> (fixed to preserve academic identity)</p>
     <?php else: ?>
      <select class="form-select" name="role" id="role" required>
       <?php foreach (PeopleValidation::ROLES as $option): ?>
        <?php if ($isEdit && in_array($option,['student','lecturer'],true)) { continue; } ?>
        <option value="<?= View::escape($option) ?>" <?= $role===$option ? 'selected':'' ?>><?= View::escape(ucwords(str_replace('_',' ',$option))) ?></option>
       <?php endforeach; ?>
      </select>
     <?php endif; ?>
     <?php if(isset($errors['role'])): ?><div class="text-danger small"><?= View::escape($errors['role']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6"><label class="form-label" for="email">Email *</label><input type="email" class="form-control" id="email" name="email" value="<?= $v('email') ?>" maxlength="254" autocomplete="off" required>
      <?php if(isset($errors['email'])): ?><div class="text-danger small"><?= View::escape($errors['email']) ?></div><?php endif; ?></div>
   </div>
   <div class="row g-3 mt-1" data-for-role="basic" <?= $academic ? 'hidden' : '' ?>>
    <div class="col-md-12"><label class="form-label" for="full-name">Full name *</label><input class="form-control" id="full-name" name="full_name" maxlength="150" value="<?= $v('full_name') ?>" data-required-for="basic">
     <?php if(isset($errors['full_name'])): ?><div class="text-danger small"><?= View::escape($errors['full_name']) ?></div><?php endif; ?></div>
   </div>
   <div class="row g-3 mt-1" data-for-role="person" <?= $academic ? '' : 'hidden' ?>>
    <div class="col-md-6"><label class="form-label" for="first-name">First name *</label><input class="form-control" id="first-name" name="first_name" maxlength="75" value="<?= $v('first_name') ?>" data-required-for="person">
     <?php if(isset($errors['first_name'])): ?><div class="text-danger small"><?= View::escape($errors['first_name']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="last-name">Last name *</label><input class="form-control" id="last-name" name="last_name" maxlength="75" value="<?= $v('last_name') ?>" data-required-for="person">
     <?php if(isset($errors['last_name'])): ?><div class="text-danger small"><?= View::escape($errors['last_name']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" maxlength="30" value="<?= $v('phone') ?>" placeholder="+234..."><div class="prospak-form-help">Optional phone contact.</div>
     <?php if(isset($errors['phone'])): ?><div class="text-danger small"><?= View::escape($errors['phone']) ?></div><?php endif; ?></div>
   </div>
   <div class="row g-3 mt-1" data-for-role="student" <?= $role === 'student' ? '' : 'hidden' ?>>
    <div class="col-md-6"><label class="form-label" for="matric">Issued matriculation number *</label><input class="form-control" id="matric" name="matriculation_number" maxlength="40" value="<?= $v('matriculation_number') ?>" data-required-for="student"><div class="prospak-form-help">Use the Academy-issued ID. Auto-numbering requires an approved policy.</div>
     <?php if(isset($errors['matriculation_number'])): ?><div class="text-danger small"><?= View::escape($errors['matriculation_number']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="gender">Gender (optional)</label><select class="form-select" name="gender" id="gender"><option value="">Not recorded</option>
      <?php foreach (['female'=>'Female','male'=>'Male','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $option=>$label): ?><option value="<?= View::escape($option) ?>" <?= ($values['gender'] ?? '')===$option?'selected':'' ?>><?= View::escape($label) ?></option><?php endforeach; ?>
     </select><?php if(isset($errors['gender'])): ?><div class="text-danger small"><?= View::escape($errors['gender']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="dob">Date of birth</label><input type="date" class="form-control" name="date_of_birth" id="dob" value="<?= $v('date_of_birth') ?>"><?php if(isset($errors['date_of_birth'])): ?><div class="text-danger small"><?= View::escape($errors['date_of_birth']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="year">Admission year</label><input type="number" class="form-control" id="year" name="admission_year" min="1901" max="2155" value="<?= $v('admission_year') ?>"><?php if(isset($errors['admission_year'])): ?><div class="text-danger small"><?= View::escape($errors['admission_year']) ?></div><?php endif; ?></div>
   </div>
   <div class="row g-3 mt-1" data-for-role="lecturer" <?= $role === 'lecturer' ? '' : 'hidden' ?>>
    <div class="col-md-6"><label class="form-label" for="staff">Issued staff number *</label><input class="form-control" id="staff" name="staff_number" maxlength="40" value="<?= $v('staff_number') ?>" data-required-for="lecturer"><div class="prospak-form-help">Enter the existing Academy-issued staff identifier.</div>
      <?php if(isset($errors['staff_number'])): ?><div class="text-danger small"><?= View::escape($errors['staff_number']) ?></div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label" for="designation">Designation (optional)</label><input class="form-control" name="designation" id="designation" maxlength="120" value="<?= $v('designation') ?>"><?php if(isset($errors['designation'])): ?><div class="text-danger small"><?= View::escape($errors['designation']) ?></div><?php endif; ?></div>
   </div>
   <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="<?= View::escape(Config::url('/admin/users')) ?>">Cancel</a><button type="submit" class="btn btn-primary"><?= $isEdit?'Save changes':'Create account' ?></button></div>
  </form>
 </div></div>
</div>
<script src="<?= View::escape(Config::url('/assets/js/people-form.js')) ?>" defer></script>
