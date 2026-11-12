<?php
declare(strict_types=1);
final class UserRepository {
    public function __construct(private PDO $db) {}
    public function byId(int $id): ?array {
        $q=$this->db->prepare('SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
        $q->execute([$id]);return $q->fetch() ?: null;
    }
    public function byEmail(string $email): ?array {
        $q=$this->db->prepare('SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=?');
        $q->execute([$email]);return $q->fetch() ?: null;
    }
    public function create(string $role,string $name,string $email,string $password): int {
        $q=$this->db->prepare('INSERT INTO users (role_id,full_name,email,password_hash,must_change_password) SELECT id,?,?,?,1 FROM roles WHERE slug=?');
        $q->execute([$name,$email,password_hash($password,PASSWORD_BCRYPT),$role]);
        if($q->rowCount()!==1)throw new DomainException('Unknown user role.');
        return (int)$this->db->lastInsertId();
    }
    public function paginate(int $page,string $search=''): array {
        $like='%'.$search.'%';
        $count=$this->db->prepare('SELECT COUNT(*) FROM users WHERE full_name LIKE ? OR email LIKE ?');
        $count->execute([$like,$like]);$total=(int)$count->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/PAGE_SIZE)));
        $stmt=$this->db->prepare('SELECT u.id,u.full_name,u.email,u.account_status,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.full_name LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1,$like);$stmt->bindValue(2,$like);$stmt->bindValue(3,PAGE_SIZE,PDO::PARAM_INT);$stmt->bindValue(4,($page-1)*PAGE_SIZE,PDO::PARAM_INT);$stmt->execute();
        return [$stmt->fetchAll(),$total,$page];
    }
}
