<?php
declare(strict_types=1);
final class AcademicController {
    private const TABLES=['faculty'=>'faculties','department'=>'departments','programme'=>'programmes','level'=>'academic_levels','session'=>'academic_sessions','semester'=>'semesters'];
    public function index(): void {
        RoleMiddleware::requireRole('administrator');$db=db();$manage=[];
        foreach(self::TABLES as $entity=>$table){
            $total=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            $page=page_number($_GET[$entity.'_page']??1);$page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
            $query=$entity==='semester'?'SELECT s.*,a.name AS session_name FROM semesters s JOIN academic_sessions a ON a.id=s.academic_session_id ORDER BY s.id DESC LIMIT ? OFFSET ?':'SELECT * FROM '.$table.' ORDER BY id DESC LIMIT ? OFFSET ?';
            $stmt=$db->prepare($query);$stmt->bindValue(1,PAGE_SIZE,PDO::PARAM_INT);$stmt->bindValue(2,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$stmt->execute();
            $manage[$entity]=['items'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page];
        }
        View::render('courses/settings',['title'=>'Academic configuration','manage'=>$manage,
            'faculties'=>$db->query('SELECT * FROM faculties ORDER BY name')->fetchAll(),
            'departments'=>$db->query('SELECT * FROM departments ORDER BY name')->fetchAll(),
            'programmes'=>$db->query('SELECT * FROM programmes ORDER BY name')->fetchAll(),
            'levels'=>$db->query('SELECT * FROM academic_levels ORDER BY sort_order')->fetchAll(),
            'sessions'=>$db->query('SELECT * FROM academic_sessions ORDER BY id DESC')->fetchAll(),
            'semesters'=>$db->query('SELECT s.*,a.name AS session_name FROM semesters s JOIN academic_sessions a ON a.id=s.academic_session_id ORDER BY s.id DESC')->fetchAll(),
        ]);
    }
    private static function table(string $entity): string {
        return self::TABLES[$entity]??throw new InvalidArgumentException('Unknown academic record type.');
    }
    private static function one(PDO $db,string $table,int $id,bool $lock=false): array {
        $q=$db->prepare('SELECT * FROM '.$table.' WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);
        return $q->fetch() ?: throw new DomainException('Academic record not found.');
    }
    private static function count(PDO $db,string $sql,int $id): int {
        $q=$db->prepare($sql);$q->execute([$id]);return (int)$q->fetchColumn();
    }
    private static function activeParent(PDO $db,string $table,int $id): void {
        $parent=self::one($db,$table,$id,true);
        if($parent['status']!=='active')throw new DomainException('Select an active parent record.');
    }
    public function edit(): void {
        RoleMiddleware::requireRole('administrator');$entity=(string)($_GET['entity']??'');$table=self::table($entity);$id=Input::id($_GET['id']??null);$db=db();
        $record=self::one($db,$table,$id);
        View::render('courses/academic_edit',['title'=>'Edit academic record','entity'=>$entity,'record'=>$record,
            'faculties'=>$db->query('SELECT id,name FROM faculties ORDER BY name')->fetchAll(),
            'departments'=>$db->query('SELECT id,name FROM departments ORDER BY name')->fetchAll(),
            'sessions'=>$db->query('SELECT id,name FROM academic_sessions ORDER BY id DESC')->fetchAll()]);
    }
    private static function checkSemesterPeriod(PDO $db,int $semesterId): void {
        $semester=self::one($db,'semesters',$semesterId,true);
        $today=(new DateTimeImmutable('now',new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
        if($today<=$semester['ends_on'] && self::count($db,"SELECT COUNT(*) FROM course_registrations WHERE semester_id=? AND status='registered'",$semesterId)>0)
            throw new DomainException('The semester is still in progress with registered students.');
        if(self::count($db,"SELECT COUNT(*) FROM result_batches WHERE semester_id=? AND status <> 'published'",$semesterId)>0)
            throw new DomainException('Semester has unfinished result batches.');
    }
    private static function canDeactivate(PDO $db,string $entity,int $id): void {
        $checks=[
            'faculty'=>["SELECT COUNT(*) FROM departments WHERE faculty_id=? AND status='active'"],
            'department'=>["SELECT COUNT(*) FROM programmes WHERE department_id=? AND status='active'","SELECT COUNT(*) FROM courses WHERE department_id=? AND status='active'"],
            'programme'=>["SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE s.programme_id=? AND u.account_status='active'","SELECT COUNT(*) FROM course_programmes cp JOIN courses c ON c.id=cp.course_id WHERE cp.programme_id=? AND c.status='active'"],
            'level'=>["SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE s.academic_level_id=? AND u.account_status='active'","SELECT COUNT(*) FROM courses WHERE academic_level_id=? AND status='active'"],
            'session'=>["SELECT COUNT(*) FROM semesters WHERE academic_session_id=? AND status='active'"],
        ];
        foreach($checks[$entity]??[] as $sql)if(self::count($db,$sql,$id)>0)throw new DomainException('Deactivate or reassign dependent active records first.');
        if($entity==='semester')self::checkSemesterPeriod($db,$id);
    }
    private static function calendarFields(string $entity,array $input,?array $parent=null): array {
        $dates=AcademicValidator::calendar($input,$parent);
        if($entity==='session')unset($dates['grading_starts_on'],$dates['grading_ends_on']);
        return $dates;
    }
    private static function apply(PDO $db,string $entity,?int $id,array $post): void {
        $table=self::table($entity);$fields=[];
        if(in_array($entity,['faculty','department','programme','level'],true)){
            $limit=$entity==='programme'?30:20;
            $fields['code']=Input::code($post['code']??null,$limit);
            $fields['name']=Input::required($post['name']??null,150);
            if($entity==='department'){$fields['faculty_id']=Input::id($post['faculty_id']??null);}
            if($entity==='programme'){$fields['department_id']=Input::id($post['department_id']??null);}
            if($entity==='level')$fields['sort_order']=AcademicValidator::sortOrder($post['sort_order']??null);
        } elseif($entity==='session') {
            $fields=['name'=>AcademicValidator::session($post['name']??null),...self::calendarFields('session',$post)];
        } elseif($entity==='semester') {
            $fields['academic_session_id']=Input::id($post['academic_session_id']??null);
            $fields['semester_number']=CourseValidator::semester($post['semester_number']??null);
            $parent=self::one($db,'academic_sessions',$fields['academic_session_id'],true);
            $fields=[...$fields,...self::calendarFields('semester',$post,$parent)];
        }
        if($id!==null){
            $old=self::one($db,$table,$id,true);
            if($entity==='department' && (int)$old['faculty_id']!==$fields['faculty_id'])self::activeParent($db,'faculties',$fields['faculty_id']);
            if($entity==='programme' && (int)$old['department_id']!==$fields['department_id'])self::activeParent($db,'departments',$fields['department_id']);
            if($entity==='session') {
                $q=$db->prepare('SELECT starts_on,ends_on FROM semesters WHERE academic_session_id=?');$q->execute([$id]);
                foreach($q->fetchAll() as $child)if($child['starts_on']<$fields['starts_on']||$child['ends_on']>$fields['ends_on'])throw new DomainException('Existing semesters must remain within session dates.');
            }
            if($entity==='semester') {
                if((int)$old['academic_session_id']!==$fields['academic_session_id'] && self::count($db,'SELECT COUNT(*) FROM course_registrations WHERE semester_id=?',$id)>0)throw new DomainException('Cannot move a semester with registration history.');
                if((int)$old['semester_number']!==$fields['semester_number'] && self::count($db,'SELECT COUNT(*) FROM course_assignments WHERE semester_id=?',$id)>0)throw new DomainException('Cannot renumber a semester with lecturer assignments.');
            }
            if($entity==='programme' && (int)$old['department_id']!==$fields['department_id']) {
                if(self::count($db,'SELECT COUNT(*) FROM students WHERE programme_id=?',$id)>0||self::count($db,'SELECT COUNT(*) FROM course_programmes WHERE programme_id=?',$id)>0)throw new DomainException('Programme cannot move while students or courses are linked.');
            }
            if($entity==='department' && (int)$old['faculty_id']!==$fields['faculty_id'] && self::count($db,'SELECT COUNT(*) FROM programmes WHERE department_id=?',$id)>0)throw new DomainException('Department with programmes cannot move faculty.');
            $sets=implode(', ',array_map(static fn($k)=>$k.'=?',array_keys($fields)));
            $q=$db->prepare("UPDATE {$table} SET {$sets} WHERE id=?");$q->execute([...array_values($fields),$id]);
        } else {
            if($entity==='department')self::activeParent($db,'faculties',$fields['faculty_id']);
            if($entity==='programme')self::activeParent($db,'departments',$fields['department_id']);
            $cols=implode(',',array_keys($fields));$placeholders=implode(',',array_fill(0,count($fields),'?'));
            $q=$db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})");$q->execute(array_values($fields));
        }
    }
    private static function activate(PDO $db,string $entity,int $id): void {
        if($entity==='session') {
            $active=$db->query("SELECT id FROM semesters WHERE status='active' FOR UPDATE")->fetchColumn();
            if($active)self::checkSemesterPeriod($db,(int)$active);
            $db->exec("UPDATE semesters SET status='inactive' WHERE status='active'");
            $db->exec("UPDATE academic_sessions SET status='inactive' WHERE status='active'");
        }elseif($entity==='semester'){
            $semester=self::one($db,'semesters',$id,true);
            self::activeParent($db,'academic_sessions',(int)$semester['academic_session_id']);
            $active=$db->query("SELECT id FROM semesters WHERE status='active' FOR UPDATE")->fetchColumn();
            if($active && (int)$active!==$id)self::checkSemesterPeriod($db,(int)$active);
            $db->exec("UPDATE semesters SET status='inactive' WHERE status='active'");
        }elseif($entity==='department'){
            $row=self::one($db,'departments',$id,true);self::activeParent($db,'faculties',(int)$row['faculty_id']);
        }elseif($entity==='programme'){
            $row=self::one($db,'programmes',$id,true);self::activeParent($db,'departments',(int)$row['department_id']);
        }
    }
    public function save(): void {
        RoleMiddleware::requireRole('administrator');$action=(string)($_POST['action']??'');$db=db();
        $legacy=['activate_session'=>['session','active'],'activate_semester'=>['semester','active']];
        $db->beginTransaction();
        try {
            if(isset($legacy[$action])){[$entity,$status]=$legacy[$action];$action='status';}
            elseif($action==='status'){$entity=(string)($_POST['entity']??'');$status=AcademicValidator::status($_POST['status']??null);}
            elseif($action==='update')$entity=(string)($_POST['entity']??'');
            else $entity=$action;
            $table=self::table($entity);
            if($action==='status'){
                $id=Input::id($_POST['id']??null);$old=self::one($db,$table,$id,true);
                if($old['status']!==$status){
                    if($status==='inactive')self::canDeactivate($db,$entity,$id);else self::activate($db,$entity,$id);
                    $q=$db->prepare("UPDATE {$table} SET status=? WHERE id=?");$q->execute([$status,$id]);
                }
            } else self::apply($db,$entity,$action==='update'?Input::id($_POST['id']??null):null,$_POST);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('A conflicting code, name or related record prevents this change.',0,$e);throw $e;}
        flash('Academic record saved.','success');redirect('/settings/academics');
    }
}
