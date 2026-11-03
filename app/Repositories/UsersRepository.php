<?php
declare(strict_types=1);

namespace Prospak\Repositories;

use PDO;

final class UsersRepository
{
    public function __construct(private PDO $pdo) {}

    public function byEmail(string $email): ?array
    {
        $query = $this->pdo->prepare('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1');
        $query->execute(['email' => $email]);
        return $query->fetch() ?: null;
    }

    public function byId(int $userId): ?array
    {
        $query = $this->pdo->prepare('SELECT u.id, u.email, u.full_name, u.role_id, u.account_status, u.session_version, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $query->execute(['id' => $userId]);
        return $query->fetch() ?: null;
    }

    public function hasPermission(int $roleId, string $slug): bool
    {
        $query = $this->pdo->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = :role AND p.slug = :slug LIMIT 1');
        $query->execute(['role' => $roleId, 'slug' => $slug]);
        return (bool) $query->fetchColumn();
    }
}
