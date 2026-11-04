<?php
use Prospak\Core\Config;
use Prospak\Core\View;
/** @var array $listing */
?>
<div class="prospak-content-card">
 <?php require dirname(__DIR__) . '/components/alerts.php'; ?>
 <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div><h2 class="h4 mb-1">Accounts and academic people</h2><p class="text-muted mb-0">Administrator-managed accounts. Student/lecturer academic placement begins in Phase 3.</p></div>
  <a class="btn btn-primary" href="<?= View::escape(Config::url('/admin/users/new')) ?>">+ Create account</a>
 </div>
 <div class="card shadow-sm mb-4"><div class="card-body">
  <form method="get" action="<?= View::escape(Config::url('/admin/users')) ?>" class="row g-3 align-items-end">
   <div class="col-md-5"><label class="form-label" for="search">Search name, email or ID</label><input class="form-control" type="search" id="search" name="q" maxlength="120" value="<?= View::escape($search) ?>" placeholder="Search accounts"></div>
   <div class="col-md-3"><label class="form-label" for="role">Role</label><select class="form-select" id="role" name="role"><option value="">All roles</option>
   <?php foreach (['administrator'=>'Administrator','academic_officer'=>'Academic Officer','lecturer'=>'Lecturer','student'=>'Student'] as $v=>$label): ?>
    <option value="<?= View::escape($v) ?>" <?= $roleFilter === $v ? 'selected' : '' ?>><?= View::escape($label) ?></option>
   <?php endforeach; ?></select></div>
   <div class="col-md-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option></select></div>
   <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
  </form>
 </div></div>
 <div class="card shadow-sm"><div class="card-body p-0">
  <div class="table-responsive"><table class="table prospak-table table-hover mb-0">
   <thead><tr><th scope="col" class="ps-3">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Institutional ID</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
   <tbody>
    <?php foreach ($listing['rows'] as $person): ?>
     <tr><td class="ps-3 fw-semibold"><?= View::escape($person['full_name']) ?></td><td class="text-break"><?= View::escape($person['email']) ?></td>
      <td><?= View::escape(ucwords(str_replace('_',' ',$person['role_slug']))) ?></td><td><?= View::escape($person['matriculation_number'] ?? $person['staff_number'] ?? '—') ?></td>
      <td><span class="badge bg-<?= $person['account_status']==='active' ? 'success':'secondary' ?>"><?= View::escape(ucfirst($person['account_status'])) ?></span></td>
      <td><a class="btn btn-sm btn-outline-primary" href="<?= View::escape(Config::url('/admin/users/view') . '?id=' . (int) $person['id']) ?>">View</a></td>
     </tr>
    <?php endforeach; ?>
    <?php if (!$listing['rows']): ?><tr><td colspan="6" class="text-center py-4 text-muted">No accounts match this search.</td></tr><?php endif; ?>
   </tbody></table></div>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3">
   <small class="text-muted"><?= (int)$listing['total'] ?> total account(s) · Page <?= (int)$listing['page'] ?> of <?= (int)$listing['pages'] ?></small>
   <nav aria-label="User list pages" class="d-flex gap-2">
    <?php foreach (['Previous'=>$listing['page'] - 1, 'Next'=>$listing['page'] + 1] as $label=>$num): ?>
     <?php if ($num>=1 && $num<=$listing['pages']): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= View::escape(Config::url('/admin/users') . '?' . http_build_query(['q'=>$search,'role'=>$roleFilter,'status'=>$statusFilter,'page'=>$num])) ?>"><?= View::escape($label) ?></a>
     <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true"><?= View::escape($label) ?></span><?php endif; ?>
    <?php endforeach; ?>
   </nav>
  </div>
 </div></div>
</div>
