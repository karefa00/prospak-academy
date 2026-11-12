<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$allow=['public','src','api','process','includes','templates','config','database','bootstrap','storage','tests'];
$passes=0;
foreach(scandir($root) as $item){
 if($item==='.'||$item==='..')continue;
 if(is_dir($root.'/'.$item) && !in_array($item,$allow,true))throw new RuntimeException('Unexpected root folder: '.$item);
}
$passes++;
foreach(['composer.json','composer.lock','vendor','bootstrap/autoload.php','public/router.php','app','routes','docs','deploy','scripts'] as $forbidden){
 if(file_exists($root.'/'.$forbidden))throw new RuntimeException('Forbidden dependency or folder: '.$forbidden);
 $passes++;
}
foreach(['public/index.php','public/.htaccess','src/Services/AuthService.php','src/Controllers/GradeController.php','templates/layouts/dashboard.php','includes/sidebar.php','database/schema/prospak_academy.sql','README.md'] as $required){
 if(!is_file($root.'/'.$required))throw new RuntimeException('Missing: '.$required);
 $passes++;
}
$source=file_get_contents($root.'/bootstrap/app.php');
if(str_contains($source,'spl_autoload_register')||str_contains($source,'vendor/autoload'))throw new RuntimeException('Autoloading remains.');$passes++;
if(!str_contains($source,'require_once'))throw new RuntimeException('Explicit file imports missing.');$passes++;
$sql=file_get_contents($root.'/database/schema/prospak_academy.sql');
foreach(['CREATE TABLE users','CREATE TABLE students','CREATE TABLE courses','CREATE TABLE course_registrations','CREATE TABLE results'] as $tbl){
 if(!str_contains($sql,$tbl))throw new RuntimeException('Core SQL table missing: '.$tbl);$passes++;
}
foreach(['CREATE TABLE announcements','CREATE TABLE notifications','CREATE TABLE transcript_requests'] as $tbl){if(str_contains($sql,$tbl))throw new RuntimeException('Unrelated table: '.$tbl);$passes++;}
echo "PASS: {$passes} structure/architecture checks\n";
