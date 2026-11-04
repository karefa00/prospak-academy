<?php
declare(strict_types=1);

namespace Prospak\Services;

use PDO;
use Prospak\Core\Database;
use Prospak\Core\PeopleValidation;
use Prospak\Repositories\PeopleRepository;

/** Administrator-owned account lifecycle; multi-table changes are atomic. */
final class PeopleService
{
    public function __construct(private Database $db) {}

    public function pdo(): PDO
    {
        return $this->db->pdo();
    }

    public function repository(): PeopleRepository
    {
        return new PeopleRepository($this->db->pdo());
    }

    /** @return array{id:int,password:string} Secure temporary password is returned once, never persisted as plaintext. */
    public function create(array $input, int $actorId): array
    {
        [$data, $errors] = PeopleValidation::validate($input);
        if ($errors) { throw new \DomainException('Correct the highlighted fields.'); }
        $pdo = $this->db->pdo();
        $password = bin2hex(random_bytes(18));
        $pdo->beginTransaction();
        try {
            $roleId = $this->repository()->roleId($data['role']);
            if ($roleId === null) { throw new \DomainException('Invalid role.'); }
            $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, password_hash, account_status, must_change_password) VALUES (:role, :name, :email, :hash, 'active', 1)");
            $stmt->execute([
                'role' => $roleId, 'name' => $data['full_name'], 'email' => $data['email'],
                'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $this->saveProfile($pdo, $userId, $data, false);
            $this->audit($pdo, $actorId, $userId, 'people.created');
            $pdo->commit();
            return ['id' => $userId, 'password' => $password];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $this->normalizedError($e);
        }
    }

    public function update(int $targetId, array $input, int $actorId): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $current = $this->lockUser($pdo, $targetId);
            if ($current === null) { throw new \DomainException('User not found.'); }
            [$data, $errors] = PeopleValidation::validate($input, true, $current['role_slug']);
            if ($errors) { throw new \DomainException('Correct the highlighted fields.'); }
            if ($actorId === $targetId && $current['role_slug'] !== $data['role']) {
                throw new \DomainException('You cannot change your own role.');
            }
            $roleId = $this->repository()->roleId($data['role']);
            if ($roleId === null) { throw new \DomainException('Invalid role.'); }
            if ($current['role_slug'] === 'administrator' && $data['role'] !== 'administrator') {
                $this->requireAnotherActiveAdmin($pdo, $targetId);
            }
            $securityChange = $current['role_slug'] !== $data['role'] || $current['email'] !== $data['email'];
            $stmt = $pdo->prepare('UPDATE users SET full_name = :name, email = :email, role_id = :role, session_version = session_version + :bump WHERE id = :id');
            $stmt->execute(['name' => $data['full_name'], 'email' => $data['email'], 'role' => $roleId, 'bump' => $securityChange ? 1 : 0, 'id' => $targetId]);
            $this->saveProfile($pdo, $targetId, $data, true);
            if ($securityChange) {
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $targetId]);
            }
            $this->audit($pdo, $actorId, $targetId, 'people.updated');
            $pdo->commit();
            if ($actorId === $targetId && $securityChange) {
                // Keep the editing administrator's current session valid after their own email edit.
                $_SESSION['auth_version'] = (int) $current['session_version'] + 1;
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $this->normalizedError($e);
        }
    }

    public function setStatus(int $targetId, string $status, int $actorId): void
    {
        if (!in_array($status, PeopleValidation::STATUSES, true)) {
            throw new \DomainException('Invalid status.');
        }
        if ($actorId === $targetId) { throw new \DomainException('You cannot change your own account status.'); }
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $current = $this->lockUser($pdo, $targetId);
            if ($current === null) { throw new \DomainException('User not found.'); }
            if ($status === 'suspended' && $current['role_slug'] === 'administrator' && $current['account_status'] === 'active') {
                $this->requireAnotherActiveAdmin($pdo, $targetId);
            }
            if ($current['account_status'] !== $status) {
                $pdo->prepare('UPDATE users SET account_status = :status, session_version = session_version + 1 WHERE id = :id')
                    ->execute(['status' => $status, 'id' => $targetId]);
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $targetId]);
                $this->audit($pdo, $actorId, $targetId, 'people.status_' . $status);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $this->normalizedError($e);
        }
    }

    public function resetTemporaryPassword(int $targetId, int $actorId): string
    {
        if ($targetId === $actorId) { throw new \DomainException('Use Change Password for your own account.'); }
        $pdo = $this->db->pdo();
        $password = bin2hex(random_bytes(18));
        $pdo->beginTransaction();
        try {
            $current = $this->lockUser($pdo, $targetId);
            if ($current === null) { throw new \DomainException('User not found.'); }
            $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 1, session_version = session_version + 1 WHERE id = :id')
                ->execute(['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $targetId]);
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $targetId]);
            $this->audit($pdo, $actorId, $targetId, 'people.credential_reset');
            $pdo->commit();
            return $password;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $this->normalizedError($e);
        }
    }

    private function saveProfile(PDO $pdo, int $userId, array $data, bool $update): void
    {
        if ($data['role'] === 'student') {
            if ($update) {
                $stmt = $pdo->prepare('UPDATE students SET first_name=:first, last_name=:last, matriculation_number=:number, phone=:phone, gender=:gender, date_of_birth=:dob, admission_year=:year WHERE user_id=:uid');
            } else {
                $stmt = $pdo->prepare('INSERT INTO students (first_name, last_name, matriculation_number, phone, gender, date_of_birth, admission_year, user_id) VALUES (:first,:last,:number,:phone,:gender,:dob,:year,:uid)');
            }
            $stmt->execute(['first'=>$data['first_name'], 'last'=>$data['last_name'], 'number'=>$data['matriculation_number'], 'phone'=>$data['phone'], 'gender'=>$data['gender'], 'dob'=>$data['date_of_birth'], 'year'=>$data['admission_year'], 'uid'=>$userId]);
        } elseif ($data['role'] === 'lecturer') {
            if ($update) {
                $stmt = $pdo->prepare('UPDATE lecturers SET first_name=:first, last_name=:last, staff_number=:number, phone=:phone, designation=:designation WHERE user_id=:uid');
            } else {
                $stmt = $pdo->prepare('INSERT INTO lecturers (first_name, last_name, staff_number, phone, designation, user_id) VALUES (:first,:last,:number,:phone,:designation,:uid)');
            }
            $stmt->execute(['first'=>$data['first_name'], 'last'=>$data['last_name'], 'number'=>$data['staff_number'], 'phone'=>$data['phone'], 'designation'=>$data['designation'], 'uid'=>$userId]);
        }
    }

    private function lockUser(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.session_version, u.account_status, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private function requireAnotherActiveAdmin(PDO $pdo, int $targetId): void
    {
        // Lock all active administrator identities to serialize concurrent demotions/suspensions.
        $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug='administrator' AND u.account_status='active' ORDER BY u.id FOR UPDATE");
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (count(array_diff($ids, [$targetId])) < 1) {
            throw new \DomainException('At least one other active administrator must remain.');
        }
    }

    private function audit(PDO $pdo, int $actorId, int $targetId, string $event): void
    {
        $pdo->prepare('INSERT INTO audit_logs (actor_user_id, subject_user_id, event_type) VALUES (:actor,:subject,:event)')
            ->execute(['actor' => $actorId, 'subject' => $targetId, 'event' => $event]);
    }

    private function normalizedError(\Throwable $e): \Throwable
    {
        if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return new \DomainException('Email or institutional identifier is already registered.', 0, $e);
        }
        return $e;
    }
}
