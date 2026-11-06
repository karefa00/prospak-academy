<?php
use Prospak\Core\View;
?>
<?php require dirname(__DIR__) . '/components/alerts.php'; ?>
<div class="row g-4">
 <div class="col-xl-8">
  <div class="card shadow-sm h-100"><div class="card-body p-4">
   <p class="small fw-semibold text-primary mb-2">PROSPAK ACADEMY · PHASE 3</p>
   <h2 class="h3">Welcome, <?= View::escape($user['full_name']) ?>.</h2>
   <p class="text-muted">Your account is authenticated. You may view your profile now. Account, student and lecturer management is available to administrators. Academic organisation is available to administrators and academic officers. Courses and grades remain scheduled for later approved phases.</p>
   <div class="d-flex flex-wrap gap-2 mt-4"><span class="badge bg-primary">Authentication active</span><span class="badge bg-secondary">Academic records pending</span></div>
  </div></div>
 </div>
 <div class="col-xl-4">
  <div class="card shadow-sm h-100"><div class="card-body p-4">
   <h3 class="h5">Your account</h3>
   <dl class="mb-0"><dt>Role</dt><dd><?= View::escape(ucwords(str_replace('_', ' ', $user['role_slug']))) ?></dd><dt>Email</dt><dd class="text-break"><?= View::escape($user['email']) ?></dd><dt>Status</dt><dd><?= View::escape($user['account_status']) ?></dd></dl>
  </div></div>
 </div>
</div>
