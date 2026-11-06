<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
use Prospak\Core\AcademicValidation as AV;
$get=static fn(string $key):string=>(string)($values[$key]??'');
$isEdit=isset($item['id']);
?>
<div class="prospak-content-card">
 <div class="mb-4"><a href="<?= View::escape(Config::url('/academics/'.$entity)) ?>">← Back to <?= View::escape(AV::LABELS[$entity]) ?></a><h2 class="h4 mt-3 mb-1"><?= $isEdit?'Edit':'Create' ?> academic record</h2>
 <p class="text-muted">Codes and relationships are checked server-side. Inactivation preserves existing records.</p></div>
 <div class="card shadow-sm"><div class="card-body p-4">
 <?php if(!empty($errors['form'])): ?><div role="alert" class="alert alert-danger"><?= View::escape($errors['form']) ?></div><?php endif; ?>
 <form method="post" action="<?= View::escape(Config::url('/academics/'.$entity.'/save')) ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <?php if($isEdit): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
  <div class="row g-3">
   <div class="col-lg-4"><label for="code" class="form-label">Unique code *</label><input class="form-control <?= isset($errors['code'])?'is-invalid':'' ?>" id="code" name="code" maxlength="<?= $entity==='programmes'?30:20 ?>" value="<?= View::escape($get('code')) ?>" required><div class="invalid-feedback"><?= View::escape($errors['code']??'') ?></div></div>
   <div class="col-lg-8"><label for="name" class="form-label">Name *</label><input class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" id="name" name="name" maxlength="150" value="<?= View::escape($get('name')) ?>" required><div class="invalid-feedback"><?= View::escape($errors['name']??'') ?></div></div>
   <?php if(in_array($entity,['departments','programmes'],true)): ?>
   <div class="col-lg-7"><label for="parent_id" class="form-label"><?= $entity==='departments'?'Faculty':'Department' ?> *</label>
    <select name="parent_id" id="parent_id" class="form-select <?= isset($errors['parent_id'])?'is-invalid':'' ?>" required><option value="">Select...</option>
     <?php foreach($options as $option): ?>
      <option value="<?= (int)$option['id'] ?>" <?= $get('parent_id')===(string)$option['id']?'selected':'' ?> <?= $option['status']!=='active' && $get('parent_id')!==(string)$option['id']?'disabled':'' ?>><?= View::escape($option['code'].' · '.$option['name'].($option['status']==='active'?'':' (inactive)')) ?></option>
     <?php endforeach; ?></select><div class="invalid-feedback"><?= View::escape($errors['parent_id']??'') ?></div>
   </div>
   <?php endif; ?>
   <?php if($entity==='levels'): ?><div class="col-lg-4"><label class="form-label" for="sort_order">Display order *</label><input class="form-control <?= isset($errors['sort_order'])?'is-invalid':'' ?>" type="number" id="sort_order" name="sort_order" min="1" max="9999" value="<?= View::escape($get('sort_order')) ?>" required><div class="invalid-feedback"><?= View::escape($errors['sort_order']??'') ?></div></div><?php endif; ?>
   <div class="col-lg-4"><label for="status" class="form-label">Status</label><select class="form-select <?= isset($errors['status'])?'is-invalid':'' ?>" id="status" name="status"><option value="active" <?= $get('status')==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $get('status')==='inactive'?'selected':'' ?>>Inactive</option></select><div class="invalid-feedback"><?= View::escape($errors['status']??'') ?></div></div>
  </div>
  <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Save <?= View::escape(AV::SINGULAR[$entity]) ?></button><a class="btn btn-outline-secondary" href="<?= View::escape(Config::url('/academics/'.$entity)) ?>">Cancel</a></div>
 </form></div></div>
</div>
