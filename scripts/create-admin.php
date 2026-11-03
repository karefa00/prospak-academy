<?php
declare(strict_types=1);

use Prospak\Core\Database;
use Prospak\Core\Validation;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap/app.php';

$options = getopt('', ['email:', 'name:']);
$email = Validation::email($options['email'] ?? null);
$name = Validation::name($options['name'] ?? null);
if (!$email || !$name || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Usage (interactive terminal only): php scripts/create-admin.php --email=admin@example.org --name='System Administrator'\n");
    exit(1);
}
try {
    $pdo = (new Database())->pdo();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count !== 0) {
        fwrite(STDERR, "Bootstrap disabled: the users table is not empty. Use Phase 2 account management for further accounts.\n");
        exit(1);
    }
    $role = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1")->fetchColumn();
    if (!$role) {
        throw new RuntimeException('Administrator role is missing.');
    }
    fwrite(STDOUT, 'Enter a strong password (12-72 bytes; input hidden): ');
    system('stty -echo');
    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        system('stty echo');
        fwrite(STDOUT, "\n");
    }
    if (!Validation::password($password)) {
        fwrite(STDERR, "Password must be between 12 and 72 bytes.\n");
        exit(1);
    }
    $pdo->beginTransaction();
    try {
        // Serialize concurrent bootstrap attempts against the same reference row.
        $lock = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator' FOR UPDATE");
        $lock->fetchColumn();
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
            throw new RuntimeException('Bootstrap account already exists.');
        }
        $insert = $pdo->prepare("INSERT INTO users (role_id, full_name, email, password_hash) VALUES (:role, :name, :email, :hash)");
        $insert->execute(['role' => $role, 'name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
        $userId = (int) $pdo->lastInsertId();
        $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, subject_user_id, event_type) VALUES (:actor, :subject, 'auth.bootstrap_admin')");
        $audit->execute(['actor' => $userId, 'subject' => $userId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
    unset($password);
    fwrite(STDOUT, "Administrator created. No password printed or stored in plaintext.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Administrator setup failed. Verify the database, schema, and configuration.\n");
    exit(1);
}
