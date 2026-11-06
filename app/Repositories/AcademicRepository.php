<?php
declare(strict_types=1);

namespace Prospak\Repositories;

use PDO;
use Prospak\Core\AcademicValidation as AV;

/** All interpolated SQL identifiers come exclusively from the fixed $meta allowlist. */
final class AcademicRepository
{
    private const META = [
        'faculties'=>['table'=>'faculties', 'extra'=>"(SELECT COUNT(*) FROM departments d WHERE d.faculty_id=e.id) AS child_count, (SELECT COUNT(*) FROM students s JOIN programmes p ON p.id=s.programme_id JOIN departments d ON d.id=p.department_id WHERE d.faculty_id=e.id) AS student_count, NULL AS parent_name"],
        'departments'=>['table'=>'departments', 'extra'=>"(SELECT COUNT(*) FROM programmes p WHERE p.department_id=e.id) AS child_count, (SELECT COUNT(*) FROM students s JOIN programmes p ON p.id=s.programme_id WHERE p.department_id=e.id) AS student_count, (SELECT f.name FROM faculties f WHERE f.id=e.faculty_id) AS parent_name"],
        'programmes'=>['table'=>'programmes', 'extra'=>"0 AS child_count, (SELECT COUNT(*) FROM students s WHERE s.programme_id=e.id) AS student_count, (SELECT d.name FROM departments d WHERE d.id=e.department_id) AS parent_name"],
        'levels'=>['table'=>'academic_levels', 'extra'=>"0 AS child_count, (SELECT COUNT(*) FROM students s WHERE s.academic_level_id=e.id) AS student_count, NULL AS parent_name"],
    ];

    public function __construct(private PDO $pdo) {}


    private function meta(string $entity): array
    {
        AV::entity($entity);
        return self::META[$entity];
    }

    public function find(string $entity, int $id): ?array
    {
        $table = $this->meta($entity)['table'];
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public function page(string $entity, string $search, string $status, int $page, int $perPage=20): array
    {
        $meta = $this->meta($entity);
        $perPage = max(1, min(100, $perPage));
        $params = [];
        $where = [];
        if ($search !== '') {
            $pattern = '%' . strtr($search, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
            $params['code'] = $pattern; $params['name'] = $pattern;
            $where[] = "(e.code LIKE :code ESCAPE '!' OR e.name LIKE :name ESCAPE '!')";
        }
        if (in_array($status, AV::STATUSES, true)) {
            $params['status'] = $status;
            $where[] = 'e.status=:status';
        }
        $filter = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $table = $meta['table'];
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} e{$filter}");
        $this->bind($count, $params);
        $count->execute();
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total/$perPage));
        $page = min(max(1, $page), $pages);
        $order = $entity === 'levels' ? 'e.sort_order ASC, e.id ASC' : 'e.name ASC, e.id ASC';
        $stmt = $this->pdo->prepare("SELECT e.*, {$meta['extra']} FROM {$table} e{$filter} ORDER BY {$order} LIMIT :limit OFFSET :offset");
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page-1)*$perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows'=>$stmt->fetchAll(), 'total'=>$total, 'page'=>$page, 'pages'=>$pages];
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key=>$value) { $stmt->bindValue(':'.$key, $value, PDO::PARAM_STR); }
    }

    public function options(string $entity): array
    {
        $table = $this->meta($entity)['table'];
        $order = $entity === 'levels' ? 'sort_order, id' : 'name, id';
        return $this->pdo->query("SELECT id,code,name,status FROM {$table} ORDER BY {$order}")->fetchAll();
    }

    /** Reject newly selected inactive parents (including ancestors). */
    public function eligibleParent(string $entity, int $id): bool
    {
        $sql = match ($entity) {
            'departments' => "SELECT f.id FROM faculties f WHERE f.id=:id AND f.status='active' FOR UPDATE",
            'programmes' => "SELECT d.id FROM departments d JOIN faculties f ON f.id=d.faculty_id WHERE d.id=:id AND d.status='active' AND f.status='active' FOR UPDATE",
            default => throw new \InvalidArgumentException('No parent for this academic entity.'),
        };
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id'=>$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function eligiblePlacement(string $kind, int $id): bool
    {
        $sql = match ($kind) {
            'programme' => "SELECT p.id FROM programmes p JOIN departments d ON d.id=p.department_id JOIN faculties f ON f.id=d.faculty_id WHERE p.id=:id AND p.status='active' AND d.status='active' AND f.status='active' FOR UPDATE",
            'level' => "SELECT id FROM academic_levels WHERE id=:id AND status='active' FOR UPDATE",
            'department' => "SELECT d.id FROM departments d JOIN faculties f ON f.id=d.faculty_id WHERE d.id=:id AND d.status='active' AND f.status='active' FOR UPDATE",
            default => throw new \InvalidArgumentException('Invalid placement type.'),
        };
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id'=>$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function students(string $entity, int $id, string $search, int $page, int $perPage=20): array
    {
        $filter = match (AV::entity($entity)) {
            'faculties'=>'d.faculty_id=:parent', 'departments'=>'d.id=:parent',
            'programmes'=>'p.id=:parent', 'levels'=>'s.academic_level_id=:parent',
        };
        $params = ['parent'=>$id];
        if ($search !== '') {
            $filter .= " AND (u.full_name LIKE :name ESCAPE '!' OR s.matriculation_number LIKE :matric ESCAPE '!')";
            $pattern = '%'.strtr($search, ['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $params['name']=$pattern;
            $params['matric']=$pattern;
        }
        $from = ' FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN programmes p ON p.id=s.programme_id LEFT JOIN departments d ON d.id=p.department_id LEFT JOIN academic_levels l ON l.id=s.academic_level_id WHERE '.$filter;
        $count = $this->pdo->prepare('SELECT COUNT(*)'.$from);
        foreach ($params as $key=>$value) { $count->bindValue(':'.$key, $value, $key==='parent'?PDO::PARAM_INT:PDO::PARAM_STR); }
        $count->execute();
        $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min(max(1,$page),$pages);
        $stmt=$this->pdo->prepare('SELECT s.user_id, u.full_name, u.account_status, s.matriculation_number, p.name AS programme_name, d.name AS department_name, l.name AS level_name'.$from.' ORDER BY s.last_name,s.first_name,s.id LIMIT :limit OFFSET :offset');
        foreach ($params as $key=>$value) { $stmt->bindValue(':'.$key, $value, $key==='parent'?PDO::PARAM_INT:PDO::PARAM_STR); }
        $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $stmt->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $stmt->execute();
        return ['rows'=>$stmt->fetchAll(), 'total'=>$total, 'page'=>$page, 'pages'=>$pages];
    }
}
