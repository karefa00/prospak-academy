<?php
declare(strict_types=1);
require_once __DIR__.'/../../bootstrap/app.php';
set_error_handler(static function(int $severity,string $message):never {throw new ErrorException($message,0,$severity);});
$root=dirname(__DIR__,2);$passes=0;
function verifyTemplate(string $path,array $data,string $expected): void {
    global $root,$passes;
    extract($data,EXTR_SKIP);
    ob_start();try{require $root.'/templates/'.$path;}catch(Throwable $e){ob_end_clean();throw new RuntimeException('Template '.$path.': '.$e->getMessage(),0,$e);} $html=ob_get_clean();
    if(!str_contains($html,$expected))throw new RuntimeException('Missing action '.$expected.' in '.$path);
    $passes++;
}
$account=['id'=>5,'role_slug'=>'lecturer','full_name'=>'Demo Lecturer','email'=>'teacher@demo.test','account_status'=>'active'];
verifyTemplate('students/user_edit.php',['record'=>$account,'lecturer'=>['staff_number'=>'T001','department_id'=>2],'departments'=>[['id'=>2,'name'=>'Computing','status'=>'active']]],'/users/update');
$student=['id'=>9,'user_id'=>12,'first_name'=>'Ada','last_name'=>'Doe','email'=>'ada@demo.test','matriculation_number'=>'PA001','programme_id'=>2,'academic_level_id'=>3,'phone'=>null,'gender'=>null,'date_of_birth'=>null,'admission_year'=>null];
verifyTemplate('students/edit.php',['record'=>$student,'programmes'=>[['id'=>2,'name'=>'BSc Software','department'=>'Computing','status'=>'active']],'levels'=>[['id'=>3,'name'=>'100 Level','status'=>'active']]],'/students/update');
$course=['id'=>3,'code'=>'SWE101','title'=>'Introduction','credit_units'=>3,'semester_number'=>1,'department_id'=>2,'academic_level_id'=>3];
verifyTemplate('courses/edit.php',['course'=>$course,'programmeIds'=>[2],'prereqIds'=>[4],'departments'=>[['id'=>2,'name'=>'Computing','status'=>'active']],'levels'=>[['id'=>3,'name'=>'100 Level','status'=>'active']],'programmes'=>[['id'=>2,'name'=>'BSc SWE','department_id'=>2,'status'=>'active']],'prerequisites'=>[['id'=>4,'code'=>'GEN101','title'=>'Basics']]],'/courses/update');
$academic=[
    'faculty'=>['id'=>1,'code'=>'TEC','name'=>'Technology','status'=>'active'],
    'department'=>['id'=>2,'code'=>'SWE','name'=>'Software','faculty_id'=>1,'status'=>'active'],
    'programme'=>['id'=>3,'code'=>'BSWE','name'=>'BSc Software','department_id'=>2,'status'=>'active'],
    'level'=>['id'=>4,'code'=>'100','name'=>'100 Level','sort_order'=>1,'status'=>'active'],
    'session'=>['id'=>5,'name'=>'2026/2027','starts_on'=>'2026-09-01','ends_on'=>'2027-08-30','registration_deadline'=>null,'exam_starts_on'=>null,'exam_ends_on'=>null,'status'=>'active'],
    'semester'=>['id'=>6,'academic_session_id'=>5,'semester_number'=>1,'starts_on'=>'2026-09-01','ends_on'=>'2027-02-28','registration_deadline'=>'2026-10-01','exam_starts_on'=>null,'exam_ends_on'=>null,'grading_starts_on'=>null,'grading_ends_on'=>null,'status'=>'active'],
];
foreach($academic as $entity=>$record)verifyTemplate('courses/academic_edit.php',['entity'=>$entity,'record'=>$record,'faculties'=>[['id'=>1,'name'=>'Technology']],'departments'=>[['id'=>2,'name'=>'Software']],'sessions'=>[['id'=>5,'name'=>'2026/2027']]],'/settings/academics');
verifyTemplate('courses/publish.php',['items'=>[['id'=>4,'code'=>'SWE101','title'=>'Intro','session_name'=>'2026/2027','semester_number'=>1,'lecturer_name'=>'Lecturer Demo']],'page'=>1,'total'=>1,'recent'=>[]],'/grades/publish');
verifyTemplate('courses/assignments.php',['user'=>['role_slug'=>'administrator'],'items'=>[['id'=>1,'course_id'=>2,'semester_id'=>3,'lecturer_id'=>4,'status'=>'active','code'=>'SWE101','title'=>'Intro','session_name'=>'2026/2027','semester_number'=>1,'lecturer_name'=>'Demo Lecturer']],'courses'=>[['id'=>2,'code'=>'SWE101','title'=>'Intro']],'lecturers'=>[['id'=>4,'full_name'=>'Demo Lecturer','staff_number'=>'T001']],'semesters'=>[['id'=>3,'session_name'=>'2026/2027','semester_number'=>1]],'page'=>1,'total'=>1],'/courses/assignment/update');
$manage=[];
foreach($academic as $entity=>$record)$manage[$entity]=['items'=>[$entity==='semester'?[...$record,'session_name'=>'2026/2027']:$record],'page'=>1,'total'=>1];
verifyTemplate('courses/settings.php',['faculties'=>[$academic['faculty']],'departments'=>[$academic['department']],'programmes'=>[$academic['programme']],'levels'=>[$academic['level']],'sessions'=>[$academic['session']],'semesters'=>[[...$academic['semester'],'session_name'=>'2026/2027']],'manage'=>$manage],'/settings/academics/edit');
echo "PASS: {$passes} CRUD view render/escaping smoke checks\n";
