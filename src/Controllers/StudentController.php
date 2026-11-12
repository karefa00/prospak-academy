<?php
declare(strict_types=1);
final class StudentController {
    public function index(): void {
        RoleMiddleware::requireRole('administrator','academic_officer');
        $search=trim((string)($_GET['q']??''));$page=page_number($_GET['page']??1);
        [$items,$total,$page]=(new StudentRepository(db()))->paginate($page,$search);
        View::render('students/index',['title'=>'Students','items'=>$items,'total'=>$total,'page'=>$page,'search'=>$search]);
    }
    public function new(): void {
        RoleMiddleware::requireRole('administrator');
        $programmes=db()->query("SELECT p.id,p.name,d.name AS department FROM programmes p JOIN departments d ON d.id=p.department_id WHERE p.status='active' AND d.status='active' ORDER BY d.name,p.name")->fetchAll();
        $levels=db()->query("SELECT id,code,name FROM academic_levels WHERE status='active' ORDER BY sort_order")->fetchAll();
        View::render('students/form',['title'=>'Register student','programmes'=>$programmes,'levels'=>$levels]);
    }
    public function create(): void {
        RoleMiddleware::requireRole('administrator');
        $first=Input::required($_POST['first_name']??'',75);$last=Input::required($_POST['last_name']??'',75);
        $email=Input::email($_POST['email']??'');$matric=Input::code($_POST['matriculation_number']??'',40);
        $programme=Input::id($_POST['programme_id']??'');$level=Input::id($_POST['academic_level_id']??'');
        $password=Input::required($_POST['temporary_password']??'',72);
        if(strlen($password)<10)throw new InvalidArgumentException('Temporary password must be at least 10 characters.');
        $q=db()->prepare("SELECT COUNT(*) FROM programmes WHERE id=? AND status='active'");$q->execute([$programme]);if(!$q->fetchColumn())throw new DomainException('Choose an active programme.');
        $q=db()->prepare("SELECT COUNT(*) FROM academic_levels WHERE id=? AND status='active'");$q->execute([$level]);if(!$q->fetchColumn())throw new DomainException('Choose an active level.');
        $image=null;$target=null;
        if(isset($_FILES['passport']) && (int)($_FILES['passport']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            $f=$_FILES['passport'];
            if(!is_array($f)||!isset($f['tmp_name'])||is_array($f['tmp_name'])||(int)$f['error']!==UPLOAD_ERR_OK||(int)$f['size']>2*1024*1024||(int)$f['size']<=0||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Upload a JPEG, PNG or WebP photo (maximum 2 MB).');
            $type=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if(!isset(Student::IMAGE_MIMES[$type]))throw new InvalidArgumentException('Unsupported passport image type.');
            $image=bin2hex(random_bytes(20)).'.'.Student::IMAGE_MIMES[$type];
            $target=PROJECT_ROOT.'/storage/private-uploads/'.$image;
            if(!move_uploaded_file($f['tmp_name'],$target))throw new RuntimeException('Could not save uploaded photo.');
            chmod($target,0600);
        }
        $db=db();
        try{
            $db->beginTransaction();
            $uid=(new UserRepository($db))->create('student',$first.' '.$last,$email,$password);
            $q=$db->prepare('INSERT INTO students(user_id,matriculation_number,first_name,last_name,programme_id,academic_level_id,passport_filename) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$uid,$matric,$first,$last,$programme,$level,$image]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($target && is_file($target))unlink($target);throw $e;}
        forget_old();flash('Student registered. Temporary password change required at first login.','success');redirect('/students');
    }
    public function edit(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_GET['id']??null);$record=(new StudentRepository(db()))->byId($id);
        if(!$record)throw new DomainException('Student not found.');
        View::render('students/edit',['title'=>'Edit student','record'=>$record,
            'programmes'=>db()->query("SELECT p.id,p.name,p.status,d.name AS department FROM programmes p JOIN departments d ON d.id=p.department_id ORDER BY d.name,p.name")->fetchAll(),
            'levels'=>db()->query('SELECT id,code,name,status FROM academic_levels ORDER BY sort_order')->fetchAll()]);
    }
    public function update(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??null);
        $first=Input::required($_POST['first_name']??null,75);$last=Input::required($_POST['last_name']??null,75);
        $email=Input::email($_POST['email']??null);$matric=Input::code($_POST['matriculation_number']??null,40);
        $programme=Input::id($_POST['programme_id']??null);$level=Input::id($_POST['academic_level_id']??null);
        $phone=trim((string)($_POST['phone']??''));
        if($phone!=='' && (!preg_match('/^[+0-9 ()-]{7,30}$/D',$phone)))throw new InvalidArgumentException('Enter a valid phone number.');
        $gender=(string)($_POST['gender']??'');
        if(!in_array($gender,['','female','male','other','prefer_not_to_say'],true))throw new InvalidArgumentException('Invalid gender.');
        $dob=AcademicValidator::date($_POST['date_of_birth']??'',true);
        $year=trim((string)($_POST['admission_year']??''));
        if($year!=='' && (!preg_match('/^\d{4}$/D',$year) || (int)$year<1901 || (int)$year>2155))throw new InvalidArgumentException('Invalid admission year.');
        $db=db();$newPhoto=null;$newPath=null;$oldPhoto=null;
        if(isset($_FILES['passport']) && (int)($_FILES['passport']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $f=$_FILES['passport'];
            if(!is_array($f)||!isset($f['tmp_name'])||is_array($f['tmp_name'])||(int)$f['error']!==UPLOAD_ERR_OK||(int)$f['size']>2097152||(int)$f['size']<=0||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Upload JPG, PNG or WebP (maximum 2 MB).');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if(!isset(Student::IMAGE_MIMES[$mime]))throw new InvalidArgumentException('Unsupported passport image type.');
            $newPhoto=bin2hex(random_bytes(20)).'.'.Student::IMAGE_MIMES[$mime];$newPath=PROJECT_ROOT.'/storage/private-uploads/'.$newPhoto;
            if(!move_uploaded_file($f['tmp_name'],$newPath))throw new RuntimeException('Unable to store passport photo.');
            chmod($newPath,0600);
        }
        try{
            $db->beginTransaction();
            $q=$db->prepare('SELECT * FROM students WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
            if(!$old)throw new DomainException('Student not found.');$oldPhoto=$old['passport_filename'];
            $q=$db->prepare("SELECT COUNT(*) FROM programmes WHERE id=? AND status='active'");$q->execute([$programme]);
            if(!$q->fetchColumn())throw new DomainException('Choose an active programme.');
            $q=$db->prepare("SELECT COUNT(*) FROM academic_levels WHERE id=? AND status='active'");$q->execute([$level]);
            if(!$q->fetchColumn())throw new DomainException('Choose an active academic level.');
            if((int)$old['programme_id']!==$programme||(int)$old['academic_level_id']!==$level){
                $q=$db->prepare("SELECT COUNT(*) FROM course_registrations cr JOIN semesters se ON se.id=cr.semester_id WHERE cr.student_id=? AND cr.status='registered' AND se.status='active'");$q->execute([$id]);
                if($q->fetchColumn())throw new DomainException('Drop or complete current-semester course registrations before changing programme or level.');
            }
            $q=$db->prepare('UPDATE users SET full_name=?,email=? WHERE id=?');$q->execute([$first.' '.$last,$email,$old['user_id']]);
            $q=$db->prepare('UPDATE students SET first_name=?,last_name=?,matriculation_number=?,programme_id=?,academic_level_id=?,phone=?,gender=?,date_of_birth=?,admission_year=?,passport_filename=? WHERE id=?');
            $q->execute([$first,$last,$matric,$programme,$level,$phone?:null,$gender?:null,$dob,$year?:null,$newPhoto??$oldPhoto,$id]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($newPath && is_file($newPath))unlink($newPath);if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('Email or matriculation number is already in use.',0,$e);throw $e;}
        if($newPhoto && $oldPhoto && preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',(string)$oldPhoto)){
            $oldPath=PROJECT_ROOT.'/storage/private-uploads/'.$oldPhoto;if(is_file($oldPath))unlink($oldPath);
        }
        flash('Student profile updated.','success');redirect('/students');
    }
    public function status(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??null);$status=$_POST['status']??null;
        if(!in_array($status,['active','suspended'],true))throw new InvalidArgumentException('Invalid account status.');
        $db=db();$q=$db->prepare('UPDATE users u JOIN students s ON s.user_id=u.id SET u.account_status=?,u.session_version=u.session_version+1 WHERE s.id=?');
        $q->execute([$status,$id]);if($q->rowCount()===0)throw new DomainException('Student not found.');
        flash($status==='active'?'Student reactivated.':'Student account deactivated; academic history preserved.','success');redirect('/students');
    }
    public function profile(): void {
        $user=AuthMiddleware::requireLogin();$profile=null;
        if($user['role_slug']==='student')$profile=(new StudentRepository(db()))->byUser((int)$user['id']);
        View::render('students/profile',['title'=>'My profile','user'=>$user,'profile'=>$profile]);
    }
    public function image(): void {
        $viewer=AuthMiddleware::requireLogin();$id=Input::id($_GET['id']??'');$student=(new StudentRepository(db()))->byId($id);
        if(!$student || (!$student['passport_filename'])){http_response_code(404);exit('Image not found');}
        if(!in_array($viewer['role_slug'],['administrator','academic_officer'],true) && (int)$student['user_id']!==(int)$viewer['id']){http_response_code(403);exit('Not authorized');}
        $name=(string)$student['passport_filename'];
        if(!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',$name)){http_response_code(404);exit;}
        $file=PROJECT_ROOT.'/storage/private-uploads/'.$name;
        if(!is_file($file)){http_response_code(404);exit;}
        $type=(new finfo(FILEINFO_MIME_TYPE))->file($file);
        if(!isset(Student::IMAGE_MIMES[$type])){http_response_code(404);exit;}
        header('Content-Type: '.$type);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);exit;
    }
}
