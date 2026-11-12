<?php
declare(strict_types=1);
final class GradeService {
    public function __construct(private PDO $db) {}
    private function offering(int $courseId,int $semesterId): array {
        $q=$this->db->prepare('SELECT c.id,c.code,c.title,c.credit_units,c.semester_number,s.semester_number AS active_semester FROM courses c JOIN semesters s ON s.id=? WHERE c.id=?');
        $q->execute([$semesterId,$courseId]);$row=$q->fetch();
        if(!$row || (int)$row['semester_number']!==(int)$row['active_semester'])throw new DomainException('Invalid course and semester combination.');
        return $row;
    }
    public function lecturerAssigned(int $userId,int $courseId,int $semesterId): bool {
        $q=$this->db->prepare("SELECT COUNT(*) FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id WHERE l.user_id=? AND ca.course_id=? AND ca.semester_id=? AND ca.status='active'");$q->execute([$userId,$courseId,$semesterId]);return (int)$q->fetchColumn()>0;
    }
    public function offerings(?int $lecturerUserId=null): array {
        if($lecturerUserId) {
            $q=$this->db->prepare('SELECT ca.course_id,ca.semester_id,c.code,c.title,ss.name AS session_name,s.semester_number FROM course_assignments ca JOIN lecturers l ON l.id=ca.lecturer_id JOIN courses c ON c.id=ca.course_id JOIN semesters s ON s.id=ca.semester_id JOIN academic_sessions ss ON ss.id=s.academic_session_id WHERE l.user_id=? AND ca.status=\'active\' ORDER BY ca.id DESC LIMIT 100');
            $q->execute([$lecturerUserId]);return $q->fetchAll();
        }
        return $this->db->query("SELECT ca.course_id,ca.semester_id,c.code,c.title,ss.name AS session_name,s.semester_number FROM course_assignments ca JOIN courses c ON c.id=ca.course_id JOIN semesters s ON s.id=ca.semester_id JOIN academic_sessions ss ON ss.id=s.academic_session_id WHERE ca.status='active' GROUP BY ca.course_id,ca.semester_id,c.code,c.title,ss.name,s.semester_number ORDER BY ca.semester_id DESC,c.code LIMIT 100")->fetchAll();
    }
    public function components(int $courseId,int $semesterId): array {
        $q=$this->db->prepare("SELECT id,component_type,name,max_score FROM assessments WHERE course_id=? AND semester_id=? AND status='active' ORDER BY component_type");$q->execute([$courseId,$semesterId]);$rows=$q->fetchAll();$by=[];
        foreach($rows as $r){ if(isset($by[$r['component_type']]))throw new DomainException('Multiple active assessments of the same type.');$by[$r['component_type']]=$r; }
        if(count($by)!==2 || !isset($by['ca'],$by['exam']) || abs((float)$by['ca']['max_score']+(float)$by['exam']['max_score']-100)>0.001) throw new DomainException('Administrator must configure CA and Exam totaling 100 points.');
        return $by;
    }
    public function roster(int $courseId,int $semesterId,int $page): array {
        $this->offering($courseId,$semesterId);
        $q=$this->db->prepare("SELECT COUNT(*) FROM course_registrations WHERE course_id=? AND semester_id=? AND status='registered'");$q->execute([$courseId,$semesterId]);$total=(int)$q->fetchColumn();$page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $q=$this->db->prepare("SELECT cr.id AS registration_id,s.matriculation_number,u.full_name,r.total_score,r.grade,r.status AS result_status, MAX(CASE WHEN a.component_type='ca' THEN ss.score END) AS ca_score, MAX(CASE WHEN a.component_type='exam' THEN ss.score END) AS exam_score FROM course_registrations cr JOIN students s ON s.id=cr.student_id JOIN users u ON u.id=s.user_id LEFT JOIN results r ON r.registration_id=cr.id LEFT JOIN student_scores ss ON ss.registration_id=cr.id LEFT JOIN assessments a ON a.id=ss.assessment_id WHERE cr.course_id=? AND cr.semester_id=? AND cr.status='registered' GROUP BY cr.id,s.matriculation_number,u.full_name,r.total_score,r.grade,r.status ORDER BY s.matriculation_number LIMIT ? OFFSET ?");
        $q->bindValue(1,$courseId,PDO::PARAM_INT);$q->bindValue(2,$semesterId,PDO::PARAM_INT);$q->bindValue(3,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(4,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();return [$q->fetchAll(),$total,$page];
    }
    private static function cents(string $str): int { return (int)round(((float)$str)*100); }
    public function saveScore(int $lecturerUserId,int $courseId,int $semesterId,int $registrationId,mixed $ca,mixed $exam): void {
        if(!$this->lecturerAssigned($lecturerUserId,$courseId,$semesterId))throw new DomainException('Only an assigned lecturer may enter scores.');
        $course=$this->offering($courseId,$semesterId);
        $components=$this->components($courseId,$semesterId);
        $ca=Input::score($ca,(float)$components['ca']['max_score']);$exam=Input::score($exam,(float)$components['exam']['max_score']);
        $totalCents=self::cents($ca)+self::cents($exam);
        $total=number_format($totalCents/100,2,'.','');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM course_registrations WHERE id=? AND course_id=? AND semester_id=? AND status=\'registered\' FOR UPDATE');$q->execute([$registrationId,$courseId,$semesterId]);
            if(!$q->fetch())throw new DomainException('Student is not actively registered for this offering.');
            $q=$this->db->prepare('SELECT status FROM result_batches WHERE course_id=? AND semester_id=? FOR UPDATE');$q->execute([$courseId,$semesterId]);$status=$q->fetchColumn();
            if($status!==false && $status!=='rejected')throw new DomainException('Results have been submitted and are locked.');
            $q=$this->db->prepare('SELECT grading_scheme_id FROM offering_grading_schemes WHERE course_id=? AND semester_id=?');$q->execute([$courseId,$semesterId]);$scheme=$q->fetchColumn();
            if(!$scheme){$scheme=$this->db->query("SELECT id FROM grading_schemes WHERE status='active' LIMIT 1")->fetchColumn();if(!$scheme)throw new DomainException('Administrator must activate a grading scheme.');
                $q=$this->db->prepare('INSERT INTO offering_grading_schemes (course_id,semester_id,grading_scheme_id) VALUES (?,?,?)');$q->execute([$courseId,$semesterId,$scheme]);
            }
            $q=$this->db->prepare('SELECT grade,grade_point FROM grading_bands WHERE grading_scheme_id=? AND min_score<=? AND max_score>=? ORDER BY min_score DESC LIMIT 1');$q->execute([$scheme,$total,$total]);$band=$q->fetch();
            if(!$band)throw new DomainException('No grading band covers this score; check grading configuration.');
            $insert=$this->db->prepare('INSERT INTO student_scores (registration_id,assessment_id,score,entered_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),entered_by=VALUES(entered_by)');
            $insert->execute([$registrationId,$components['ca']['id'],$ca,$lecturerUserId]);$insert->execute([$registrationId,$components['exam']['id'],$exam,$lecturerUserId]);
            $quality=number_format((float)$band['grade_point']*(int)$course['credit_units'],2,'.','');
            $q=$this->db->prepare("INSERT INTO results (registration_id,grading_scheme_id,total_score,grade,grade_point,quality_points,status) VALUES (?,?,?,?,?,?,'draft') ON DUPLICATE KEY UPDATE total_score=VALUES(total_score),grade=VALUES(grade),grade_point=VALUES(grade_point),quality_points=VALUES(quality_points),status='draft'");
            $q->execute([$registrationId,$scheme,$total,$band['grade'],$band['grade_point'],$quality]);
            $this->db->commit();
        } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function submit(int $userId,int $courseId,int $semesterId): void {
        if(!$this->lecturerAssigned($userId,$courseId,$semesterId))throw new DomainException('Lecturer assignment required.');
        $this->components($courseId,$semesterId);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT status FROM result_batches WHERE course_id=? AND semester_id=? FOR UPDATE');$q->execute([$courseId,$semesterId]);$status=$q->fetchColumn();
            if($status!==false && $status!=='rejected')throw new DomainException('This batch cannot be resubmitted.');
            $q=$this->db->prepare("SELECT COUNT(*) AS total,SUM(CASE WHEN r.status='draft' AND (SELECT COUNT(*) FROM student_scores sc JOIN assessments a ON a.id=sc.assessment_id WHERE sc.registration_id=cr.id AND a.course_id=cr.course_id AND a.semester_id=cr.semester_id AND a.status='active')=2 THEN 1 ELSE 0 END) AS complete FROM course_registrations cr LEFT JOIN results r ON r.registration_id=cr.id WHERE cr.course_id=? AND cr.semester_id=? AND cr.status='registered'");$q->execute([$courseId,$semesterId]);$completeness=$q->fetch();
            if((int)$completeness['total']<1 || (int)$completeness['complete'] !== (int)$completeness['total'])throw new DomainException('Complete scores for every registered student before submission.');
            if($status===false){$q=$this->db->prepare("INSERT INTO result_batches (course_id,semester_id,status,submitted_by) VALUES (?,?,'submitted',?)");$q->execute([$courseId,$semesterId,$userId]);}
            else{$q=$this->db->prepare("UPDATE result_batches SET status='submitted',submitted_by=?,submitted_at=UTC_TIMESTAMP(6) WHERE course_id=? AND semester_id=?");$q->execute([$userId,$courseId,$semesterId]);}
            $q=$this->db->prepare("UPDATE results r JOIN course_registrations cr ON cr.id=r.registration_id SET r.status='submitted' WHERE cr.course_id=? AND cr.semester_id=? AND cr.status='registered'");$q->execute([$courseId,$semesterId]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function batches(int $page): array {
        $total=(int)$this->db->query('SELECT COUNT(*) FROM result_batches')->fetchColumn();$page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $q=$this->db->prepare('SELECT b.*,c.code,c.title,a.name AS session_name,s.semester_number,u.full_name AS lecturer_name FROM result_batches b JOIN courses c ON c.id=b.course_id JOIN semesters s ON s.id=b.semester_id JOIN academic_sessions a ON a.id=s.academic_session_id JOIN users u ON u.id=b.submitted_by ORDER BY b.id DESC LIMIT ? OFFSET ?');$q->bindValue(1,PAGE_SIZE,PDO::PARAM_INT);$q->bindValue(2,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$q->execute();return [$q->fetchAll(),$total,$page];
    }
    public function review(int $officerId,int $batchId,string $decision,string $reason): void {
        if(!in_array($decision,['approved','rejected'],true))throw new DomainException('Invalid review decision.');
        if($decision==='rejected' && ($reason==='' || mb_strlen_safe($reason)>1000))throw new DomainException('A rejection reason is required (up to 1,000 characters).');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT * FROM result_batches WHERE id=? FOR UPDATE');$q->execute([$batchId]);$batch=$q->fetch();
            if(!$batch || $batch['status']!=='submitted')throw new DomainException('Only submitted batches can be reviewed.');
            $q=$this->db->prepare('INSERT INTO result_reviews (batch_id,reviewer_user_id,decision,reason) VALUES (?,?,?,?)');$q->execute([$batchId,$officerId,$decision,$decision==='approved'?null:$reason]);
            $q=$this->db->prepare('UPDATE result_batches SET status=? WHERE id=?');$q->execute([$decision,$batchId]);
            $q=$this->db->prepare('UPDATE results r JOIN course_registrations cr ON cr.id=r.registration_id SET r.status=? WHERE cr.course_id=? AND cr.semester_id=? AND r.status=\'submitted\'');$q->execute([$decision==='rejected'?'draft':'approved',$batch['course_id'],$batch['semester_id']]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function publish(int $adminId,int $batchId): void {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT * FROM result_batches WHERE id=? FOR UPDATE');$q->execute([$batchId]);$batch=$q->fetch();
            if(!$batch || $batch['status']!=='approved')throw new DomainException('Only approved batches can be published.');
            $q=$this->db->prepare("UPDATE results r JOIN course_registrations cr ON cr.id=r.registration_id JOIN courses c ON c.id=cr.course_id SET r.credit_units_snapshot=c.credit_units,r.status='published' WHERE cr.course_id=? AND cr.semester_id=? AND r.status='approved'");$q->execute([$batch['course_id'],$batch['semester_id']]);
            if($q->rowCount()===0)throw new DomainException('Approved results not found.');
            $q=$this->db->prepare('INSERT INTO result_publications (batch_id,published_by) VALUES (?,?)');$q->execute([$batchId,$adminId]);
            $q=$this->db->prepare("UPDATE result_batches SET status='published' WHERE id=?");$q->execute([$batchId]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function studentResults(int $studentId): array {
        $q=$this->db->prepare("SELECT r.total_score,r.grade,r.grade_point,r.credit_units_snapshot,cr.id AS registration_id,cr.course_id,cr.semester_id,c.code,c.title,s.semester_number,a.name AS session_name,a.starts_on AS period_key FROM results r JOIN course_registrations cr ON cr.id=r.registration_id JOIN courses c ON c.id=cr.course_id JOIN semesters s ON s.id=cr.semester_id JOIN academic_sessions a ON a.id=s.academic_session_id WHERE cr.student_id=? AND r.status='published' ORDER BY a.starts_on DESC,s.semester_number DESC,c.code");$q->execute([$studentId]);return $q->fetchAll();
    }
    public function cgpa(array $rows, ?string $policy): array {
        if($policy!==null && !in_array($policy,['all_attempts','latest_published','highest_published'],true))throw new DomainException('Invalid repeat-course policy.');
        $eligible=[];$repeat=false;
        foreach($rows as $row){
            $key=(int)$row['course_id'];
            if($policy==='all_attempts'){$eligible[]=$row;continue;}
            if(isset($eligible[$key])){
                $repeat=true;
                if($policy==='latest_published' && ((string)$row['period_key'] < (string)$eligible[$key]['period_key'] || ((string)$row['period_key']===(string)$eligible[$key]['period_key'] && (int)$row['registration_id']<(int)$eligible[$key]['registration_id'])))continue;
                if($policy==='highest_published' && (float)$row['grade_point']<=(float)$eligible[$key]['grade_point'])continue;
            }
            $eligible[$key]=$row;
        }
        if($repeat && $policy===null)return ['value'=>null,'credits'=>null,'needs_policy'=>true];
        $credits=0;$qualityCents=0;
        foreach($eligible as $r){$credit=(int)$r['credit_units_snapshot'];if($credit<=0)throw new DomainException('Published credit snapshot missing.');$credits+=$credit;$qualityCents+=(int)round(100*(float)$r['grade_point'])*$credit;}
        return ['value'=>$credits>0?number_format($qualityCents/$credits/100,2,'.',''):null,'credits'=>$credits,'needs_policy'=>false];
    }
    public function policy(): ?string {
        $q=$this->db->query("SELECT policy_value FROM academic_policies WHERE policy_key='repeat_policy'");$v=$q->fetchColumn();return $v===false||$v===null?null:(string)$v;
    }
    public function setPolicy(?string $policy,?string $minimum,int $adminId): void {
        if($policy!==null && !in_array($policy,['all_attempts','latest_published','highest_published'],true))throw new InvalidArgumentException('Invalid repeat policy.');
        if($minimum!==null)Input::score($minimum,5.0);
        $q=$this->db->prepare('UPDATE academic_policies SET policy_value=?,updated_by=? WHERE policy_key=?');
        $q->execute([$policy,$adminId,'repeat_policy']);$q->execute([$minimum,$adminId,'prerequisite_min_grade_point']);
    }
    public function setupScheme(int $adminId,int $points): void {
        if(!in_array($points,[4,5],true))throw new InvalidArgumentException('Select a four-point or five-point grading example.');
        $name='Prospak example '.$points.'-point';
        $bands=$points===5?[[70,100,'A',5],[60,69.99,'B',4],[50,59.99,'C',3],[45,49.99,'D',2],[40,44.99,'E',1],[0,39.99,'F',0]]:[[70,100,'A',4],[60,69.99,'B',3],[50,59.99,'C',2],[45,49.99,'D',1],[0,44.99,'F',0]];
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM grading_schemes WHERE name=? FOR UPDATE');$q->execute([$name]);$id=$q->fetchColumn();
            if(!$id){$q=$this->db->prepare("INSERT INTO grading_schemes (name,status) VALUES (?,'inactive')");$q->execute([$name]);$id=(int)$this->db->lastInsertId();
                $q=$this->db->prepare('INSERT INTO grading_bands (grading_scheme_id,min_score,max_score,grade,grade_point) VALUES (?,?,?,?,?)');foreach($bands as $r)$q->execute([$id,...$r]);
            }
            $this->db->exec("UPDATE grading_schemes SET status='inactive' WHERE status='active'");
            $q=$this->db->prepare("UPDATE grading_schemes SET status='active' WHERE id=?");$q->execute([$id]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function setupComponents(int $courseId,int $semesterId,mixed $caMax,mixed $examMax): void {
        $this->offering($courseId,$semesterId);
        $ca=Input::score($caMax,100);$exam=Input::score($examMax,100);
        if((int)round(((float)$ca+(float)$exam)*100)!==10000 || (float)$ca<=0 || (float)$exam<=0)throw new InvalidArgumentException('CA and examination maximums must both be positive and total 100.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT COUNT(*) FROM assessments WHERE course_id=? AND semester_id=?');$q->execute([$courseId,$semesterId]);
            if((int)$q->fetchColumn()>0)throw new DomainException('Assessments already exist for this offering.');
            $q=$this->db->prepare('INSERT INTO assessments (course_id,semester_id,name,component_type,max_score) VALUES (?,?,?,?,?)');
            $q->execute([$courseId,$semesterId,'Continuous Assessment','ca',$ca]);$q->execute([$courseId,$semesterId,'Final Examination','exam',$exam]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
