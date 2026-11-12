<?php
declare(strict_types=1);
final class CourseController {
    public function index(): void {
        AuthMiddleware::requireLogin();$search=trim((string)($_GET['q']??''));$page=page_number($_GET['page']??1);
        [$items,$total,$page]=(new CourseRepository(db()))->paginate($page,$search);
        View::render('courses/index',['title'=>'Course catalogue','items'=>$items,'total'=>$total,'page'=>$page,'search'=>$search]);
    }
    public function new(): void {
        RoleMiddleware::requireRole('administrator');$db=db();
        View::render('courses/form',['title'=>'Add course',
            'departments'=>$db->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll(),
            'levels'=>$db->query("SELECT id,code,name FROM academic_levels WHERE status='active' ORDER BY sort_order")->fetchAll(),
            'programmes'=>$db->query("SELECT p.id,p.name,p.department_id FROM programmes p WHERE p.status='active' ORDER BY p.name")->fetchAll(),
            'prerequisites'=>(new CourseRepository($db))->allActive()]);
    }
    public function create(): void {
        RoleMiddleware::requireRole('administrator');
        $code=Input::code($_POST['code']??'',20);$title=Input::required($_POST['title']??'',200);$credit=CourseValidator::credit($_POST['credit_units']??'');
        $department=Input::id($_POST['department_id']??'');$level=Input::id($_POST['academic_level_id']??'');
        $semester=CourseValidator::semester($_POST['semester_number']??'');$programme=Input::id($_POST['programme_id']??'');
        $prereq=($_POST['prerequisite_id']??'')!==''?Input::id($_POST['prerequisite_id']):null;
        $db=db();$q=$db->prepare("SELECT COUNT(*) FROM programmes WHERE id=? AND department_id=? AND status='active'");$q->execute([$programme,$department]);
        if(!(int)$q->fetchColumn())throw new DomainException('Programme must belong to the selected department.');
        $db->beginTransaction();
        try{
            $q=$db->prepare('INSERT INTO courses(code,title,credit_units,department_id,academic_level_id,semester_number) VALUES(?,?,?,?,?,?)');$q->execute([$code,$title,$credit,$department,$level,$semester]);$id=(int)$db->lastInsertId();
            $q=$db->prepare('INSERT INTO course_programmes(course_id,programme_id) VALUES(?,?)');$q->execute([$id,$programme]);
            if($prereq){$q=$db->prepare('SELECT id FROM courses WHERE id=?');$q->execute([$prereq]);if(!$q->fetch())throw new DomainException('Prerequisite course not found.');$q=$db->prepare('INSERT INTO course_prerequisites(course_id,prerequisite_course_id) VALUES(?,?)');$q->execute([$id,$prereq]);}
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        flash('Course created.','success');redirect('/courses');
    }
    public function edit(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_GET['id']??null);$db=db();$course=(new CourseRepository($db))->byId($id);
        if(!$course)throw new DomainException('Course not found.');
        $q=$db->prepare('SELECT programme_id FROM course_programmes WHERE course_id=?');$q->execute([$id]);$programmeIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $q=$db->prepare('SELECT prerequisite_course_id FROM course_prerequisites WHERE course_id=?');$q->execute([$id]);$prereqIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        View::render('courses/edit',['title'=>'Edit course','course'=>$course,'programmeIds'=>$programmeIds,'prereqIds'=>$prereqIds,
            'departments'=>$db->query("SELECT id,name,status FROM departments ORDER BY name")->fetchAll(),
            'levels'=>$db->query('SELECT id,name,status FROM academic_levels ORDER BY sort_order')->fetchAll(),
            'programmes'=>$db->query('SELECT id,name,department_id,status FROM programmes ORDER BY name')->fetchAll(),
            'prerequisites'=>(new CourseRepository($db))->allActive()]);
    }
    private static function ids(mixed $input,string $label): array {
        if(!is_array($input))throw new InvalidArgumentException('Select '.$label.' from the list.');
        if(count($input)>100)throw new InvalidArgumentException('Too many '.$label.' selected.');
        $ids=[];foreach($input as $value)$ids[]=Input::id($value);
        return array_values(array_unique($ids));
    }
    private static function checkPrerequisiteCycles(PDO $db,int $courseId,array $prerequisites): void {
        if(in_array($courseId,$prerequisites,true))throw new DomainException('A course cannot require itself.');
        $q=$db->query('SELECT course_id,prerequisite_course_id FROM course_prerequisites');$graph=[];
        foreach($q->fetchAll() as $r)$graph[(int)$r['course_id']][]=(int)$r['prerequisite_course_id'];
        $graph[$courseId]=$prerequisites;
        foreach($prerequisites as $pre){$stack=[$pre];$seen=[];
            while($stack){$cur=array_pop($stack);if($cur===$courseId)throw new DomainException('Circular prerequisite detected.');if(isset($seen[$cur]))continue;$seen[$cur]=true;foreach($graph[$cur]??[] as $next)$stack[]=$next;}
        }
    }
    public function update(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??null);
        $code=Input::code($_POST['code']??null,20);$title=Input::required($_POST['title']??null,200);
        $credit=CourseValidator::credit($_POST['credit_units']??null);$department=Input::id($_POST['department_id']??null);
        $level=Input::id($_POST['academic_level_id']??null);$semester=CourseValidator::semester($_POST['semester_number']??null);
        $programmes=self::ids($_POST['programme_ids']??[],'programmes');$prerequisites=self::ids($_POST['prerequisite_ids']??[],'prerequisites');
        if(!$programmes)throw new InvalidArgumentException('At least one programme is required.');
        $db=db();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM courses WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new DomainException('Course not found.');
            $q=$db->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND status='active'");$q->execute([$department]);if(!$q->fetchColumn())throw new DomainException('Choose an active department.');
            $q=$db->prepare("SELECT COUNT(*) FROM academic_levels WHERE id=? AND status='active'");$q->execute([$level]);if(!$q->fetchColumn())throw new DomainException('Choose an active level.');
            foreach($programmes as $pid){$q=$db->prepare("SELECT COUNT(*) FROM programmes WHERE id=? AND department_id=? AND status='active'");$q->execute([$pid,$department]);if(!$q->fetchColumn())throw new DomainException('All programmes must belong to the selected department and be active.');}
            foreach($prerequisites as $pre){$q=$db->prepare("SELECT COUNT(*) FROM courses WHERE id=? AND status='active'");$q->execute([$pre]);if(!$q->fetchColumn())throw new DomainException('Select existing active prerequisites.');}
            self::checkPrerequisiteCycles($db,$id,$prerequisites);
            $q=$db->prepare('SELECT programme_id FROM course_programmes WHERE course_id=? ORDER BY programme_id');$q->execute([$id]);$oldProgrammes=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));sort($oldProgrammes);$newProgrammes=$programmes;sort($newProgrammes);
            $q=$db->prepare('SELECT prerequisite_course_id FROM course_prerequisites WHERE course_id=? ORDER BY prerequisite_course_id');$q->execute([$id]);$oldPrereqs=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));sort($oldPrereqs);$newPrereqs=$prerequisites;sort($newPrereqs);
            $critical=(int)$old['credit_units']!==$credit||(int)$old['department_id']!==$department||(int)$old['academic_level_id']!==$level||(int)$old['semester_number']!==$semester||$oldProgrammes!==$newProgrammes||$oldPrereqs!==$newPrereqs;
            if($critical){
                $q=$db->prepare('SELECT COUNT(*) FROM course_registrations WHERE course_id=?');$q->execute([$id]);if($q->fetchColumn())throw new DomainException('Course academic structure cannot change after registrations exist. Only code and title may be edited.');
                $q=$db->prepare('SELECT COUNT(*) FROM course_assignments WHERE course_id=?');$q->execute([$id]);if($q->fetchColumn())throw new DomainException('Unassign lecturers before changing course academic structure.');
            }
            $q=$db->prepare('UPDATE courses SET code=?,title=?,credit_units=?,department_id=?,academic_level_id=?,semester_number=? WHERE id=?');$q->execute([$code,$title,$credit,$department,$level,$semester,$id]);
            if($oldProgrammes!==$newProgrammes){$q=$db->prepare('DELETE FROM course_programmes WHERE course_id=?');$q->execute([$id]);$q=$db->prepare('INSERT INTO course_programmes(course_id,programme_id) VALUES (?,?)');foreach($programmes as $pid)$q->execute([$id,$pid]);}
            if($oldPrereqs!==$newPrereqs){$q=$db->prepare('DELETE FROM course_prerequisites WHERE course_id=?');$q->execute([$id]);$q=$db->prepare('INSERT INTO course_prerequisites(course_id,prerequisite_course_id) VALUES (?,?)');foreach($prerequisites as $pre)$q->execute([$id,$pre]);}
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('Course code or relationships conflict with an existing record.',0,$e);throw $e;}
        flash('Course updated.','success');redirect('/courses');
    }
    private static function assignmentLocked(PDO $db,int $course,int $semester,bool $allowPublishedDeactivation=false): bool {
        $q=$db->prepare('SELECT status FROM result_batches WHERE course_id=? AND semester_id=?');$q->execute([$course,$semester]);$batch=$q->fetchColumn();
        if($batch!==false)return !($allowPublishedDeactivation && $batch==='published');
        $q=$db->prepare('SELECT COUNT(*) FROM student_scores ss JOIN course_registrations cr ON cr.id=ss.registration_id WHERE cr.course_id=? AND cr.semester_id=?');$q->execute([$course,$semester]);
        return (int)$q->fetchColumn()>0;
    }
    public function assignmentUpdate(): void {
        RoleMiddleware::requireRole('administrator','academic_officer');$id=Input::id($_POST['id']??null);$lecturer=Input::id($_POST['lecturer_id']??null);
        $db=db();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM course_assignments WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new DomainException('Assignment not found.');
            if((int)$old['lecturer_id']!==$lecturer && self::assignmentLocked($db,(int)$old['course_id'],(int)$old['semester_id']))throw new DomainException('Cannot reassign after score entry or result processing.');
            $q=$db->prepare("SELECT COUNT(*) FROM lecturers l JOIN users u ON u.id=l.user_id WHERE l.id=? AND u.account_status='active'");$q->execute([$lecturer]);if(!$q->fetchColumn())throw new DomainException('Choose an active lecturer.');
            $q=$db->prepare('UPDATE course_assignments SET lecturer_id=? WHERE id=?');$q->execute([$lecturer,$id]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('This lecturer already has the assignment.',0,$e);throw $e;}
        flash('Lecturer reassigned.','success');redirect('/courses/assignments');
    }
    public function assignmentStatus(): void {
        RoleMiddleware::requireRole('administrator','academic_officer');$id=Input::id($_POST['id']??null);$status=$_POST['status']??null;
        if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid assignment status.');
        $db=db();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM course_assignments WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new DomainException('Assignment not found.');
            if($status==='inactive' && self::assignmentLocked($db,(int)$old['course_id'],(int)$old['semester_id'],true))throw new DomainException('Result evidence exists; lecturer assignment cannot be removed.');
            if($status==='active'){
                $q=$db->prepare("SELECT COUNT(*) FROM lecturers l JOIN users u ON u.id=l.user_id WHERE l.id=? AND u.account_status='active'");$q->execute([$old['lecturer_id']]);if(!$q->fetchColumn())throw new DomainException('Lecturer is suspended or missing.');
            }
            $q=$db->prepare('UPDATE course_assignments SET status=? WHERE id=?');$q->execute([$status,$id]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        flash('Assignment status updated.','success');redirect('/courses/assignments');
    }
    public function toggle(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??'');$status=$_POST['status']??null;
        if(!in_array($status,Course::STATUSES,true))throw new InvalidArgumentException('Invalid status.');
        if($status==='inactive'){
            $q=db()->prepare("SELECT COUNT(*) FROM course_registrations cr JOIN semesters s ON s.id=cr.semester_id WHERE cr.course_id=? AND cr.status='registered' AND s.status='active'");$q->execute([$id]);if($q->fetchColumn())throw new DomainException('Cannot deactivate while the active semester has registered students.');
            $q=db()->prepare("SELECT COUNT(*) FROM result_batches WHERE course_id=? AND status <> 'published'");$q->execute([$id]);if($q->fetchColumn())throw new DomainException('Finish processing pending result batches first.');
            $q=db()->prepare("SELECT COUNT(*) FROM course_prerequisites cp JOIN courses c ON c.id=cp.course_id WHERE cp.prerequisite_course_id=? AND c.status='active'");$q->execute([$id]);if($q->fetchColumn())throw new DomainException('Active courses still require this prerequisite.');
        }
        $q=db()->prepare('UPDATE courses SET status=? WHERE id=?');$q->execute([$status,$id]);if(!$q->rowCount())throw new DomainException('Course not found or already has the selected status.');flash('Course status updated.','success');redirect('/courses');
    }
    public function assignments(): void {
        $user=RoleMiddleware::requireRole('administrator','academic_officer','lecturer');$db=db();
        $page=page_number($_GET['page']??1);
        if($user['role_slug']==='lecturer') {
            $q=$db->prepare('SELECT COUNT(*) FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id WHERE l.user_id=?');$q->execute([$user['id']]);$total=(int)$q->fetchColumn();
            $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
            $q=$db->prepare("SELECT ca.id,ca.course_id,ca.semester_id,ca.lecturer_id,ca.status,c.code,c.title,ss.name AS session_name,s.semester_number,u.full_name AS lecturer_name FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id JOIN users u ON u.id=l.user_id JOIN courses c ON c.id=ca.course_id JOIN semesters s ON s.id=ca.semester_id JOIN academic_sessions ss ON ss.id=s.academic_session_id WHERE l.user_id=? ORDER BY ca.id DESC LIMIT ? OFFSET ?");
            $q->bindValue(1,(int)$user['id'],PDO::PARAM_INT);$q->bindValue(2,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(3,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();$list=$q->fetchAll();
        } else {
            $total=(int)$db->query('SELECT COUNT(*) FROM course_assignments')->fetchColumn();
            $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
            $q=$db->prepare("SELECT ca.id,ca.course_id,ca.semester_id,ca.lecturer_id,ca.status,c.code,c.title,ss.name AS session_name,s.semester_number,u.full_name AS lecturer_name FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id JOIN users u ON u.id=l.user_id JOIN courses c ON c.id=ca.course_id JOIN semesters s ON s.id=ca.semester_id JOIN academic_sessions ss ON ss.id=s.academic_session_id ORDER BY ca.id DESC LIMIT ? OFFSET ?");
            $q->bindValue(1,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(2,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();$list=$q->fetchAll();
        }
        $courses=(new CourseRepository($db))->allActive();
        $semesters=$db->query('SELECT s.id,s.semester_number,a.name AS session_name FROM semesters s JOIN academic_sessions a ON a.id=s.academic_session_id ORDER BY s.id DESC LIMIT 50')->fetchAll();
        $lecturers=$db->query("SELECT l.id,l.staff_number,u.full_name FROM lecturers l JOIN users u ON u.id=l.user_id WHERE u.account_status='active' ORDER BY u.full_name")->fetchAll();
        View::render('courses/assignments',['title'=>'Lecturer assignments','items'=>$list,'courses'=>$courses,'semesters'=>$semesters,'lecturers'=>$lecturers,'user'=>$user,'total'=>$total,'page'=>$page]);
    }
    public function assign(): void {
        RoleMiddleware::requireRole('administrator','academic_officer');$courseId=Input::id($_POST['course_id']??'');$semester=Input::id($_POST['semester_id']??'');$lecturer=Input::id($_POST['lecturer_id']??'');
        $db=db();$course=(new CourseRepository($db))->byId($courseId);$q=$db->prepare('SELECT semester_number FROM semesters WHERE id=?');$q->execute([$semester]);$number=$q->fetchColumn();
        if(!$course||$course['status']!=='active'||(int)$course['semester_number']!==(int)$number)throw new DomainException('Course and semester are not compatible.');
        $q=$db->prepare("SELECT COUNT(*) FROM lecturers l JOIN users u ON u.id=l.user_id WHERE l.id=? AND u.account_status='active'");$q->execute([$lecturer]);if(!$q->fetchColumn())throw new DomainException('Lecturer is unavailable.');
        $q=$db->prepare("INSERT INTO course_assignments(course_id,semester_id,lecturer_id,status) VALUES(?,?,?,'active') ON DUPLICATE KEY UPDATE status='active'");$q->execute([$courseId,$semester,$lecturer]);
        flash('Lecturer assigned.','success');redirect('/courses/assignments');
    }
}
