<?php
declare(strict_types=1);
final class GradeController {
    public function index(): void {
        $user=RoleMiddleware::requireRole('administrator','academic_officer','lecturer');$svc=new GradeService(db());
        if($user['role_slug']==='lecturer'){
            View::render('courses/grades',['title'=>'My course grades','user'=>$user,'offerings'=>$svc->offerings((int)$user['id']),'batches'=>[],'total'=>0,'page'=>1]);
        }else {
            [$batches,$total,$page]=$svc->batches(page_number($_GET['page']??1));
            View::render('courses/grades',['title'=>'Grade approvals','user'=>$user,'offerings'=>$svc->offerings(),'batches'=>$batches,'total'=>$total,'page'=>$page]);
        }
    }
    public function entry(): void {
        $user=RoleMiddleware::requireRole('lecturer');$course=Input::id($_GET['course_id']??'');$semester=Input::id($_GET['semester_id']??'');$svc=new GradeService(db());
        if(!$svc->lecturerAssigned((int)$user['id'],$course,$semester))throw new DomainException('Course is not assigned to you.');
        $components=$svc->components($course,$semester);[$rows,$total,$page]=$svc->roster($course,$semester,page_number($_GET['page']??1));
        $q=db()->prepare('SELECT status FROM result_batches WHERE course_id=? AND semester_id=?');$q->execute([$course,$semester]);$status=$q->fetchColumn();
        View::render('courses/grade_entry',['title'=>'Score entry','user'=>$user,'course'=>$course,'semester'=>$semester,'components'=>$components,'items'=>$rows,'total'=>$total,'page'=>$page,'status'=>$status]);
    }
    public function save(): void {
        $u=RoleMiddleware::requireRole('lecturer');$course=Input::id($_POST['course_id']??'');$semester=Input::id($_POST['semester_id']??'');$reg=Input::id($_POST['registration_id']??'');
        (new GradeService(db()))->saveScore((int)$u['id'],$course,$semester,$reg,$_POST['ca_score']??null,$_POST['exam_score']??null);
        flash('Draft score saved.','success');redirect('/grades/entry?course_id='.$course.'&semester_id='.$semester);
    }
    public function submit(): void {
        $u=RoleMiddleware::requireRole('lecturer');$course=Input::id($_POST['course_id']??'');$semester=Input::id($_POST['semester_id']??'');
        (new GradeService(db()))->submit((int)$u['id'],$course,$semester);
        flash('Complete roster submitted for academic review.','success');redirect('/grades');
    }
    public function review(): void {
        $u=RoleMiddleware::requireRole('academic_officer');$id=Input::id($_POST['batch_id']??'');$decision=(string)($_POST['decision']??'');$reason=trim((string)($_POST['reason']??''));
        (new GradeService(db()))->review((int)$u['id'],$id,$decision,$reason);
        flash('Review decision recorded.','success');redirect('/grades');
    }
    public function publication(): void {
        RoleMiddleware::requireRole('administrator');$db=db();$page=page_number($_GET['page']??1);
        $total=(int)$db->query("SELECT COUNT(*) FROM result_batches WHERE status='approved'")->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $q=$db->prepare("SELECT b.id,c.code,c.title,s.semester_number,a.name AS session_name,u.full_name AS lecturer_name,b.submitted_at FROM result_batches b JOIN courses c ON c.id=b.course_id JOIN semesters s ON s.id=b.semester_id JOIN academic_sessions a ON a.id=s.academic_session_id JOIN users u ON u.id=b.submitted_by WHERE b.status='approved' ORDER BY b.id ASC LIMIT ? OFFSET ?");
        $q->bindValue(1,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(2,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();
        $recent=$db->query("SELECT c.code,c.title,a.name AS session_name,p.published_at FROM result_publications p JOIN result_batches b ON b.id=p.batch_id JOIN courses c ON c.id=b.course_id JOIN semesters s ON s.id=b.semester_id JOIN academic_sessions a ON a.id=s.academic_session_id ORDER BY p.id DESC LIMIT 5")->fetchAll();
        View::render('courses/publish',['title'=>'Publish approved results','items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'recent'=>$recent]);
    }
    public function publish(): void {
        $u=RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['batch_id']??'');
        (new GradeService(db()))->publish((int)$u['id'],$id);
        flash('Approved grades published to students.','success');redirect('/grades');
    }
    public function setup(): void {
        RoleMiddleware::requireRole('administrator');$db=db();$svc=new GradeService($db);
        $schemes=$db->query('SELECT id,name,status FROM grading_schemes ORDER BY id DESC')->fetchAll();
        $courses=(new CourseRepository($db))->allActive();
        $semesters=$db->query('SELECT s.id,s.semester_number,a.name AS session_name FROM semesters s JOIN academic_sessions a ON a.id=s.academic_session_id ORDER BY s.id DESC')->fetchAll();
        $policy=$db->query('SELECT policy_key,policy_value FROM academic_policies')->fetchAll(PDO::FETCH_KEY_PAIR);
        View::render('courses/grade_setup',['title'=>'Grade configuration','schemes'=>$schemes,'courses'=>$courses,'semesters'=>$semesters,'policy'=>$policy]);
    }
    public function saveSetup(): void {
        $u=RoleMiddleware::requireRole('administrator');$action=(string)($_POST['action']??'');$svc=new GradeService(db());
        if($action==='scheme')$svc->setupScheme((int)$u['id'],Input::id($_POST['scale']??''));
        elseif($action==='components')$svc->setupComponents(Input::id($_POST['course_id']??''),Input::id($_POST['semester_id']??''),$_POST['ca_max']??null,$_POST['exam_max']??null);
        elseif($action==='policy'){
            $policy=($_POST['repeat_policy']??'')===''?null:(string)$_POST['repeat_policy'];
            $minimum=($_POST['pass_threshold']??'')===''?null:(string)$_POST['pass_threshold'];
            $svc->setPolicy($policy,$minimum,(int)$u['id']);
        }else throw new InvalidArgumentException('Unknown grading setup action.');
        flash('Grade configuration updated.','success');redirect('/grades/setup');
    }
    public function results(): void {
        $user=RoleMiddleware::requireRole('student');$student=(new StudentRepository(db()))->byUser((int)$user['id']);
        if(!$student)throw new DomainException('Student record missing.');
        $svc=new GradeService(db());$rows=$svc->studentResults((int)$student['id']);$cgpa=$svc->cgpa($rows,$svc->policy());
        View::render('courses/results',['title'=>'My published grades','user'=>$user,'student'=>$student,'items'=>$rows,'cgpa'=>$cgpa]);
    }
}
