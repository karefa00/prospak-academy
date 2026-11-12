<?php
declare(strict_types=1);
require_once __DIR__.'/../../bootstrap/app.php';
$passes=0;
function check(bool $v,string $message): void {global $passes;if(!$v)throw new RuntimeException('FAIL: '.$message);$passes++;}
function fails(callable $fn): bool {try{$fn();return false;}catch(InvalidArgumentException|DomainException $e){return true;}}
check(Input::email(' Demo@Example.TEST ') === 'demo@example.test','email normalization');
check(Input::code('swe101')==='SWE101','course code normalization');
check(CourseValidator::credit('3')===3,'credit units');
check(CourseValidator::semester('2')===2,'semester selection');
check(Input::score('49.50',60)==='49.50','decimal score');
check(fails(fn()=>Input::score('60.01',60)),'score boundary rejection');
check(fails(fn()=>Input::score('5.000',10)),'score precision rejection');
check(fails(fn()=>Input::required("ok\0bad")),'NUL rejection');
check(fails(fn()=>Input::email('invalid@@example')),'email rejection');
check(fails(fn()=>CourseValidator::credit('100')),'credit upper bound');
check(page_number('-4')===1,'invalid page correction');
check(page_number('4')===4,'valid page');
check(PAGE_SIZE===10,'page size');
check(wat_time('2026-11-11 12:00:00')==='11 Nov 2026, 1:00 PM WAT','UTC to WAT');
check(password_verify('safepassword12',password_hash('safepassword12',PASSWORD_BCRYPT)),'bcrypt password hash');
$fake=(new ReflectionClass(GradeService::class))->newInstanceWithoutConstructor();
$grades=[
 ['course_id'=>1,'registration_id'=>10,'period_key'=>'2026-01-01','grade_point'=>'4.00','credit_units_snapshot'=>3],
 ['course_id'=>2,'registration_id'=>11,'period_key'=>'2026-01-01','grade_point'=>'2.00','credit_units_snapshot'=>2]
];
check($fake->cgpa($grades,null)['value']==='3.20','weighted GPA');
$repeat=[...$grades,['course_id'=>1,'registration_id'=>12,'period_key'=>'2027-01-01','grade_point'=>'5.00','credit_units_snapshot'=>3]];
check($fake->cgpa($repeat,null)['needs_policy']===true,'repeat requires policy');
check($fake->cgpa($repeat,'latest_published')['value']==='3.80','latest attempt policy');
check($fake->cgpa($repeat,'highest_published')['value']==='3.80','highest attempt policy');
check($fake->cgpa($repeat,'all_attempts')['value']==='3.88','all attempts policy');
echo "PASS: {$passes} unit checks\n";
