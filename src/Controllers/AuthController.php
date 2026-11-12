<?php
declare(strict_types=1);
final class AuthController {
    public function login(): void { if(isset($_SESSION['user_id']) && AuthMiddleware::user())redirect('/dashboard'); View::render('auth/login',['title'=>'Sign in'],'auth'); }
    public function signIn(): void {
        $email=Input::email($_POST['email']??'');$password=$_POST['password']??'';
        if(!is_string($password)||$password==='')throw new InvalidArgumentException('Invalid credentials.');
        if(!(new AuthService(db()))->login($email,$password))throw new InvalidArgumentException('Invalid credentials or temporarily rate-limited.');
        redirect('/dashboard');
    }
    public function changePage(): void { $user=AuthMiddleware::requireLogin();View::render('auth/change',['title'=>'Change password','user'=>$user]); }
    public function change(): void {
        $user=AuthMiddleware::requireLogin();
        $old=$_POST['current_password']??'';$new=$_POST['new_password']??'';$repeat=$_POST['confirm_password']??'';
        if(!is_string($old)||!is_string($new)||!is_string($repeat)||$new!==$repeat)throw new InvalidArgumentException('Passwords do not match.');
        (new AuthService(db()))->changePassword((int)$user['id'],$old,$new);
        flash('Password updated.','success');redirect('/dashboard');
    }
    public function signOut(): void {AuthService::logout();redirect('/login');}
}
