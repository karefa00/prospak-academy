<?php
declare(strict_types=1);
final class CourseRepository {
    public function __construct(private PDO $db) {}
    public function paginate(int $page,string $search=''): array {
        $like='%'.$search.'%';
        $where=' WHERE c.code LIKE ? OR c.title LIKE ? ';
        $st=$this->db->prepare('SELECT COUNT(*) FROM courses c'.$where);$st->execute([$like,$like]);$total=(int)$st->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $st=$this->db->prepare('SELECT c.*,d.name AS department_name,l.name AS level_name FROM courses c JOIN departments d ON d.id=c.department_id JOIN academic_levels l ON l.id=c.academic_level_id '.$where.' ORDER BY c.id DESC LIMIT ? OFFSET ?');
        $st->bindValue(1,$like);$st->bindValue(2,$like);$st->bindValue(3,PAGE_SIZE,PDO::PARAM_INT);$st->bindValue(4,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$st->execute();return [$st->fetchAll(),$total,$page];
    }
    public function allActive(): array { return $this->db->query("SELECT id,code,title,semester_number,credit_units FROM courses WHERE status='active' ORDER BY code")->fetchAll(); }
    public function byId(int $id): ?array {
        $st=$this->db->prepare('SELECT * FROM courses WHERE id=?');$st->execute([$id]);return $st->fetch()?:null;
    }
}
