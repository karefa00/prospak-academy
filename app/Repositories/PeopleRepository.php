<?php
declare(strict_types=1);

namespace Prospak\Repositories;

use PDO;

final class PeopleRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT u.id, u.full_name, u.email, u.role_id, u.account_status, u.must_change_password,
       u.session_version, u.created_at, u.updated_at, u.last_login_at,
       r.slug AS role_slug, r.display_name AS role_label,
       s.id AS student_id, s.matriculation_number, s.first_name AS student_first_name,
       s.last_name AS student_last_name, s.phone AS student_phone, s.gender, s.date_of_birth,
       s.admission_year, s.passport_filename,
       l.id AS lecturer_id, l.staff_number, l.first_name AS lecturer_first_name,
       l.last_name AS lecturer_last_name, l.phone AS lecturer_phone, l.designation
FROM users u
JOIN roles r ON r.id = u.role_id
LEFT JOIN students s ON s.user_id = u.id
LEFT JOIN lecturers l ON l.user_id = u.id
WHERE u.id = :id LIMIT 1
SQL);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function roleId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE slug = :role LIMIT 1');
        $stmt->execute(['role' => $slug]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function page(string $search, string $role, string $status, int $page, int $perPage = 20): array
    {
        $perPage = max(1, min(100, $perPage));
        $where = [];
        $params = [];
        if ($role !== '') {
            $where[] = 'r.slug = :role';
            $params['role'] = $role;
        }
        if ($status !== '') {
            $where[] = 'u.account_status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            // Escape the LIKE metacharacters and the escape symbol itself.
            $query = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(u.full_name LIKE :findname ESCAPE '!' OR u.email LIKE :findemail ESCAPE '!' OR s.matriculation_number LIKE :findmatric ESCAPE '!' OR l.staff_number LIKE :findstaff ESCAPE '!')";
            $params['findname'] = $params['findemail'] = $params['findmatric'] = $params['findstaff'] = $query;
        }
        $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $from = ' FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN students s ON s.user_id = u.id LEFT JOIN lecturers l ON l.user_id = u.id';
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from . $filter);
        foreach ($params as $key => $value) { $count->bindValue(':' . $key, $value, PDO::PARAM_STR); }
        $count->execute();
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $sql = 'SELECT u.id, u.full_name, u.email, u.account_status, u.must_change_password, r.slug AS role_slug, s.matriculation_number, l.staff_number'
             . $from . $filter . ' ORDER BY u.id DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR); }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page, 'perPage' => $perPage];
    }
}
