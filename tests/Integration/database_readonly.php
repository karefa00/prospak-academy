<?php
declare(strict_types=1);
require_once __DIR__.'/../../bootstrap/app.php';
if(!in_array('mysql',PDO::getAvailableDrivers(),true)){fwrite(STDERR,"SKIP: pdo_mysql is not installed\n");exit(2);}
$db=db();
$required=['roles','users','students','lecturers','faculties','departments','programmes','academic_levels','academic_sessions','semesters','courses','course_programmes','course_prerequisites','course_assignments','course_registrations','assessments','student_scores','results','result_batches','result_reviews','result_publications','grading_schemes','grading_bands','offering_grading_schemes','academic_policies'];
$q=$db->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');$have=$q->fetchAll(PDO::FETCH_COLUMN);
$missing=array_values(array_diff($required,$have));if($missing)throw new RuntimeException('Missing tables: '.implode(',',$missing));
foreach(['users','students','courses','course_registrations','results'] as $name){$count=(int)$db->query('SELECT COUNT(*) FROM `'.$name.'`')->fetchColumn();echo "$name: $count\n";}
echo 'PASS: '.count($required).' critical relational tables verified (read only).'.PHP_EOL;
