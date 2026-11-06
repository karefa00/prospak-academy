<?php
declare(strict_types=1);

// DB-independent Phase 3 validation, escaping, form and permission-visibility checks.
// NOT evidence of live MySQL integration or authenticated browser behavior.
putenv('APP_URL=http://127.0.0.1:8098');
putenv('APP_KEY='.str_repeat('ef',32));
require dirname(__DIR__).'/bootstrap/app.php';

use Prospak\Core\AcademicValidation as AV;
use Prospak\Core\View;
use Prospak\Core\Config;

$_SESSION=[];
$_SERVER['REQUEST_URI']='/academics/faculties';
$count=0;
function check3(bool $result,string $label):void {
    global $count;
    $count++;
    if (!$result) { fwrite(STDERR,"FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}

$faculty=['code'=>' sci ','name'=>' Faculty of Science ','status'=>'active'];
[$data,$errors]=AV::validate('faculties',$faculty);
check3($errors===[],'valid faculty accepted');
check3($data['code']==='SCI' && $data['name']==='Faculty of Science','faculty normalized');
check3(AV::id('12')===12,'numeric id');
check3(AV::id(0)===null && AV::id('-1')===null,'invalid ids rejected');
check3(AV::id('4294967296')===null,'reject value beyond INT UNSIGNED');
check3(AV::id(['1'])===null,'nested id rejected');
check3(AV::page('0')===1 && AV::page('999999999')===1000000,'page bounded');
check3(AV::search(['bad'])==='','search array rejected');
check3(strlen(AV::search(str_repeat('a',200)))===120,'search is bounded');
check3(preg_match('//u',AV::search(str_repeat('é',80)))===1,'search does not split UTF-8');
$bad=$faculty; $bad['code']='**'; check3(isset(AV::validate('faculties',$bad)[1]['code']),'invalid code rejected');
$bad=$faculty; $bad['name']=''; check3(isset(AV::validate('faculties',$bad)[1]['name']),'empty name rejected');
$bad=$faculty; $bad['name']="Test\nRecord"; check3(isset(AV::validate('faculties',$bad)[1]['name']),'control chars rejected');
$bad=$faculty; $bad['status']='deleted'; check3(isset(AV::validate('faculties',$bad)[1]['status']),'invalid status rejected');
$dept=$faculty+['parent_id'=>'2'];
check3(AV::validate('departments',$dept)[1]===[],'valid department accepted');
$bad=$dept; $bad['parent_id']='other'; check3(isset(AV::validate('departments',$bad)[1]['parent_id']),'invalid parent rejected');
$bad=$dept; $bad['parent_id']=['1']; check3(isset(AV::validate('departments',$bad)[1]['parent_id']),'parent array rejected');
$prog=['code'=>'BSC-CS','name'=>'Computer Science','status'=>'active','parent_id'=>'8'];
check3(AV::validate('programmes',$prog)[1]===[],'valid programme accepted');
$lev=['code'=>'100','name'=>'100 Level','status'=>'active','sort_order'=>'100'];
check3(AV::validate('levels',$lev)[1]===[],'valid level accepted');
$bad=$lev; $bad['sort_order']='0'; check3(isset(AV::validate('levels',$bad)[1]['sort_order']),'zero sort rejected');
$bad=$lev; $bad['sort_order']='10000'; check3(isset(AV::validate('levels',$bad)[1]['sort_order']),'too large sort rejected');
$errors=[];
check3(AV::optionalId('', $errors, 'programme_id')===null && $errors===[],'unassigned academic placement accepted');
check3(AV::optionalId('5', $errors,'programme_id')===5,'valid placement accepted');
check3(AV::optionalId('bad', $errors,'programme_id')===null && isset($errors['programme_id']),'bad placement rejected');
check3(View::escape('<script>')==='&lt;script&gt;','HTML escaped');

$user=['role_slug'=>'administrator','full_name'=>'Admin','id'=>1,'must_change_password'=>0];
ob_start();
View::render('academics/form',['title'=>'Create','user'=>$user,'entity'=>'departments','item'=>null,
    'values'=>['code'=>'<script>alert(1)</script>','name'=>'Bad <img src=x onerror=1>','status'=>'active'],
    'options'=>[['id'=>1,'code'=>'SCI','name'=>'Faculty','status'=>'active']],
    'errors'=>[]], 'portal');
$html=ob_get_clean();
check3(str_contains($html,'&lt;script&gt;') && !str_contains($html,'value="<script>'),'academic form escapes unsafe values');
check3(str_contains($html,'name="_csrf"'),'academic form has csrf');
check3(str_contains($html,'name="parent_id"'),'department form has parent selection');
check3(str_contains($html,'name="status"'),'academic form supports inactive status');

ob_start();
View::render('academics/list',['title'=>'Faculties','user'=>['role_slug'=>'academic_officer','full_name'=>'Officer','id'=>2,'must_change_password'=>0],
    'entity'=>'faculties','listing'=>['rows'=>[['id'=>1,'code'=>'SCI','name'=>'<img src=x onerror=alert(1)>','status'=>'active','child_count'=>0,'student_count'=>0]],'total'=>1,'page'=>1,'pages'=>1],
    'statusFilter'=>'','search'=>'','canManage'=>false,'flash'=>null], 'portal');
$html=ob_get_clean();
check3(str_contains($html,'&lt;img src=x onerror=alert(1)&gt;'),'list escapes injected academic name');
check3(!str_contains($html,'>Edit</a>'),'academic officer view does not expose editing links');
check3(str_contains($html,'/academics/students?'),'list links to associated students');

$person=['id'=>3,'role_slug'=>'student','full_name'=>'Ada & Co','email'=>'ada@example.org'];
ob_start();
View::render('academics/placement',['title'=>'Placement','user'=>$user,'person'=>$person,'values'=>['programme_id'=>'', 'academic_level_id'=>''],
    'programmes'=>[['id'=>4,'code'=>'BSC-CS','name'=>'Computer Science','status'=>'active']],
    'levels'=>[['id'=>5,'code'=>'100','name'=>'100 Level','status'=>'active']],
    'departments'=>[],'error'=>null], 'portal');
$html=ob_get_clean();
check3(str_contains($html,'name="programme_id"')&&str_contains($html,'name="academic_level_id"'),'student placement selectors');
check3(!str_contains($html,'name="department_id"'),'student placement not directly assigned to department');
check3(str_contains($html,'Ada &amp; Co'),'placement escapes personal name');
check3(str_contains($html,'name="_csrf"'),'placement has csrf');

ob_start();
View::render('academics/students',['title'=>'Students','user'=>$user,'entity'=>'programmes','item'=>['id'=>4,'name'=>'Computer <b>Science</b>','code'=>'BSC-CS'],
    'search'=>'','listing'=>['rows'=>[['user_id'=>5,'full_name'=>'<script>alert(1)</script>',
    'account_status'=>'active','matriculation_number'=>'PA/1','programme_name'=>'Computer Science','department_name'=>'Science','level_name'=>'100 Level']], 'total'=>1,'page'=>1,'pages'=>1],
    'canManage'=>true], 'portal');
$html=ob_get_clean();
check3(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'),'associated list escapes student name');
check3(!str_contains($html,'Computer <b>Science</b>'),'academic scope title encoded');

// The real DB checks are in phase3_db_readonly.php and manual guide.
echo "SUCCESS: $count non-database Phase 3 checks passed.\n";
