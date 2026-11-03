<?php
use Prospak\Core\View;
?>
<header class="prospak-header d-flex align-items-center justify-content-between px-4 py-3">
 <h1 class="h5 mb-0"><?= View::escape($title ?? 'Portal') ?></h1>
 <span class="text-muted small"><?= View::escape($user['full_name'] ?? '') ?> · <?= View::escape(ucwords(str_replace('_', ' ', $user['role_slug'] ?? ''))) ?></span>
</header>
