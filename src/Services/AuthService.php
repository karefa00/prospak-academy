<?php
declare(strict_types=1);
final class AuthService {
    public function __construct(private PDO $db) {}
    public function login(string $email,string $password): bool {
        $hashKey=env_setting('APP_KEY','');
        if(strlen($hashKey)<32)throw new RuntimeException('Configure APP_KEY in .env.');
        $identifier=hash_hmac('sha256',$email,$hashKey);
        $ip=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'local'),$hashKey);
        $since=gmdate('Y-m-d H:i:s',time()-900);
        $q=$this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE attempt_type='login' AND (identifier_hash=? OR ip_hash=?) AND occurred_at>=?");
        $q->execute([$identifier,$ip,$since]);
        if((int)$q->fetchColumn()>=8) return false;
        $user=(new UserRepository($this->db))->byEmail($email);
        $valid=$user && $user['account_status']==='active' && password_verify($password,$user['password_hash']);
        if(!$valid) {
            $q=$this->db->prepare("INSERT INTO login_attempts (attempt_type,identifier_hash,ip_hash) VALUES ('login',?,?)");$q->execute([$identifier,$ip]);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$user['id'];
        $_SESSION['session_version']=(int)$user['session_version'];
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
        $q=$this->db->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([(int)$user['id']]);
        if(password_needs_rehash($user['password_hash'],PASSWORD_BCRYPT)) {
            $q=$this->db->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_BCRYPT),(int)$user['id']]);
        }
        return true;
    }
    public function changePassword(int $id,string $current,string $new): void {
        if(strlen($new)<10 || strlen($new)>72)throw new InvalidArgumentException('New password must be 10–72 characters.');
        $user=(new UserRepository($this->db))->byId($id);
        if(!$user || !password_verify($current,$user['password_hash']))throw new InvalidArgumentException('Current password is incorrect.');
        $q=$this->db->prepare('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?');
        $q->execute([password_hash($new,PASSWORD_BCRYPT),$id]);
        session_regenerate_id(true);
        $_SESSION['session_version']=(int)$user['session_version']+1;
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    }
    public static function logout(): void {
        $_SESSION=[];
        if(ini_get('session.use_cookies')) {
            $p=session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
        }
        session_destroy();
    }
}
