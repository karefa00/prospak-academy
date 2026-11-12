<?php
declare(strict_types=1);
final class UserController {
    public function index(): void {
        RoleMiddleware::requireRole('administrator');$search=trim((string)($_GET['q']??''));$page=page_number($_GET['page']??1);
        [$items,$total,$page]=(new UserRepository(db()))->paginate($page,$search);
        View::render('students/users',['title'=>'User accounts','items'=>$items,'total'=>$total,'page'=>$page,'search'=>$search]);
    }
    public function new(): void {
        RoleMiddleware::requireRole('administrator');$departments=db()->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
        View::render('students/user_form',['title'=>'Register staff','departments'=>$departments]);
    }
    public function create(): void {
        RoleMiddleware::requireRole('administrator');
        $role=Input::required($_POST['role']??'',30);
        if(!in_array($role,['academic_officer','lecturer'],true))throw new InvalidArgumentException('Select a valid staff role.');
        $name=Input::required($_POST['full_name']??'',150);$email=Input::email($_POST['email']??'');$pwd=Input::required($_POST['temporary_password']??'',72);
        if(strlen($pwd)<10)throw new InvalidArgumentException('Temporary password must have at least 10 characters.');
        $first='';$last='';$staffNumber='';$dept=0;
        if($role==='lecturer') {
            $staffNumber=Input::code($_POST['staff_number']??'',40);
            $dept=Input::id($_POST['department_id']??null);
            $parts=preg_split('/\s+/',trim($name),2);$first=$parts[0];$last=$parts[1]??$first;
            $q=db()->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND status='active'");$q->execute([$dept]);if(!$q->fetchColumn())throw new DomainException('Department not found.');
        }
        $db=db();$db->beginTransaction();
        try{
            $id=(new UserRepository($db))->create($role,$name,$email,$pwd);
            if($role==='lecturer'){$q=$db->prepare('INSERT INTO lecturers(user_id,staff_number,first_name,last_name,department_id) VALUES(?,?,?,?,?)');$q->execute([$id,$staffNumber,$first,$last,$dept]);}
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        forget_old();flash('Staff account created. The temporary password must be changed at first login.','success');redirect('/users');
    }
    public function edit(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_GET['id']??null);$db=db();
        $record=(new UserRepository($db))->byId($id);
        if(!$record)throw new DomainException('Account not found.');
        if($record['role_slug']==='student')throw new DomainException('Edit student accounts from the Students page.');
        $lecturer=null;
        if($record['role_slug']==='lecturer'){$q=$db->prepare('SELECT * FROM lecturers WHERE user_id=?');$q->execute([$id]);$lecturer=$q->fetch();}
        View::render('students/user_edit',['title'=>'Edit user account','record'=>$record,'lecturer'=>$lecturer,
            'departments'=>$db->query("SELECT id,name,status FROM departments ORDER BY name")->fetchAll()]);
    }
    public function update(): void {
        RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??null);
        $name=Input::required($_POST['full_name']??null,150);$email=Input::email($_POST['email']??null);
        $db=db();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE');$q->execute([$id]);$record=$q->fetch();
            if(!$record)throw new DomainException('Account not found.');
            if($record['role_slug']==='student')throw new DomainException('Edit student accounts from the Students page.');
            $q=$db->prepare('UPDATE users SET full_name=?,email=? WHERE id=?');$q->execute([$name,$email,$id]);
            if($record['role_slug']==='lecturer') {
                $number=Input::code($_POST['staff_number']??null,40);$dept=Input::id($_POST['department_id']??null);
                $q=$db->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND status='active'");$q->execute([$dept]);
                if(!$q->fetchColumn())throw new DomainException('Select an active department.');
                $parts=preg_split('/\s+/',trim($name),2);$first=$parts[0];$last=$parts[1]??$first;
                $q=$db->prepare('UPDATE lecturers SET first_name=?,last_name=?,staff_number=?,department_id=? WHERE user_id=?');$q->execute([$first,$last,$number,$dept,$id]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000')throw new DomainException('Email or staff number is already in use.',0,$e);throw $e;}
        flash('Account updated.','success');redirect('/users');
    }
    public function status(): void {
        $actor=RoleMiddleware::requireRole('administrator');$id=Input::id($_POST['id']??null);$status=$_POST['status']??null;
        if(!in_array($status,['active','suspended'],true))throw new InvalidArgumentException('Invalid account status.');
        if((int)$actor['id']===$id && $status==='suspended')throw new DomainException('You cannot suspend your own administrator account.');
        $db=db();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT u.account_status,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE');$q->execute([$id]);$record=$q->fetch();
            if(!$record)throw new DomainException('Account not found.');
            if($record['role_slug']==='student')throw new DomainException('Manage student status from the Students page.');
            if($status==='suspended' && $record['role_slug']==='administrator') {
                $q=$db->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrator' AND u.account_status='active'");
                if((int)$q->fetchColumn()<2)throw new DomainException('At least one active administrator must remain.');
            }
            if($status==='suspended' && $record['role_slug']==='lecturer') {
                $q=$db->prepare("SELECT COUNT(*) FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id WHERE l.user_id=? AND ca.status='active'");$q->execute([$id]);
                if($q->fetchColumn())throw new DomainException('Reassign or deactivate this lecturer’s active course assignments first.');
            }
            $q=$db->prepare('UPDATE users SET account_status=?,session_version=session_version+1 WHERE id=?');$q->execute([$status,$id]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        flash('Account status updated.','success');redirect('/users');
    }
}
