<?php
declare(strict_types=1);
// Optional CLI housekeeping; safe to schedule daily when Phase 1 is deployed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/app.php';
try {
    $pdo = (new \Prospak\Core\Database())->pdo();
    $attempts = $pdo->exec('DELETE FROM login_attempts WHERE occurred_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 30 DAY)');
    $tokens = $pdo->exec('DELETE FROM password_reset_tokens WHERE expires_at < UTC_TIMESTAMP(6)');
    echo "Pruned expired authentication records. Attempts: $attempts; tokens: $tokens.\n";
} catch (\Throwable $exception) {
    fwrite(STDERR, "Prune failed; verify database configuration.\n");
    exit(1);
}
