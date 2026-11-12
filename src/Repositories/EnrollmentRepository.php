<?php
declare(strict_types=1);
final class EnrollmentRepository {
    public function __construct(private PDO $db) {}
    public function forStudent(int $studentId,int $page): array {
        $q=$this->db->prepare('SELECT COUNT(*) FROM course_registrations WHERE student_id=?');$q->execute([$studentId]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $q=$this->db->prepare('SELECT cr.*,c.code,c.title,c.credit_units,s.semester_number,a.name AS session_name FROM course_registrations cr JOIN courses c ON c.id=cr.course_id JOIN semesters s ON s.id=cr.semester_id JOIN academic_sessions a ON a.id=s.academic_session_id WHERE cr.student_id=? ORDER BY cr.id DESC LIMIT ? OFFSET ?');
        $q->bindValue(1,$studentId,PDO::PARAM_INT);$q->bindValue(2,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(3,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();return [$q->fetchAll(),$total,$page];
    }
}
