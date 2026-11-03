<?php
declare(strict_types=1);

namespace Prospak\Services;

use PDO;
use Prospak\Core\Config;
use Prospak\Core\Database;
use Prospak\Core\Session;
use Prospak\Core\Validation;
use Prospak\Repositories\UsersRepository;

final class AuthService
{
    private ?array $currentUser = null;
    private bool $userChecked = false;

    public function __construct(private Database $db) {}

    public function user(): ?array
    {
        if ($this->userChecked) {
            return $this->currentUser;
        }
        $this->userChecked = true;
        if (!isset($_SESSION['auth_id'], $_SESSION['auth_version']) || !is_int($_SESSION['auth_id'])) {
            return null;
        }
        $user = (new UsersRepository($this->db->pdo()))->byId($_SESSION['auth_id']);
        if (!$user || $user['account_status'] !== 'active' || (int) $user['session_version'] !== (int) $_SESSION['auth_version']) {
            unset($_SESSION['auth_id'], $_SESSION['auth_version']);
            return null;
        }
        return $this->currentUser = $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        return $user !== null && (new UsersRepository($this->db->pdo()))->hasPermission((int) $user['role_id'], $permission);
    }

    /** Returns ok, invalid, or throttled. No distinction between unknown and wrong-password accounts. */
    public function login(string $email, string $password, string $remoteIp): string
    {
        $pdo = $this->db->pdo();
        $idHash = $this->fingerprint($email);
        $ipHash = $this->fingerprint($remoteIp);
        if ($this->isLimited($pdo, 'login', $idHash, $ipHash, 5, 20, 15)) {
            return 'throttled';
        }
        $users = new UsersRepository($pdo);
        $user = $users->byEmail($email);
        // Perform a bcrypt comparison even for unknown accounts to reduce timing-based enumeration.
        $dummyHash = '$2y$12$yLVhCxuxeuG7ftrpZiBwnO5RU3zk/vcrnhvM08rsrpN/.B4j5bQc.';
        $verified = password_verify($password, $user['password_hash'] ?? $dummyHash);
        if (!$user || $user['account_status'] !== 'active' || !$verified) {
            $this->recordAttempt($pdo, 'login', $idHash, $ipHash);
            return 'invalid';
        }
        $pdo->beginTransaction();
        try {
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $rehash = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $rehash->execute(['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $user['id']]);
            }
            $update = $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(6) WHERE id = :id');
            $update->execute(['id' => $user['id']]);
            $delete = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_type = 'login' AND identifier_hash = :hash");
            $delete->execute(['hash' => $idHash]);
            $this->audit($pdo, 'auth.login', (int) $user['id'], (int) $user['id']);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        $_SESSION['auth_id'] = (int) $user['id'];
        $_SESSION['auth_version'] = (int) $user['session_version'];
        $this->userChecked = false;
        $this->currentUser = null;
        return 'ok';
    }

    public function logout(): void
    {
        try {
            $user = $this->user();
            if ($user !== null) {
                $this->audit($this->db->pdo(), 'auth.logout', (int) $user['id'], (int) $user['id']);
            }
        } catch (\Throwable $ignored) {
            // Do not trap a visitor in an authenticated session during a DB outage.
        } finally {
            $this->userChecked = false;
            $this->currentUser = null;
            Session::destroy();
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if (!Validation::password($newPassword)) {
            return false;
        }
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT password_hash, session_version FROM users WHERE id = :id AND account_status = :status FOR UPDATE');
            $query->execute(['id' => $userId, 'status' => 'active']);
            $user = $query->fetch();
            if (!$user || !password_verify($currentPassword, $user['password_hash']) || hash_equals($currentPassword, $newPassword)) {
                $pdo->rollBack();
                return false;
            }
            $update = $pdo->prepare('UPDATE users SET password_hash = :hash, session_version = session_version + 1 WHERE id = :id');
            $update->execute(['hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $userId]);
            $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id');
            $delete->execute(['id' => $userId]);
            $this->audit($pdo, 'auth.password_changed', $userId, $userId);
            $pdo->commit();
            $_SESSION['auth_version'] = (int) $user['session_version'] + 1;
            $this->userChecked = false;
            return true;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /** Always respond identically for unknown and known emails. */
    public function requestPasswordReset(string $email, string $remoteIp): void
    {
        $pdo = $this->db->pdo();
        $idHash = $this->fingerprint($email);
        $ipHash = $this->fingerprint($remoteIp);
        if ($this->isLimited($pdo, 'reset_request', $idHash, $ipHash, 3, 12, 60)) {
            return;
        }
        $this->recordAttempt($pdo, 'reset_request', $idHash, $ipHash);
        if (strtolower(Config::get('MAIL_ENABLED', 'false')) !== 'true') {
            return;
        }
        $sender = Config::get('MAIL_FROM');
        // Avoid user enumeration even if the mail sender is misconfigured.
        if (!filter_var($sender, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $sender)) {
            return;
        }
        $user = (new UsersRepository($pdo))->byEmail($email);
        if (!$user || $user['account_status'] !== 'active') {
            return;
        }
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id');
            $delete->execute(['id' => $user['id']]);
            $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at) VALUES (:user, :selector, :hash, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 30 MINUTE))');
            $insert->execute(['user' => $user['id'], 'selector' => $selector, 'hash' => hash('sha256', $validator)]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $link = Config::appUrl() . '/reset-password?selector=' . $selector . '#token=' . $validator;
        $sent = false;
        try {
            $sent = @mail($user['email'], 'Prospak Academy password reset',
                "A password reset was requested for your Prospak Academy account.\n\n" .
                "Open this link within 30 minutes:\n" . $link . "\n\n" .
                "If this was not you, ignore this email.\n",
                ['From' => $sender, 'Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (\Throwable $ignored) {
            // Keep the outward response indistinguishable from an unknown account.
        }
        if (!$sent) {
            $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE selector = :selector');
            $delete->execute(['selector' => $selector]);
            $this->audit($pdo, 'auth.reset_delivery_failed', null, (int) $user['id']);
        }
    }

    public function resetPassword(string $selector, string $validator, string $newPassword): bool
    {
        if (!preg_match('/^[0-9a-f]{32}$/D', $selector) || !preg_match('/^[0-9a-f]{64}$/D', $validator) || !Validation::password($newPassword)) {
            return false;
        }
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT t.user_id, t.token_hash FROM password_reset_tokens t JOIN users u ON u.id = t.user_id WHERE t.selector = :selector AND t.expires_at > UTC_TIMESTAMP(6) AND u.account_status = :status FOR UPDATE');
            $query->execute(['selector' => $selector, 'status' => 'active']);
            $token = $query->fetch();
            if (!$token || !hash_equals($token['token_hash'], hash('sha256', $validator))) {
                $pdo->rollBack();
                return false;
            }
            $update = $pdo->prepare('UPDATE users SET password_hash = :hash, session_version = session_version + 1 WHERE id = :id');
            $update->execute(['hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $token['user_id']]);
            $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id');
            $delete->execute(['id' => $token['user_id']]);
            $this->audit($pdo, 'auth.password_reset', null, (int) $token['user_id']);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, Config::appKey());
    }

    private function isLimited(PDO $pdo, string $type, string $idHash, string $ipHash, int $idMax, int $ipMax, int $minutes): bool
    {
        // Two selective indexed counts are cheaper than scanning every recent attempt.
        if (!in_array($minutes, [15, 60], true)) {
            throw new \InvalidArgumentException('Unsupported rate-limit window.');
        }
        foreach ([['identifier_hash', $idHash, $idMax], ['ip_hash', $ipHash, $ipMax]] as [$column, $hash, $limit]) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_type = :type AND ' . $column . ' = :hash AND occurred_at >= DATE_SUB(UTC_TIMESTAMP(6), INTERVAL ' . $minutes . ' MINUTE)');
            $query->execute(['type' => $type, 'hash' => $hash]);
            if ((int) $query->fetchColumn() >= $limit) {
                return true;
            }
        }
        return false;
    }

    private function recordAttempt(PDO $pdo, string $type, string $idHash, string $ipHash): void
    {
        $query = $pdo->prepare('INSERT INTO login_attempts (attempt_type, identifier_hash, ip_hash) VALUES (:type, :identifier, :ip)');
        $query->execute(['type' => $type, 'identifier' => $idHash, 'ip' => $ipHash]);
    }

    private function audit(PDO $pdo, string $event, ?int $actor, ?int $subject): void
    {
        $query = $pdo->prepare('INSERT INTO audit_logs (actor_user_id, subject_user_id, event_type) VALUES (:actor, :subject, :event)');
        $query->execute(['actor' => $actor, 'subject' => $subject, 'event' => $event]);
    }
}
