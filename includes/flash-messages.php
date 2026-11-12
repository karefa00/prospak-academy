<?php
$notice=$_SESSION['flash']??null;unset($_SESSION['flash']);
if(is_array($notice) && !empty($notice['message'])):
?><div class="alert alert-<?= e($notice['kind']??'info') ?>" role="status"><?= e($notice['message']) ?></div><?php endif; ?>
