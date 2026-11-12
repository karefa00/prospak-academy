<?php
declare(strict_types=1);
function start_app_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '7200');
    if (is_dir(PROJECT_ROOT . '/storage/sessions') && is_writable(PROJECT_ROOT . '/storage/sessions')) {
        session_save_path(PROJECT_ROOT . '/storage/sessions');
    }
    $secure = env_setting('SESSION_SECURE', '0') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('PROSPAKSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}
