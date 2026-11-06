<?php
use Prospak\Core\Config;
use Prospak\Core\View;
?>
<div class="prospak-content-card">
 <a href="<?= View::escape(Config::url('/academics/'.$entity)) ?>">← Back to academic list</a>
 <h2 class="h4 mt-3">Students associated with <?= View::escape($item['name']) ?></h2>
 <p class="text-muted"><?= View::escape($item['code']) ?> · <?= View::escape(\Prospak\Core\AcademicValidation::LABELS[$entity]) ?></p>
 <div class="card shadow-sm mb-4"><div class="card-body"><form method="get" action="<?= View::escape(Config::url('/academics/students')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
 <input type="hidden" name="entity" value="<?= View::escape($entity) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
 <div class="flex-grow-1"><label for="student-search" class="form-label">Name or matriculation number</label><input id="student-search" name="q" class="form-control" maxlength="120" value="<?= View::escape($search) ?>"></div><button class="btn btn-outline-primary" type="submit">Search</button>
 </form></div></div>
 <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover prospak-table mb-0"><thead><tr><th class="ps-3">Name</th><th>Matriculation no.</th><th>Programme</th><th>Department</th><th>Level</th><th>Status</th><th>Profile</th></tr></thead><tbody>
 <?php foreach ($listing['rows'] as $student): ?><tr>
 <td class="ps-3"><?= View::escape($student['full_name']) ?></td><td><?= View::escape($student['matriculation_number']) ?></td><td><?= View::escape($student['programme_name']??'Unassigned') ?></td><td><?= View::escape($student['department_name']??'Unassigned') ?></td><td><?= View::escape($student['level_name']??'Unassigned') ?></td><td><?= View::escape($student['account_status']) ?></td>
 <td><?php if($canManage): ?><a href="<?= View::escape(Config::url('/admin/users/view').'?id='.(int)$student['user_id']) ?>">View</a><?php else: ?>—<?php endif; ?></td>
 </tr><?php endforeach; ?>
 <?php if (!$listing['rows']): ?><tr><td class="text-muted text-center p-4" colspan="7">No associated students found.</td></tr><?php endif; ?>
 </tbody></table></div><div class="card-body d-flex justify-content-between flex-wrap gap-2"><small class="text-muted"><?= (int)$listing['total'] ?> students · Page <?= (int)$listing['page'] ?> of <?= (int)$listing['pages'] ?></small>
 <div class="d-flex gap-2"><?php foreach(['Previous'=>$listing['page']-1,'Next'=>$listing['page']+1] as $label=>$p): ?>
 <?php if($p>=1&&$p<=$listing['pages']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= View::escape(Config::url('/academics/students').'?'.http_build_query(['entity'=>$entity,'id'=>$item['id'],'q'=>$search,'page'=>$p])) ?>"><?= View::escape($label) ?></a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true"><?= View::escape($label) ?></span><?php endif; ?>
 <?php endforeach; ?></div></div></div>
</div>
