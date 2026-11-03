# Prospak Academy — Phase 1 Manual Testing Guide

**Phase:** 1 — Project Foundation and Authentication
**Status:** Implementation supplied; awaiting the user's local Ubuntu/MySQL testing and approval.
**Scope restriction:** This guide tests only Phase 1. No student profiles, subjects/courses, grades, reports or actual role dashboards exist yet.

## A. Prerequisites

1. A fresh **Ubuntu** development host with Apache, PHP 8.x and MySQL 8.x. Verify `php -m` includes `PDO`, `pdo_mysql`, `fileinfo`, `openssl`, and `session`.
2. Unzip the Phase 1 project, copy it to `/var/www/prospak-academy`, set Apache `DocumentRoot` to `/var/www/prospak-academy/public`, and set `APP_URL=http://prospak.test` (or use PHP's development server and adjust `APP_URL` accordingly).
3. Copy `.env.example` to `.env`, generate a **unique** 64-character hexadecimal `APP_KEY`, and fill in **your own** DB username and password. Do not send secrets to ChatGPT.
4. Import `database/student_portal.sql` **once**, using a privileged MySQL operator on an empty test database. Verify seven tables were created.
5. Use `php scripts/create-admin.php --email=you@example.org --name='System Administrator'` in an interactive terminal to create your **own initial admin account**; the password is entered invisibly. Do not run this against production data.
6. A modern browser with cookies and JavaScript enabled. Access to your MySQL CLI or phpMyAdmin. A second private/incognito browser window is useful for checking session invalidation.
7. `MAIL_ENABLED=false` is the safe initial default. For the optional end-to-end password-reset delivery test, set up a working local PHP `mail()` transport first, test that it actually delivers mail, then set `MAIL_ENABLED=true` with your verified `MAIL_FROM`. Never share reset URLs/tokens.

### Install and check the runtime

```bash
sudo apt update
sudo apt install apache2 mysql-server php php-cli libapache2-mod-php php-mysql unzip git
php -v
php -m | grep -Ei 'PDO|pdo_mysql|fileinfo|openssl|session'
mysql --version
sudo systemctl status apache2 mysql --no-pager
cd /var/www/prospak-academy
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'  # copy result to APP_KEY in .env
```

Use the complete install and MySQL privilege instructions in `README.md`. If using PHP's built-in server, set `APP_URL=http://127.0.0.1:8000` and run `php -S 127.0.0.1:8000 -t public public/router.php` in the project directory; replace `http://prospak.test` in all tests below with `http://127.0.0.1:8000`.

**No actual user passwords or student data are shipped.** The only mandatory test account is the admin you create. Optional authorization testing may temporarily modify *test-only* permission records (see Test 12). Do not run modification tests against your real institution's database.

## B. SQL setup and read-only verification

Use the MySQL terminal (`sudo mysql`) or phpMyAdmin, select the `prospak_academy` database, and execute:

```sql
USE prospak_academy;
SHOW TABLES;
SELECT COUNT(*) AS role_count FROM roles;
SELECT COUNT(*) AS permission_count FROM permissions;
SELECT COUNT(*) AS role_permission_count FROM role_permissions;
SELECT r.slug AS role, p.slug AS permission
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
ORDER BY r.slug, p.slug;
SELECT COUNT(*) AS user_count FROM users;
SHOW CREATE TABLE users;
SHOW CREATE TABLE password_reset_tokens;
```

**Expected:** Seven tables. Exactly four roles, three permissions, and nine role-permission links (four roles each get two account permissions, with `admin.access` additionally granted to the administrator). Before first bootstrap, `users` has zero rows; immediately afterwards, one administrator. `users.email` is unique and `users.role_id` references `roles.id`.

## C. Numbered browser / security test cases

### Test 1 — PHP syntax and static checks

Run from project root:

```bash
find app bootstrap config routes templates public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/phase1_unit.php
python3 tests/static_checks.py
```

**Expected:** Each PHP file reports `No syntax errors detected`; the test script reports 25+ non-database assertions passing; static checks report seven Phase 1 tables. These tests **do not** prove actual MySQL execution.

### Test 2 — Home page and Hope UI asset loading

1. Open `http://prospak.test/` in your browser.
2. Expect a redirect to `/login` and a Prospak Academy-branded Hope UI sign-in page.
3. Open Browser Developer Tools → Network; reload.
4. Confirm `/assets/css/hope-ui.min.css`, `/assets/css/libs.min.css`, `/assets/css/app.css` and `/assets/images/auth-01.png` return `200` rather than `404`.
5. Resize the browser to desktop, tablet and narrow phone widths.

**Expected:** Clear readable form, no horizontal scroll in authentication, consistent styling, and no broken core assets. The sign-in screen must not show the original Hope UI social-login buttons or sign-up link.

### Test 3 — New first administrator (once only)

1. From your SSH terminal, run `php scripts/create-admin.php --email=<your-email> --name='System Administrator'` (substitute your own valid email; omit angle brackets in the command).
2. Enter a secure 12–72-byte password when prompted. **Do not paste it into chat or your Git repository.**
3. Expect `Administrator created.`
4. Execute:

```sql
SELECT u.id, u.full_name, u.email, u.account_status, r.slug AS role,
       LEFT(u.password_hash, 4) AS password_hash_prefix
FROM users u JOIN roles r ON r.id = u.role_id;
```

**Expected:** One active administrator; password hash prefix resembles `$2y$`, not plaintext. Running the bootstrap command a second time should refuse because users already exist. Bootstrap is explicitly **not** a general account-management feature.

### Test 4 — Valid login

1. Browse `http://prospak.test/login` in a normal browser session.
2. Enter the administrator email and password you just created.
3. Click **Sign in**.

**Expected:** Redirect to `/dashboard` showing your account name, role and email, plus a clear message that the academic modules are not yet implemented. No demo student counts or fake grades. Check database:

```sql
SELECT email, last_login_at, session_version FROM users;
SELECT event_type, occurred_at FROM audit_logs ORDER BY id DESC LIMIT 10;
```

`last_login_at` should have a UTC value and `auth.login` should appear in the audit log.

### Test 5 — Unauthenticated access control

1. Open a **new incognito window** without signing in.
2. Type `http://prospak.test/dashboard` directly.
3. Also open `http://prospak.test/change-password` and `/admin/security-check` directly.

**Expected:** All three protected paths redirect to `/login`; access cannot be gained by typing a URL manually. No private account details are displayed.

### Test 6 — Forbidden public registration and wrong methods

1. Open `http://prospak.test/register` and `http://prospak.test/signup`.
2. Open `http://prospak.test/logout` via browser GET (while signed in or out).
3. Open `http://prospak.test/not-a-route`.

**Expected:** Registration and unknown URLs return HTTP **404**; GET `/logout` returns HTTP **405** because only CSRF-protected POST logout is supported. The login page must clearly say that accounts are administrator-created.

### Test 7 — Invalid login inputs and generic error messages

1. Sign out if needed.
2. Submit an unknown but properly formatted email and wrong password.
3. Submit your actual administrator email and an incorrect password.
4. Try a malformed email and blank password.

**Expected:** Wrong credentials produce the same generic `Invalid email or password` message (HTTP 422) without exposing whether the email exists. Malformed/blank inputs are rejected by browser/server validation. No exception traces or SQL details are shown.

### Test 8 — Login throttling

1. Use a dedicated browser session and deliberately enter the wrong password for one email **five times** within 15 minutes (use an email that is not your real admin address if practical).
2. Submit again.

**Expected:** Too many attempts returns HTTP **429** with a generic cooldown message. Throttling is checked by hashed email and remote IP. You may wait 15 minutes or use an isolated test database to clear only the test attempt records. Do not delete real audit history. Verify aggregated database evidence:

```sql
SELECT attempt_type, COUNT(*) AS total FROM login_attempts GROUP BY attempt_type;
SELECT COUNT(*) AS recent_login_failures FROM login_attempts
WHERE attempt_type='login' AND occurred_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE;
```

**Important:** If your IP is rate-limited, that affects other sign-ins from the same IP. Avoid repeated guessing against a production installation.

### Test 9 — CSRF protection

1. Open the login form, then use browser Developer Tools to remove its hidden `_csrf` input temporarily and submit an otherwise valid form.
2. Repeat with `_csrf` set to a random invalid string.
3. On a signed-in session, do the same with the password-change form.

**Expected:** Every modified POST fails HTTP **419** and changes no data. Reload the page to obtain a valid form token. A valid login or password change must work when the original token is present.

### Test 10 — Logout and session invalidation

1. Log in successfully.
2. Click **Sign out** in the sidebar (this submits a POST).
3. Try browsing back to `/dashboard`, and also reload the previous dashboard URL.

**Expected:** Redirect to `/login` with a sign-out confirmation; protected pages no longer show the previous account when reloaded. The expired session cookie and server session are invalidated, and the browser must not treat a cached page as fresh protected content.

### Test 11 — Change password and reject invalid values

1. Log in and open `http://prospak.test/change-password`.
2. Test wrong current password, mismatched new password, new password shorter than 12 bytes, and a new password identical to the old one.
3. Confirm each attempt is rejected without changing the stored password.
4. Enter your real current password and a **different, valid** new password, then click **Update password**.
5. Log out; old password should fail, new password should succeed.
6. If you left a second session open in another window, reload it.

**Expected:** Only the valid change succeeds. Other sessions become invalid on their next protected request; the active session remains valid. Database `session_version` should increment by one after a successful change, and `auth.password_changed` appears in `audit_logs`.

### Test 12 — Administrator permission checkpoint (and optional 403 test)

1. Sign in as the administrator and open `http://prospak.test/admin/security-check`.
2. Expect an admin-only message: the checkpoint is accessible with `admin.access`.
3. In a **throwaway development DB only**, first read the existing role-permission relationship, then temporarily revoke only the administrator's `admin.access` permission:

```sql
-- DEVELOPMENT DATABASE ONLY. Verify row before deleting:
SELECT rp.role_id, rp.permission_id
FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE r.slug='administrator' AND p.slug='admin.access';

-- Remove only the verified one row in an isolated test database:
DELETE rp FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE r.slug='administrator' AND p.slug='admin.access';
```

4. Reload the admin checkpoint. **Expected:** HTTP **403 Forbidden** from the *server*, even when the URL is manually entered.
5. Restore the permission immediately:

```sql
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug='administrator' AND p.slug='admin.access';
```

6. Reload the page and confirm it works again. **Never remove permissions on a live production database to test this.** Alternatively, wait until Phase 2 creates a second non-admin account and verify that account receives HTTP 403 instead.

### Test 13 — Password reset request with mail disabled

1. Keep `MAIL_ENABLED=false` in `.env`.
2. Open `http://prospak.test/forgot-password`.
3. Submit a valid but unknown email, then an existing user email.

**Expected:** Both requests show the **same** generic confirmation without exposing account existence. No email is sent with mail disabled. Attempts are recorded only as keyed hashes, not plaintext email/IP. Check:

```sql
SELECT attempt_type, COUNT(*) FROM login_attempts GROUP BY attempt_type;
SELECT COUNT(*) AS reset_token_count FROM password_reset_tokens;
```

The `reset_token_count` should remain zero if there were no earlier delivered resets. Test malformed emails to confirm a safe validation message.

### Test 14 — End-to-end password reset (requires configured email transport)

**Only after verifying a real mail transport is operational.** Set `MAIL_ENABLED=true`, a valid sender address, and ensure the administrator's email is a mailbox you control.

1. Request a reset for the administrator's email.
2. Confirm the email actually arrives. Its link is valid for 30 minutes; the secret appears after `#` and should be removed from the visible address bar when the page loads.
3. Open the link in the browser. Set a new 12–72-byte password and submit.
4. Try opening the same reset link and using it again.
5. Check the old password no longer works and the new one does.
6. Check that existing sessions are invalidated because `session_version` increased.
7. Request another link, then deliberately let it expire (or, **on a test DB only**, set its `expires_at` earlier than `UTC_TIMESTAMP()`) and confirm reuse is rejected.

**Expected:** The first valid reset succeeds once, the second use fails safely, expired/incorrect tokens fail, tokens are removed after success, and the audit event `auth.password_reset` is recorded. SQL:

```sql
SELECT id, selector, expires_at FROM password_reset_tokens ORDER BY id DESC LIMIT 5;
SELECT event_type, occurred_at FROM audit_logs ORDER BY id DESC LIMIT 10;
SELECT email, session_version FROM users ORDER BY id;
```

Do not copy token links, hashes, passwords or mailbox contents into bug reports. If mail is unavailable, record this test as **blocked**, not passed.

### Test 15 — Database outage and recovery

**Do not interrupt a shared or production database.** On an isolated development host only, temporarily stop MySQL or configure an intentionally invalid test DB connection, then attempt login.

**Expected:** Safe **503 Service temporarily unavailable**, with no raw PDO error or credentials displayed. Restore the original connection; login should work again. Do not mark this passed if not actually tested.

### Test 16 — Suspended-account protection (development DB only)

1. Back up your test database. Inspect the specific admin row using a `SELECT` by email.
2. In an isolated development database, set only that account's `account_status='suspended'` and attempt login or access from an existing browser session.
3. **Expected:** Login is rejected generically; an existing session cannot reopen a protected page.
4. Restore the account to `active` immediately.

This tests the server's underlying account-state guard, not the Phase 2 account-management interface.

## D. Database verification queries (run after tests)

```sql
USE prospak_academy;
SHOW TABLES;
SELECT slug, display_name FROM roles ORDER BY id;
SELECT slug FROM permissions ORDER BY id;
SELECT r.slug AS role, p.slug AS permission
FROM role_permissions rp JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id ORDER BY role, permission;
SELECT id, full_name, email, account_status, session_version,
       created_at, last_login_at
FROM users ORDER BY id;
SELECT event_type, COUNT(*) AS events FROM audit_logs GROUP BY event_type;
SELECT attempt_type, COUNT(*) AS attempts FROM login_attempts GROUP BY attempt_type;
SELECT COUNT(*) AS outstanding_reset_tokens FROM password_reset_tokens;
SELECT table_name, engine
FROM information_schema.tables
WHERE table_schema='prospak_academy' ORDER BY table_name;
SELECT table_name, constraint_name, referenced_table_name
FROM information_schema.key_column_usage
WHERE table_schema='prospak_academy' AND referenced_table_name IS NOT NULL
ORDER BY table_name;
```

**Expected:** All seven tables use `InnoDB`, foreign keys exist, and the correct role/permission relationships remain restored after negative testing. Test users should have bcrypt hashes and only appropriate roles. Do not paste password hashes or personally identifiable user records into your report.

## E. Troubleshooting and reporting format

| Symptom | Likely cause | What to inspect safely |
|---|---|---|
| HTTP 500 on login page | Invalid/missing `APP_KEY` or `APP_URL`; PHP runtime error | Check `.env` format and `storage/logs/application.log` **event types**, never expose secrets |
| HTTP 503 on login POST | MySQL down, `pdo_mysql` absent, incorrect DB privileges/config | Run `php -m`, `mysql --version`, check MySQL service and PDO config |
| 404 for `/login` via Apache | Incorrect `DocumentRoot` or `FallbackResource` | `apache2ctl configtest`, virtual host config, `sites-enabled` |
| Hope UI unstyled | Wrong `/assets` prefix or missing copied assets | Browser Network tab; check stylesheet 200 responses |
| HTTP 419 | Missing/invalid form token, stale session cookie | Refresh form; confirm session cookies and `_csrf` hidden field |
| HTTP 429 | Too many recent failed logins | Wait for cooldown; inspect aggregated `login_attempts` |
| Admin bootstrap refused | Users table already has accounts, missing TTY or role seed | Run `SELECT COUNT(*)`, confirm terminal is interactive |
| No password reset email | `MAIL_ENABLED=false`, no local mail transport, sender rejected | Inspect mail configuration without publishing reset tokens; test mailbox receipt |
| Password reset rejects link | Link expired/used, fragment missing, JavaScript disabled | Re-open *original* link, request a new link, check browser JS errors |
| HTTP 403 on admin checkpoint | `admin.access` not assigned to account role | Check `role_permissions` join; undo optional negative-test deletion |
| Session remains authenticated | Browser cache/state or session not invalidated | Reload protected route, verify HTTP status, compare `session_version` |
| Database permissions error during import | Using low-privilege app user for DDL | Apply migration using a privileged MySQL operator |

When reporting an issue, provide **test number, expected result, actual result, URL path (without reset secrets), HTTP status code, relevant safe PHP version, and a sanitized error description**. Never send passwords, `.env`, real user details, session IDs, cookies or reset tokens.

## F. Phase sign-off

Use this feedback format after performing the tests:

```text
Prospak Academy — Phase 1 Testing
Ubuntu version:
PHP version:
MySQL version:
Apache version:
Passed tests: [numbers]
Failed tests: [numbers and concise sanitized errors]
Blocked/not executed: [numbers and why]
Any required bug fixes:
I confirm whether Phase 1 is accepted: YES / NO
```

Phase 1 is **not officially completed** until you confirm it works. No Phase 2 implementation should start automatically.
