<?php
declare(strict_types=1);

// Dependency-free checks; these are NOT a substitute for MySQL integration tests.
putenv('APP_URL=http://127.0.0.1:8095');
putenv('APP_KEY=' . str_repeat('a1', 32));
require dirname(__DIR__) . '/bootstrap/app.php';
ob_start(); // Keep HTTP headers mutable until Router status checks finish.

use Prospak\Core\Clock;
use Prospak\Core\Config;
use Prospak\Core\Csrf;
use Prospak\Core\Env;
use Prospak\Core\Router;
use Prospak\Core\Validation;
use Prospak\Core\View;

$checks = 0;
function check(bool $assertion, string $name): void
{
    global $checks;
    ++$checks;
    if (!$assertion) {
        fwrite(STDERR, "FAIL $name\n");
        exit(1);
    }
    echo "PASS $name\n";
}

check(Config::url('/login') === '/login', 'URL paths');
check(strlen(Config::appKey()) === 32, 'APP_KEY is 32 bytes');
check(Validation::email('  ADMIN@EXAMPLE.COM  ') === 'admin@example.com', 'email normalization');
check(Validation::email('not-email') === null, 'invalid email rejected');
check(Validation::email(['admin@example.com']) === null, 'array input rejected');
check(!Validation::password('short'), 'short password rejected');
check(Validation::password(str_repeat('a', 12)), 'minimum password accepted');
check(Validation::password(str_repeat('a', 72)), '72-byte password accepted');
check(!Validation::password(str_repeat('a', 73)), 'bcrypt truncation prevented');
check(Validation::name('<Person>') === '<Person>', 'validated values are not incorrectly HTML-encoded');
check(Validation::name("bad\nname") === null, 'control character name rejected');
check(View::escape('<script>') === '&lt;script&gt;', 'HTML escaping');
check(View::escape('"\'') === '&quot;&#039;', 'HTML attribute quotes escaped');
check(Clock::display('2026-11-03 13:00:00') === '03 Nov 2026, 2:00 PM WAT', 'UTC to Lagos timezone');
$bcrypt = password_hash('AnExampleStrongPassword123!', PASSWORD_BCRYPT, ['cost' => 12]);
check(password_verify('AnExampleStrongPassword123!', $bcrypt), 'bcrypt verification');
check(!password_verify('incorrectPassword', $bcrypt), 'bcrypt rejects incorrect password');

$_SESSION = [];
$token = Csrf::token();
check(strlen($token) === 64 && Csrf::check($token), 'CSRF token generated and validated');
check(!Csrf::check('wrong') && !Csrf::check(['nested']), 'invalid CSRF rejected');
check(Csrf::token() === $token, 'CSRF stable within session');

$temp = tempnam(sys_get_temp_dir(), 'prospak_test_');
file_put_contents($temp, "PROSPAK_UNIT_TEST_VALUE=hello\n#ignored\n");
Env::load($temp);
unlink($temp);
check(Config::get('PROSPAK_UNIT_TEST_VALUE') === 'hello', 'environment parser');

$router = new Router();
$router->get('/test-route', static fn () => print('FOUND'));
ob_start();
$router->dispatch('GET', '/test-route?hello=world');
$output = ob_get_clean();
check($output === 'FOUND', 'GET dispatch parses path');

http_response_code(200);
ob_start();
$router->dispatch('GET', '/no-such-route');
$out = ob_get_clean();
check($out === 'Not Found' && http_response_code() === 404, 'unknown route returns 404');

http_response_code(200);
ob_start();
$router->dispatch('POST', '/test-route');
$out = ob_get_clean();
check($out === 'Method Not Allowed' && http_response_code() === 405, 'unsupported route method returns 405');

http_response_code(200);
$_SERVER['REQUEST_URI'] = '/dashboard';
ob_start();
View::render('portal/home', ['title' => 'Test', 'user' => [
    'full_name' => '<script>alert(1)</script>', 'role_slug' => 'student',
    'email' => 'test@example.org', 'account_status' => 'active',
]], 'portal');
$html = ob_get_clean();
check(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'portal escapes user-supplied content');
check(str_contains($html, 'Prospak Academy') && str_contains($html, 'Phase 3'), 'portal uses Prospak Academy/current phase copy');

echo "SUCCESS: $checks non-database unit checks passed.\n";
ob_end_flush();
