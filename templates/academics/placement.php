<?php
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\View;
$student=$person['role_slug']==='student';
?>
<div class="prospak-content-card">
 <a href="<?= View::escape(Config::url('/admin/users/view').'?id='.(int)$person['id']) ?>">← Back to account</a>
 <h2 class="h4 mt-3">Academic placement</h2>
 <p class="text-muted">Assign academic organisation for <?= View::escape($person['full_name']) ?> (<?= View::escape($person['email']) ?>). Only active entities can be newly assigned. Leaving a field blank makes it unassigned.</p>
 <?php if($error): ?><div role="alert" class="alert alert-danger"><?= View::escape($error) ?></div><?php endif; ?>
 <div class="card shadow-sm"><div class="card-body p-4"><form method="post" action="<?= View::escape(Config::url('/academics/placement')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$person['id'] ?>">
  <?php
  $select=static function(string $name,string $title,array $options) use ($values):void {
   $selected=(string)($values[$name]??'');
   echo '<div class="mb-3"><label class="form-label" for="'.View::escape($name).'">'.View::escape($title).'</label><select class="form-select" id="'.View::escape($name).'" name="'.View::escape($name).'"><option value="">Unassigned</option>';
   foreach($options as $option) {
    $key=(string)$option['id'];
    $disabled=$option['status']!=='active' && $key!==$selected;
    echo '<option value="'.(int)$option['id'].'"'.($selected===$key?' selected':'').($disabled?' disabled':'').'>'.View::escape($option['code'].' · '.$option['name'].($option['status']!=='active'?' (inactive)':'')).'</option>';
   }
   echo '</select></div>';
  };
  if($student) { $select('programme_id','Programme',$programmes); $select('academic_level_id','Academic level',$levels); }
  else { $select('department_id','Lecturer department',$departments); }
  ?>
  <button type="submit" class="btn btn-primary">Save academic placement</button>
 </form></div></div>
</div>
