<?php
declare(strict_types=1);

namespace Prospak\Controllers;

use Prospak\Core\Config;
use Prospak\Core\Guard;
use Prospak\Core\Session;
use Prospak\Core\Validation;
use Prospak\Core\View;
use Prospak\Services\AuthService;

final class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function showLogin(): void
    {
        if ($this->auth->user() !== null) {
            self::redirect('/dashboard');
        }
        View::render('auth/login', ['title' => 'Sign in', 'flash' => Session::pullFlash()]);
    }

    public function login(): void
    {
        $email = Validation::email($_POST['email'] ?? null);
        $password = $_POST['password'] ?? null;
        if ($email === null || !is_string($password) || strlen($password) > 1024) {
            http_response_code(422);
            View::render('auth/login', ['title' => 'Sign in', 'error' => 'Enter a valid email address and password.', 'oldEmail' => is_string($_POST['email'] ?? null) ? substr((string) $_POST['email'], 0, 254) : '']);
            return;
        }
        $result = $this->auth->login($email, $password, (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($result === 'ok') {
            self::redirect('/dashboard');
        }
        http_response_code($result === 'throttled' ? 429 : 422);
        View::render('auth/login', ['title' => 'Sign in', 'error' => $result === 'throttled'
            ? 'Too many attempts. Please try again later.' : 'Invalid email or password.', 'oldEmail' => $email]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        Session::start();
        Session::flash('success', 'You have been signed out.');
        self::redirect('/login');
    }

    public function showForgot(): void
    {
        View::render('auth/forgot', ['title' => 'Recover your password', 'flash' => Session::pullFlash()]);
    }

    public function forgot(): void
    {
        $email = Validation::email($_POST['email'] ?? null);
        if ($email === null) {
            http_response_code(422);
            View::render('auth/forgot', ['title' => 'Recover your password', 'error' => 'Enter a valid email address.']);
            return;
        }
        $this->auth->requestPasswordReset($email, (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        Session::flash('success', 'If this email belongs to an active account and email delivery is configured, a reset link will be sent.');
        self::redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $selector = $_GET['selector'] ?? '';
        if (!is_string($selector) || !preg_match('/^[0-9a-f]{32}$/D', $selector)) {
            http_response_code(400);
            View::render('auth/reset', ['title' => 'Set a new password', 'error' => 'The reset link is invalid.', 'selector' => '']);
            return;
        }
        View::render('auth/reset', ['title' => 'Set a new password', 'selector' => $selector]);
    }

    public function reset(): void
    {
        $selector = $_POST['selector'] ?? '';
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;
        if (!is_string($password) || !Validation::password($password) || !is_string($confirmation) || !hash_equals($password, $confirmation)) {
            http_response_code(422);
            View::render('auth/reset', ['title' => 'Set a new password', 'selector' => is_string($selector) && preg_match('/^[0-9a-f]{32}$/D', $selector) ? $selector : '', 'error' => 'Passwords must match and be between 12 and 72 bytes. Reopen the original reset link if you need to try again.']);
            return;
        }
        if (!is_string($selector) || !is_string($token) || !$this->auth->resetPassword($selector, $token, $password)) {
            http_response_code(422);
            View::render('auth/reset', ['title' => 'Set a new password', 'selector' => '', 'error' => 'This link is expired or invalid. Request a new link.']);
            return;
        }
        Session::flash('success', 'Your password has been reset. Please sign in.');
        self::redirect('/login');
    }

    public function showChange(): void
    {
        $user = Guard::requirePermission($this->auth, 'auth.change_password');
        View::render('auth/change', ['title' => 'Change password', 'user' => $user, 'flash' => Session::pullFlash()], 'portal');
    }

    public function change(): void
    {
        $user = Guard::requirePermission($this->auth, 'auth.change_password');
        $current = $_POST['current_password'] ?? null;
        $new = $_POST['new_password'] ?? null;
        $confirm = $_POST['new_password_confirmation'] ?? null;
        if (!is_string($current) || !is_string($new) || !is_string($confirm) || !Validation::password($new) || !hash_equals($new, $confirm)) {
            http_response_code(422);
            View::render('auth/change', ['title' => 'Change password', 'user' => $user, 'error' => 'Provide your current password and a matching new password of 12–72 bytes.'], 'portal');
            return;
        }
        if (!$this->auth->changePassword((int) $user['id'], $current, $new)) {
            http_response_code(422);
            View::render('auth/change', ['title' => 'Change password', 'user' => $user, 'error' => 'Current password was incorrect, or the new password matches the old password.'], 'portal');
            return;
        }
        Session::flash('success', 'Password changed. Other sessions will require sign-in again.');
        self::redirect('/change-password');
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . Config::url($path), true, 303);
        exit;
    }
}
