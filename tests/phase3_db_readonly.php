<?php
declare(strict_types=1);

/** Read-only Phase 3 MySQL checks. Requires a deployed Phase 3 schema and PDO MySQL. */
require dirname(__DIR__).'/bootstrap/app.php';
use Prospak\Core\Database;

function requireCondition(bool $ok,string $message):void {
    if (!$ok) { fwrite(STDERR,"FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}
try {
    $pdo=(new Database())->pdo();
    $expected=['faculties','departments','programmes','academic_levels','students','lecturers'];
    $query=$pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
    foreach($expected as $table) { $query->execute(['table'=>$table]); requireCondition((bool)$query->fetchColumn(),"table {$table} exists"); }
    $fields=['students'=>['programme_id','academic_level_id'],'lecturers'=>['department_id']];
    $query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
    foreach($fields as $table=>$cols) { foreach($cols as $col) { $query->execute(['table'=>$table,'column'=>$col]); requireCondition((int)$query->fetchColumn()===1,"column {$table}.{$col} exists"); } }
    $fks=['fk_departments_faculty','fk_programmes_department','fk_students_programme','fk_students_level','fk_lecturers_department'];
    $query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name=:name');
    foreach($fks as $fk) { $query->execute(['name'=>$fk]); requireCondition((int)$query->fetchColumn()===1,"foreign key {$fk} exists"); }
    $query=$pdo->query("SELECT r.slug AS role_slug, p.slug AS permission_slug FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE p.slug IN ('academics.view','academics.manage') ORDER BY r.slug,p.slug");
    $grants=[];
    foreach($query->fetchAll() as $row) { $grants[$row['role_slug']][]=$row['permission_slug']; }
    requireCondition(in_array('academics.manage',$grants['administrator']??[],true),'administrator has manage grant');
    requireCondition(in_array('academics.view',$grants['academic_officer']??[],true),'academic officer has view grant');
    requireCondition(!in_array('academics.manage',$grants['academic_officer']??[],true),'academic officer cannot manage');
    requireCondition(!isset($grants['student'])&&!isset($grants['lecturer']),'students and lecturers have no global academic grants');
    echo "SUCCESS: Phase 3 read-only MySQL schema and grant checks passed.\n";
} catch (\Throwable $e) {
    fwrite(STDERR,"Unable to perform Phase 3 MySQL checks. Confirm DB_DATABASE, DB_USERNAME, PDO MySQL, and migration state. Type=".get_class($e)."\n");
    exit(1);
}
