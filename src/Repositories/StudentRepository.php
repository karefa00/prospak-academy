<?php
declare(strict_types=1);
final class StudentRepository {
    public function __construct(private PDO $db) {}
    public function byUser(int $uid): ?array {
        $q=$this->db->prepare('SELECT s.*,u.email,u.full_name,p.name AS programme_name,l.name AS level_name FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN programmes p ON p.id=s.programme_id LEFT JOIN academic_levels l ON l.id=s.academic_level_id WHERE s.user_id=?');$q->execute([$uid]);return $q->fetch()?:null;
    }
    public function byId(int $id): ?array {
        $q=$this->db->prepare('SELECT s.*,u.email,u.full_name,p.name AS programme_name,l.name AS level_name FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN programmes p ON p.id=s.programme_id LEFT JOIN academic_levels l ON l.id=s.academic_level_id WHERE s.id=?');$q->execute([$id]);return $q->fetch()?:null;
    }
    public function paginate(int $page,string $search=''): array {
        $like='%'.$search.'%';
        $where=" WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.matriculation_number LIKE ? ";
        $count=$this->db->prepare('SELECT COUNT(*) FROM students s'.$where);$count->execute([$like,$like,$like]);$total=(int)$count->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $stmt=$this->db->prepare('SELECT s.id,s.matriculation_number,s.first_name,s.last_name,s.passport_filename,u.email,u.account_status,p.name AS programme_name,l.name AS level_name FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN programmes p ON p.id=s.programme_id LEFT JOIN academic_levels l ON l.id=s.academic_level_id '.$where.' ORDER BY s.id DESC LIMIT ? OFFSET ?');
        foreach([$like,$like,$like] as $i=>$v)$stmt->bindValue($i+1,$v);
        $stmt->bindValue(4,PAGE_SIZE,PDO::PARAM_INT);$stmt->bindValue(5,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$stmt->execute();return [$stmt->fetchAll(),$total,$page];
    }
}
