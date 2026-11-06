<?php
use Prospak\Core\Config;
use Prospak\Core\View;
$labels=\Prospak\Core\AcademicValidation::LABELS;
?>
<div class="prospak-content-card">
 <?php require dirname(__DIR__).'/components/alerts.php'; ?>
 <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
  <div><p class="small fw-bold text-primary mb-1">PROSPAK ACADEMY · ACADEMIC ORGANISATION</p><h2 class="h4 mb-1"><?= View::escape($labels[$entity]) ?></h2><p class="text-muted mb-0">Manage academic structure without deleting student records.</p></div>
  <?php if ($canManage): ?><a class="btn btn-primary" href="<?= View::escape(Config::url('/academics/'.$entity.'/form')) ?>">+ Add <?= View::escape(\Prospak\Core\AcademicValidation::SINGULAR[$entity]) ?></a><?php endif; ?>
 </div>
 <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Academic organisation sections">
 <?php foreach ($labels as $key=>$label): ?>
  <a class="btn btn-sm <?= $key===$entity?'btn-primary':'btn-outline-primary' ?>" href="<?= View::escape(Config::url('/academics/'.$key)) ?>"><?= View::escape($label) ?></a>
 <?php endforeach; ?>
 </nav>
 <div class="card shadow-sm mb-4"><div class="card-body"><form method="get" action="<?= View::escape(Config::url('/academics/'.$entity)) ?>" class="row g-3 align-items-end">
  <div class="col-md-7"><label class="form-label" for="academic-search">Search code or name</label><input id="academic-search" class="form-control" name="q" maxlength="120" value="<?= View::escape($search) ?>"></div>
  <div class="col-md-3"><label class="form-label" for="academic-status">Status</label><select id="academic-status" class="form-select" name="status"><option value="">All</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option></select></div>
  <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
 </form></div></div>
 <div class="card shadow-sm"><div class="table-responsive"><table class="table prospak-table table-hover mb-0"><thead><tr><th scope="col" class="ps-3">Code</th><th scope="col">Name</th>
  <?php if ($entity==='departments' || $entity==='programmes'): ?><th scope="col">Parent</th><?php endif; ?>
  <?php if ($entity==='levels'): ?><th scope="col">Order</th><?php endif; ?>
  <th scope="col">Status</th><th scope="col">Students</th><th scope="col">Actions</th></tr></thead><tbody>
 <?php foreach ($listing['rows'] as $entry): ?><tr>
  <td class="ps-3 fw-semibold"><?= View::escape($entry['code']) ?></td><td><?= View::escape($entry['name']) ?></td>
  <?php if ($entity==='departments' || $entity==='programmes'): ?><td><?= View::escape($entry['parent_name']??'—') ?></td><?php endif; ?>
  <?php if ($entity==='levels'): ?><td><?= (int)$entry['sort_order'] ?></td><?php endif; ?>
  <td><span class="badge bg-<?= $entry['status']==='active'?'success':'secondary' ?>"><?= View::escape(ucfirst($entry['status'])) ?></span></td>
  <td><a href="<?= View::escape(Config::url('/academics/students').'?'.http_build_query(['entity'=>$entity,'id'=>(int)$entry['id']])) ?>"><?= (int)$entry['student_count'] ?> students</a></td>
  <td><?php if($canManage): ?><a class="btn btn-sm btn-outline-primary" href="<?= View::escape(Config::url('/academics/'.$entity.'/form').'?id='.(int)$entry['id']) ?>">Edit</a><?php else: ?><span class="text-muted">View only</span><?php endif; ?></td>
 </tr><?php endforeach; ?>
 <?php if (!$listing['rows']): ?><tr><td colspan="7" class="text-muted text-center p-4">No academic records match the filters.</td></tr><?php endif; ?>
 </tbody></table></div>
 <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center"><span class="text-muted small"><?= (int)$listing['total'] ?> records · Page <?= (int)$listing['page'] ?> of <?= (int)$listing['pages'] ?></span>
 <div class="d-flex gap-2">
 <?php foreach(['Previous'=>$listing['page']-1,'Next'=>$listing['page']+1] as $text=>$p): ?>
  <?php if($p>=1&&$p<=$listing['pages']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= View::escape(Config::url('/academics/'.$entity).'?'.http_build_query(['q'=>$search,'status'=>$statusFilter,'page'=>$p])) ?>"><?= View::escape($text) ?></a>
  <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true"><?= View::escape($text) ?></span><?php endif; ?>
 <?php endforeach; ?></div>
 </div></div>
</div>
