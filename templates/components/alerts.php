<?php
use Prospak\Core\View;
$notice = $flash ?? null;
if (!empty($error)) { $notice = ['type' => 'danger', 'message' => $error]; }
?>
<?php if (is_array($notice) && isset($notice['message'])): ?>
<div class="alert alert-<?= View::escape(($notice['type'] ?? '') === 'success' ? 'success' : 'danger') ?>" role="alert">
 <?= View::escape($notice['message']) ?>
</div>
<?php endif; ?>
