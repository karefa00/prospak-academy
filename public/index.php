<?php
declare(strict_types=1);
// PHP built-in dev server passes existing static files through directly; no router.php needed.
if(PHP_SAPI==='cli-server') {
    $path=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
    $real=realpath(__DIR__.$path);$root=realpath(__DIR__);
    if($real && $root && str_starts_with($real,$root.DIRECTORY_SEPARATOR) && is_file($real) && strtolower(pathinfo($real,PATHINFO_EXTENSION))!=='php' && !str_starts_with(basename($real),'.')) return false;
}
require_once __DIR__ . '/../bootstrap/app.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; script-src 'self'; style-src 'self'; font-src 'self'");
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$path=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
if($method==='POST')CsrfMiddleware::verify();
if($path==='/')redirect(AuthMiddleware::user()?'/dashboard':'/login');
$auth=new AuthController();$students=new StudentController();$users=new UserController();$courses=new CourseController();$enroll=new EnrollmentController();$grades=new GradeController();$academic=new AcademicController();
$routes=[
    'GET /login'=>static fn()=> $auth->login(),
    'POST /login'=>static function(){require_once __DIR__.'/../process/login.php';},
    'POST /logout'=>static function(){require_once __DIR__.'/../process/logout.php';},
    'GET /change-password'=>static fn()=> $auth->changePage(),
    'POST /change-password'=>static fn()=> $auth->change(),
    'GET /dashboard'=>static fn()=>(new DashboardController())->index(),
    'GET /profile'=>static fn()=> $students->profile(),
    'GET /users'=>static fn()=> $users->index(),
    'GET /users/new'=>static fn()=> $users->new(),
    'POST /users/create'=>static function(){require_once __DIR__.'/../process/register.php';},
    'GET /users/edit'=>static fn()=> $users->edit(),
    'POST /users/update'=>static fn()=> $users->update(),
    'POST /users/status'=>static fn()=> $users->status(),
    'GET /students'=>static fn()=> $students->index(),
    'GET /students/new'=>static fn()=> $students->new(),
    'POST /students/create'=>static fn()=> $students->create(),
    'GET /students/edit'=>static fn()=> $students->edit(),
    'POST /students/update'=>static fn()=> $students->update(),
    'POST /students/status'=>static fn()=> $students->status(),
    'GET /students/photo'=>static fn()=> $students->image(),
    'GET /courses'=>static fn()=> $courses->index(),
    'GET /courses/new'=>static fn()=> $courses->new(),
    'POST /courses/create'=>static fn()=> $courses->create(),
    'GET /courses/edit'=>static fn()=> $courses->edit(),
    'POST /courses/update'=>static fn()=> $courses->update(),
    'POST /courses/status'=>static fn()=> $courses->toggle(),
    'GET /courses/assignments'=>static fn()=> $courses->assignments(),
    'POST /courses/assign'=>static fn()=> $courses->assign(),
    'POST /courses/assignment/update'=>static fn()=> $courses->assignmentUpdate(),
    'POST /courses/assignment/status'=>static fn()=> $courses->assignmentStatus(),
    'GET /settings/academics'=>static fn()=> $academic->index(),
    'GET /settings/academics/edit'=>static fn()=> $academic->edit(),
    'POST /settings/academics'=>static fn()=> $academic->save(),
    'GET /enrollments'=>static fn()=> $enroll->index(),
    'POST /enrollments/add'=>static function(){$operation='add';require __DIR__.'/../process/enrollment.php';},
    'POST /enrollments/drop'=>static function(){$operation='drop';require __DIR__.'/../process/enrollment.php';},
    'GET /grades'=>static fn()=> $grades->index(),
    'GET /grades/entry'=>static fn()=> $grades->entry(),
    'POST /grades/save'=>static fn()=> $grades->save(),
    'POST /grades/submit'=>static fn()=> $grades->submit(),
    'POST /grades/review'=>static fn()=> $grades->review(),
    'POST /grades/publish'=>static fn()=> $grades->publish(),
    'GET /grades/setup'=>static fn()=> $grades->setup(),
    'POST /grades/setup'=>static fn()=> $grades->saveSetup(),
    'GET /results/publish'=>static fn()=> $grades->publication(),
    'GET /results'=>static fn()=> $grades->results(),
    'GET /api/auth/me'=>static function(){require_once __DIR__.'/../api/routes/auth.php';},
    'GET /api/students'=>static function(){require_once __DIR__.'/../api/routes/students.php';},
    'GET /api/courses'=>static function(){require_once __DIR__.'/../api/routes/courses.php';},
    'GET /api/enrollments'=>static function(){require_once __DIR__.'/../api/routes/enrollments.php';},
];
$key=$method.' '.$path;
if(!isset($routes[$key])){http_response_code(404);View::render('errors/not_found',['title'=>'Page not found'],'main');exit;}
try { $routes[$key](); }
catch (InvalidArgumentException|DomainException $e) {
    if($method==='GET'){ http_response_code(400);View::render('errors/bad_request',['title'=>'Unable to display page','message'=>$e->getMessage()],'main');exit; }
    flash($e->getMessage(),'danger');
    $fallback=[
        '/login'=>'/login','/change-password'=>'/change-password',
        '/users/create'=>'/users/new','/users/update'=>'/users','/users/status'=>'/users',
        '/students/create'=>'/students/new','/students/update'=>'/students','/students/status'=>'/students',
        '/courses/create'=>'/courses/new','/courses/update'=>'/courses','/courses/status'=>'/courses',
        '/courses/assign'=>'/courses/assignments','/courses/assignment/update'=>'/courses/assignments','/courses/assignment/status'=>'/courses/assignments','/settings/academics'=>'/settings/academics',
        '/enrollments/add'=>'/enrollments','/enrollments/drop'=>'/enrollments',
        '/grades/save'=>'/grades','/grades/submit'=>'/grades','/grades/review'=>'/grades',
        '/grades/publish'=>'/results/publish','/grades/setup'=>'/grades/setup',
    ];
    redirect($fallback[$path]??'/dashboard');
}
