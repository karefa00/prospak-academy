<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$router=file_get_contents($root.'/public/index.php');$checks=0;
foreach(['GET /users/edit','POST /users/update','POST /users/status','GET /students/edit','POST /students/update','POST /students/status','GET /settings/academics/edit','POST /settings/academics','GET /courses/edit','POST /courses/update','POST /courses/assignment/update','POST /courses/assignment/status','GET /results/publish','POST /grades/publish'] as $route){
    if(!str_contains($router,"'".$route."'"))throw new RuntimeException('Missing route: '.$route);
    $checks++;
}
if(!str_contains($router,"if(\$method==='POST')CsrfMiddleware::verify()"))throw new RuntimeException('Global CSRF validation missing.');$checks++;
foreach(['UserController.php'=>['function edit()','function update()','function status()','RoleMiddleware::requireRole'], 'StudentController.php'=>['function edit()','function update()','function status()','passport','account_status'], 'CourseController.php'=>['function edit()','function update()','function assignmentUpdate()','function assignmentStatus()','Circular prerequisite detected'], 'AcademicController.php'=>['function edit()','function save()','canDeactivate','checkSemesterPeriod'], 'GradeController.php'=>['function publication()','RoleMiddleware::requireRole(\'administrator\')']] as $controller=>$parts){
    $file=file_get_contents($root.'/src/Controllers/'.$controller);
    foreach($parts as $part){if(!str_contains($file,$part))throw new RuntimeException($controller.' missing '.$part);$checks++;}
}
foreach(['templates/students/edit.php'=>'/students/update','templates/students/user_edit.php'=>'/users/update','templates/courses/edit.php'=>'/courses/update','templates/courses/academic_edit.php'=>'/settings/academics','templates/courses/publish.php'=>'/grades/publish','includes/sidebar.php'=>'/results/publish'] as $path=>$keyword){
    if(!str_contains(file_get_contents($root.'/'.$path),$keyword))throw new RuntimeException('Missing linked form or button: '.$path);$checks++;
}
foreach(['templates/students/index.php','templates/students/users.php','templates/courses/index.php','templates/courses/assignments.php','templates/courses/settings.php','templates/courses/publish.php'] as $path){
    if(!str_contains(file_get_contents($root.'/'.$path),'csrf_field()'))throw new RuntimeException('CSRF input missing: '.$path);$checks++;
}
echo "PASS: {$checks} CRUD routes, forms and security wiring checks\n";
