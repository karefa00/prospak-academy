<?php
declare(strict_types=1);
final class DashboardController {
    public function index(): void {
        $user=AuthMiddleware::requireLogin();$role=$user['role_slug'];$db=db();$cards=[];$rows=[];$student=null;$cgpa=null;
        if(in_array($role,['administrator','academic_officer'],true)) {
            $cards=[
                ['label'=>'Students','value'=>(int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn(),'icon'=>'users','tone'=>'blue'],
                ['label'=>'Lecturers','value'=>(int)$db->query('SELECT COUNT(*) FROM lecturers')->fetchColumn(),'icon'=>'briefcase','tone'=>'purple'],
                ['label'=>'Courses','value'=>(int)$db->query("SELECT COUNT(*) FROM courses WHERE status='active'")->fetchColumn(),'icon'=>'book','tone'=>'orange'],
                ['label'=>'Result batches','value'=>(int)$db->query('SELECT COUNT(*) FROM result_batches')->fetchColumn(),'icon'=>'award','tone'=>'green'],
            ];
            $rows=$db->query("SELECT b.id,c.code,c.title,b.status FROM result_batches b JOIN courses c ON c.id=b.course_id ORDER BY b.id DESC LIMIT 5")->fetchAll();
        }elseif($role==='lecturer') {
            $q=$db->prepare("SELECT COUNT(*) FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id WHERE l.user_id=? AND ca.status='active'");$q->execute([$user['id']]);$count=(int)$q->fetchColumn();
            $cards=[['label'=>'Assigned courses','value'=>$count,'icon'=>'book','tone'=>'purple']];
            $rows=(new GradeService($db))->offerings((int)$user['id']);
        }elseif($role==='student') {
            $student=(new StudentRepository($db))->byUser((int)$user['id']);
            if($student){$q=$db->prepare("SELECT COUNT(*) FROM course_registrations WHERE student_id=? AND status='registered'");$q->execute([$student['id']]);$count=(int)$q->fetchColumn();
                $results=(new GradeService($db))->studentResults((int)$student['id']);$cgpa=(new GradeService($db))->cgpa($results,(new GradeService($db))->policy());
                $cards=[['label'=>'Registered courses','value'=>$count,'icon'=>'book','tone'=>'blue'],['label'=>'Published grades','value'=>count($results),'icon'=>'award','tone'=>'green'],['label'=>'CGPA','value'=>$cgpa['value']??'—','icon'=>'chart','tone'=>'purple']];
                $rows=array_slice($results,0,5);
            }
        }
        View::render('dashboard/index',['title'=>'Dashboard','user'=>$user,'cards'=>$cards,'rows'=>$rows,'student'=>$student,'cgpa'=>$cgpa]);
    }
}
