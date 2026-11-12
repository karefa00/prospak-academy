<?php
declare(strict_types=1);
final class EnrollmentController {
    public function index(): void {
        $user=AuthMiddleware::requireLogin();$role=$user['role_slug'];$db=db();
        if($role==='student') {
            $student=(new StudentRepository($db))->byUser((int)$user['id']);
            if(!$student)throw new DomainException('Your student profile is missing.');
            $semester=(new EnrollmentService($db))->activeSemester();
            $eligible=$semester && $student['programme_id'] && $student['academic_level_id'] ? (new EnrollmentService($db))->eligibleCourses($student,$semester):[];
            [$items,$total,$page]=(new EnrollmentRepository($db))->forStudent((int)$student['id'],page_number($_GET['page']??1));
            View::render('courses/enrollments',['title'=>'My course registration','semester'=>$semester,'eligible'=>$eligible,'items'=>$items,'total'=>$total,'page'=>$page,'user'=>$user]);
        }else {
            RoleMiddleware::requireRole('administrator','academic_officer','lecturer');
            $page=page_number($_GET['page']??1);
            $sql=" FROM course_registrations cr JOIN students s ON s.id=cr.student_id JOIN users u ON u.id=s.user_id JOIN courses c ON c.id=cr.course_id JOIN semesters sm ON sm.id=cr.semester_id";
            $where='';$params=[];
            if($role==='lecturer'){$sql.=" JOIN course_assignments a ON a.course_id=cr.course_id AND a.semester_id=cr.semester_id JOIN lecturers l ON l.id=a.lecturer_id";$where=" WHERE l.user_id=? AND a.status='active'";$params[]=$user['id'];}
            $q=$db->prepare('SELECT COUNT(DISTINCT cr.id)'.$sql.$where);$q->execute($params);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
            $q=$db->prepare('SELECT DISTINCT cr.id,u.full_name,s.matriculation_number,c.code,cr.status'.$sql.$where.' ORDER BY cr.id DESC LIMIT ? OFFSET ?');
            $i=1;foreach($params as $v)$q->bindValue($i++,(int)$v,PDO::PARAM_INT);$q->bindValue($i++,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue($i,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();
            View::render('courses/enrollments',['title'=>'Registrations','items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'user'=>$user,'semester'=>null,'eligible'=>[]]);
        }
    }
    public function mutate(string $action): void {
        $user=RoleMiddleware::requireRole('student');$id=Input::id($_POST['course_id']??'');
        (new EnrollmentService(db()))->mutate((int)$user['id'],$id,$action);
        flash($action==='add'?'Course registered.':'Course dropped.','success');redirect('/enrollments');
    }
}
