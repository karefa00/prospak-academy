<?php
declare(strict_types=1);

// Dependency-free Phase 2 validation and template unit checks. NOT MySQL integration evidence.
putenv('APP_URL=http://127.0.0.1:8098');
putenv('APP_KEY=' . str_repeat('ef', 32));
require dirname(__DIR__) . '/bootstrap/app.php';
ob_start();

use Prospak\Core\PeopleValidation as P;
use Prospak\Core\View;

$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) { fwrite(STDERR, "FAIL $label\n"); exit(1); }
    echo "PASS $label\n";
}

$base = [
    'role'=>'student', 'first_name'=>'Ada', 'last_name'=>'Ibe', 'email'=>'  ADA@EXAMPLE.ORG ',
    'matriculation_number'=>'Pa/2026/01', 'phone'=>'+234 801 111 2222',
    'gender'=>'female', 'date_of_birth'=>'2003-02-24', 'admission_year'=>'2026',
];
[$data, $errors] = P::validate($base);
verify($errors===[], 'valid student accepted');
verify($data['email']==='ada@example.org', 'email normalized');
verify($data['matriculation_number']==='PA/2026/01', 'matric normalized');
verify($data['full_name']==='Ada Ibe', 'full name derived');
verify($data['date_of_birth']==='2003-02-24', 'date kept');
verify($data['phone']==='+234 801 111 2222', 'phone retained');
verify($data['admission_year']==='2026', 'admission year retained');
$invalid = $base; $invalid['email'] = ['bad'];
verify(isset(P::validate($invalid)[1]['email']), 'nested email rejected');
$invalid = $base; $invalid['role'] = 'superadmin';
verify(isset(P::validate($invalid)[1]['role']), 'unexpected role rejected');
$invalid = $base; $invalid['matriculation_number'] = 'bad [abc]';
verify(isset(P::validate($invalid)[1]['matriculation_number']), 'invalid matric rejected');
$invalid = $base; $invalid['matriculation_number'] = '';
verify(isset(P::validate($invalid)[1]['matriculation_number']), 'missing matric rejected');
$invalid = $base; $invalid['first_name'] = "Ada\nIbe";
verify(isset(P::validate($invalid)[1]['first_name']), 'control chars in name rejected');
$invalid = $base; $invalid['last_name'] = str_repeat('A', 76);
verify(isset(P::validate($invalid)[1]['last_name']), 'long last name rejected');
$invalid = $base; $invalid['gender'] = 'invalid';
verify(isset(P::validate($invalid)[1]['gender']), 'bad gender rejected');
$invalid = $base; $invalid['date_of_birth'] = '2024-02-30';
verify(isset(P::validate($invalid)[1]['date_of_birth']), 'invalid calendar date rejected');
$invalid = $base; $invalid['date_of_birth'] = '2100-01-01';
verify(isset(P::validate($invalid)[1]['date_of_birth']), 'future date rejected');
$invalid = $base; $invalid['admission_year'] = '9999';
verify(isset(P::validate($invalid)[1]['admission_year']), 'out-of-range MySQL YEAR rejected');
$invalid = $base; $invalid['phone'] = ['123'];
verify(isset(P::validate($invalid)[1]['phone']), 'array phone rejected');
$invalid = $base; $invalid['phone'] = 'abc';
verify(isset(P::validate($invalid)[1]['phone']), 'malformed phone rejected');
verify(!isset(P::validate($base,true,'student')[1]['role']), 'same student role editable');
$invalid = $base; $invalid['role'] = 'lecturer';
verify(isset(P::validate($invalid,true,'student')[1]['role']), 'student to lecturer role conversion denied');
$invalid = $base; $invalid['role'] = 'academic_officer';
verify(isset(P::validate($invalid,true,'student')[1]['role']), 'student to officer role conversion denied');
$staff = ['role'=>'lecturer','first_name'=>'Sade','last_name'=>'Okon','email'=>'sade@example.org','staff_number'=>'L001','designation'=>'Tutor'];
verify(P::validate($staff)[1]===[], 'valid lecturer accepted');
$invalid = $staff; $invalid['staff_number'] = '$bad';
verify(isset(P::validate($invalid)[1]['staff_number']), 'malformed staff ID rejected');
$invalid = $staff; $invalid['designation'] = str_repeat('x', 121);
verify(isset(P::validate($invalid)[1]['designation']), 'overlong designation rejected');
$admin = ['role'=>'administrator','full_name'=>'System Admin', 'email'=>'admin@example.org'];
verify(P::validate($admin)[1]===[], 'valid administrator accepted');
$officer = $admin; $officer['role'] = 'academic_officer';
verify(P::validate($officer,true,'administrator')[1]===[], 'administrator to officer validated (service enforces last-admin rule)');
verify(P::positiveId('42')===42, 'positive ID accepted');
verify(P::positiveId('-1')===null && P::positiveId('1.2')===null, 'invalid IDs rejected');
verify(P::positiveId(['1'])===null && P::positiveId('0')===null, 'array/zero IDs rejected');
verify(P::page('abc')===1 && P::page('123')===123, 'page validation');
verify(View::escape('<img src=x onerror=alert(1)>') !== '<img src=x onerror=alert(1)>', 'XSS output escapes');

$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/admin/users/new';
ob_start();
View::render('people/form', ['title'=>'Create account','user'=>['role_slug'=>'administrator','full_name'=>'Tester','id'=>2],
    'values'=>['role'=>'student','first_name'=>'<img onerror=1>','email'=>'test@example.org'],'errors'=>[], 'isEdit'=>false], 'portal');
$html = ob_get_clean();
verify(str_contains($html,'&lt;img onerror=1&gt;') && !str_contains($html,'value="<img onerror=1>"'), 'form values escaped');
verify(str_contains($html, 'name="matriculation_number"') && str_contains($html, 'name="staff_number"'), 'role-specific form fields present');
verify(str_contains($html, '/assets/js/people-form.js'), 'form uses local vanilla JS');
verify(str_contains($html,'name="_csrf"'), 'form includes CSRF token');

ob_start();
View::render('people/list', ['title'=>'Manage', 'user'=>['role_slug'=>'administrator','full_name'=>'Tester','id'=>2],
    'listing'=>['rows'=>[['id'=>2,'full_name'=>'<script>x</script>','email'=>'evil@example.org','account_status'=>'active','role_slug'=>'student','matriculation_number'=>'PA/1','staff_number'=>null]],'total'=>1,'pages'=>1,'page'=>1],
    'roleFilter'=>'','statusFilter'=>'','search'=>'','flash'=>null], 'portal');
$html = ob_get_clean();
verify(str_contains($html,'&lt;script&gt;x&lt;/script&gt;'), 'list escapes student names');
verify(!str_contains($html, '<script>x</script>'), 'list never emits executable user HTML');

$samplePerson = [
  'id'=>7, 'full_name'=>'Ada <Ibe>', 'email'=>'ada@example.org', 'role_slug'=>'student',
  'role_label'=>'Student', 'account_status'=>'active', 'must_change_password'=>1,
  'created_at'=>'2026-11-04 13:00:00', 'student_first_name'=>'Ada', 'student_last_name'=>'Ibe',
  'matriculation_number'=>'PA/2026/1', 'passport_filename'=>null, 'student_phone'=>null,
  'gender'=>null, 'date_of_birth'=>null, 'admission_year'=>null,
];
ob_start();
View::render('people/detail', ['title'=>'Profile', 'user'=>['role_slug'=>'student','full_name'=>'Ada Ibe','id'=>7],
    'person'=>$samplePerson, 'canManage'=>false, 'credential'=>null], 'portal');
$html=ob_get_clean();
verify(str_contains($html,'Ada &lt;Ibe&gt;') && !str_contains($html,'Ada <Ibe>'), 'private self-profile escapes name');
verify(!str_contains($html,'Issue new temporary password'), 'student self-profile has no admin action');
verify(str_contains($html,'PA/2026/1'), 'student self-profile includes own ID');
verify(str_contains($html,'2:00 PM WAT'), 'profile displays timestamp in Africa/Lagos');

verify(str_contains(file_get_contents(dirname(__DIR__).'/public/assets/css/app.css'), '[data-for-role][hidden]'), 'Bootstrap role sections hidden when necessary');
echo "SUCCESS: $checks non-database Phase 2 checks passed.\n";
ob_end_flush();
