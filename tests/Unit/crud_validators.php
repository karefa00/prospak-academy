<?php
declare(strict_types=1);
require_once __DIR__.'/../../bootstrap/app.php';
$checks=0;
function assertCrud(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function shouldReject(callable $call,string $description): void {try {$call();}catch(InvalidArgumentException|DomainException $e){assertCrud(true,$description);return;}throw new RuntimeException('Expected rejection: '.$description);}
assertCrud(AcademicValidator::date('2026-09-01')==='2026-09-01','valid date');
assertCrud(AcademicValidator::date('',true)===null,'optional date');
shouldReject(fn()=>AcademicValidator::date('2026-02-30'),'invalid calendar day');
shouldReject(fn()=>AcademicValidator::date("2026-01-01\0"),'null byte date');
shouldReject(fn()=>AcademicValidator::date(['2026-01-01']),'array rejected');
assertCrud(AcademicValidator::session('2026/2027')==='2026/2027','valid session');
shouldReject(fn()=>AcademicValidator::session('2026/2028'),'session nonconsecutive');
shouldReject(fn()=>AcademicValidator::session('2026-2027'),'session format');
assertCrud(AcademicValidator::status('active')==='active','active status');
assertCrud(AcademicValidator::status('inactive')==='inactive','inactive status');
shouldReject(fn()=>AcademicValidator::status('published'),'unsupported academic status');
shouldReject(fn()=>AcademicValidator::status(['active']),'status input type');
assertCrud(AcademicValidator::sortOrder('200')===200,'academic sort order');
shouldReject(fn()=>AcademicValidator::sortOrder('65536'),'sort overflow');
$good=AcademicValidator::calendar(['starts_on'=>'2026-09-01','ends_on'=>'2027-05-30','registration_deadline'=>'2026-10-10','exam_starts_on'=>'2027-04-01','exam_ends_on'=>'2027-04-30','grading_starts_on'=>'2027-05-01','grading_ends_on'=>'2027-05-25']);
assertCrud($good['registration_deadline']==='2026-10-10','valid semester calendar');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-10-10','ends_on'=>'2026-10-10']),'zero-length period');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-09-01','ends_on'=>'2027-05-30','registration_deadline'=>'2027-06-01']),'deadline outside period');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-09-01','ends_on'=>'2027-05-30','exam_starts_on'=>'2027-03-01']),'unpaired exam');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-09-01','ends_on'=>'2027-05-30','exam_starts_on'=>'2027-05-29','exam_ends_on'=>'2027-06-01']),'exam date outside period');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-09-01','ends_on'=>'2027-05-30','grading_starts_on'=>'2027-06-01','grading_ends_on'=>'2027-05-31']),'inverted grading period');
shouldReject(fn()=>AcademicValidator::calendar(['starts_on'=>'2026-08-01','ends_on'=>'2027-05-30'],['starts_on'=>'2026-09-01','ends_on'=>'2027-08-30']),'semester outside session');
echo "PASS: {$checks} CRUD/calendar validation checks\n";
