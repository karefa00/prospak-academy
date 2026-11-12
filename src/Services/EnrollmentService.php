<?php
declare(strict_types=1);
final class EnrollmentService {
    public function __construct(private PDO $db) {}
    public function activeSemester(): ?array {
        $r=$this->db->query("SELECT s.*,a.name AS session_name,a.status AS session_status FROM semesters s JOIN academic_sessions a ON a.id=s.academic_session_id WHERE s.status='active' AND a.status='active' LIMIT 1")->fetch();
        return $r?:null;
    }
    public function eligibleCourses(array $student, array $semester): array {
        $q=$this->db->prepare("SELECT c.id,c.code,c.title,c.credit_units FROM courses c JOIN course_programmes cp ON cp.course_id=c.id WHERE cp.programme_id=? AND c.academic_level_id=? AND c.semester_number=? AND c.status='active' ORDER BY c.code");
        $q->execute([(int)$student['programme_id'],(int)$student['academic_level_id'],(int)$semester['semester_number']]);return $q->fetchAll();
    }
    public function mutate(int $userId,int $courseId,string $action): void {
        if(!in_array($action,['add','drop'],true))throw new DomainException('Unknown registration operation.');
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT * FROM students WHERE user_id=? FOR UPDATE');$s->execute([$userId]);$student=$s->fetch();
            if(!$student || !$student['programme_id'] || !$student['academic_level_id']) throw new DomainException('Student programme and level must be assigned.');
            $sem=$this->activeSemester();
            if(!$sem)throw new DomainException('No active semester.');
            $today=(new DateTimeImmutable('now',new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
            if($today<$sem['starts_on'] || $today>$sem['ends_on'] || (!$sem['registration_deadline']) || $today>$sem['registration_deadline'])throw new DomainException('Registration period is closed.');
            $query=$this->db->prepare('SELECT c.*,cp.programme_id FROM courses c LEFT JOIN course_programmes cp ON cp.course_id=c.id AND cp.programme_id=? WHERE c.id=? FOR UPDATE');
            $query->execute([(int)$student['programme_id'],$courseId]);$course=$query->fetch();
            if(!$course)throw new DomainException('Course not found.');
            $query=$this->db->prepare('SELECT status FROM result_batches WHERE course_id=? AND semester_id=? FOR UPDATE');$query->execute([$courseId,$sem['id']]);
            if($query->fetch())throw new DomainException('This course roster is locked for result processing.');
            $query=$this->db->prepare('SELECT * FROM course_registrations WHERE student_id=? AND course_id=? AND semester_id=? FOR UPDATE');
            $query->execute([$student['id'],$courseId,$sem['id']]);$existing=$query->fetch();
            if($action==='drop') {
                if(!$existing || $existing['status']!=='registered')throw new DomainException('No active registration to drop.');
                $q=$this->db->prepare("UPDATE course_registrations SET status='dropped',dropped_at=UTC_TIMESTAMP(6) WHERE id=?");$q->execute([$existing['id']]);$event='dropped';$regId=(int)$existing['id'];
            } else {
                if($course['status']!=='active' || (int)$course['academic_level_id']!==(int)$student['academic_level_id'] || (int)$course['semester_number']!==(int)$sem['semester_number'] || !$course['programme_id'])throw new DomainException('Course is not eligible for your programme, level or semester.');
                if($existing && $existing['status']==='registered')throw new DomainException('Already registered.');
                $q=$this->db->prepare('SELECT prerequisite_course_id FROM course_prerequisites WHERE course_id=?');$q->execute([$courseId]);$required=$q->fetchAll(PDO::FETCH_COLUMN);
                if($required) {
                    $q=$this->db->prepare("SELECT policy_value FROM academic_policies WHERE policy_key='prerequisite_min_grade_point'");$minimum=$q->fetchColumn();
                    if($minimum===false||$minimum===null||!is_numeric($minimum))throw new DomainException('Prerequisite pass policy has not been configured.');
                    foreach($required as $pre) {
                        $q=$this->db->prepare("SELECT MAX(r.grade_point) FROM results r JOIN course_registrations cr ON cr.id=r.registration_id WHERE cr.student_id=? AND cr.course_id=? AND r.status='published'");$q->execute([$student['id'],$pre]);
                        $passed=$q->fetchColumn();
                        if($passed===null || (float)$passed<(float)$minimum)throw new DomainException('Required prerequisite has not been passed.');
                    }
                }
                if($existing) {
                    $q=$this->db->prepare("UPDATE course_registrations SET status='registered',dropped_at=NULL,registered_at=UTC_TIMESTAMP(6) WHERE id=?");$q->execute([$existing['id']]);$regId=(int)$existing['id'];$event='re_registered';
                } else {
                    $q=$this->db->prepare('INSERT INTO course_registrations (student_id,course_id,semester_id) VALUES (?,?,?)');$q->execute([$student['id'],$courseId,$sem['id']]);$regId=(int)$this->db->lastInsertId();$event='registered';
                }
            }
            $q=$this->db->prepare('INSERT INTO course_registration_events (registration_id,action,actor_user_id) VALUES (?,?,?)');$q->execute([$regId,$event,$userId]);
            $this->db->commit();
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }
}
