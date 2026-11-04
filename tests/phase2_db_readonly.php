<?php
declare(strict_types=1);

// Optional READ-ONLY integration checks. Run against a backed-up Phase 2 MySQL database.
require dirname(__DIR__) . '/bootstrap/app.php';
use Prospak\Core\Database;
use Prospak\Repositories\PeopleRepository;

try {
    $pdo = (new Database())->pdo();
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('users','students','lecturers','roles','permissions','role_permissions')")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) !== 6) { throw new RuntimeException('Expected user, student, lecturer and authorization tables.'); }
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetchAll();
    if (count($columns) !== 1) { throw new RuntimeException('Phase 2 column missing.'); }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='administrator' AND p.slug='users.manage'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() !== 1) { throw new RuntimeException('Administrator permission missing.'); }
    $repo = new PeopleRepository($pdo);
    $page = $repo->page('', '', '', 1, 2);
    if (!array_key_exists('rows',$page) || $page['perPage']!==2 || $page['page']!==1) { throw new RuntimeException('List query failed.'); }
    $none = $repo->page('some-account-that-must-not-exist-phase2','student','active',9999,2);
    if ($none['total']!==0 || $none['page']!==1) { throw new RuntimeException('Empty result pagination failed.'); }
    $fks = $pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('fk_students_user','fk_lecturers_user')")->fetchColumn();
    if ((int)$fks !== 2) { throw new RuntimeException('Foreign keys missing.'); }
    echo "PASS Phase 2 read-only MySQL checks: schema, permission, two FKs and filtered/paginated PDO queries.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL Phase 2 MySQL read-only check: ' . get_class($e) . ' (details available to server operator)'."\n");
    exit(1);
}
