# Prospak Academy — Student Portal & Grade Management System

**Project status:** Phase 0 accepted by the user. **Phase 1 — Project Foundation and Authentication** implemented on `feature/phase-1-authentication` and awaiting the user's Ubuntu/MySQL manual verification and approval. **Do not begin Phase 2 without explicit approval.**

## Approved project decisions

- Institution: **Prospak Academy**; single institution.
- Student and lecturer accounts are **administrator-created**, not self-registered. `/register` is intentionally unavailable.
- Four roles: administrator, academic officer, lecturer and student.
- 14-phase approved roadmap, with user testing and sign-off between phases.
- Do not invent academic/grading policies or add modules outside the currently approved phase.

## Technologies

PHP 8.x (tested here with PHP 8.4.24), MySQL 8.x with InnoDB, native PDO prepared statements, Apache on Ubuntu, Bootstrap 5, HTML5/CSS3, vanilla JavaScript and selected **Hope UI v5.0.0** distributed assets. No Laravel, no Composer dependencies in Phase 1. Chart.js and Dompdf are reserved for their future approved phases.

### Phase 1 features

- Project bootstrap, simple method+path router, front controller and configurable Apache virtual host.
- Secure central MySQL PDO connection (`utf8mb4`, exception mode, native prepares, UTC SQL session).
- Seven Phase-1-only relational tables and the incremental SQL migration plus cumulative import file.
- Role/permission foundation: 4 roles; `dashboard.view`, `auth.change_password`, `admin.access` permissions; `admin.access` belongs only to administrators.
- Administrator CLI first-account bootstrap (TTY password prompt, no hard-coded credentials, one-time only).
- Password login/logout and session management, idle timeout, session ID rotation after login, bcrypt, throttled login attempts, authentication and permission guards.
- CSRF tokens validated on **all POST routes**, including login, password reset, change password and logout.
- User-safe form validation, output escaping, no untrusted SQL interpolation, private audit events and generic error responses.
- Password change with existing password verification, transaction and session-version invalidation.
- Password recovery with generic response and per-account/IP rate limiting. When `MAIL_ENABLED=true` and PHP `mail()` is configured, links expire after 30 minutes, use single-use hashed tokens, and put the secret in a URL **fragment**, not in server access logs. When email delivery is disabled, requests are accepted generically but **no link is sent**.
- Prospak-branded Hope UI sign-in/recovery views and basic account landing page (no pretend statistics, student records or courses).
- Admin-only checkpoint `/admin/security-check` to exercise the RBAC foundation; no user-management interface in this phase.

**Not yet in Phase 1:** User management UI and student/lecturer profiles (Phase 2); departments/programmes (Phase 3); courses and assessments (Phases 4–8); actual role dashboards (Phase 9); notifications, reports or transcripts (Phases 10–12).

## Directory structure

```text
app/Controllers/           HTTP controllers
app/Core/                  Configuration, router, PDO, sessions, CSRF, guards, validation, views
app/Repositories/          User and permission queries
app/Services/              Authentication business logic
bootstrap/app.php          Autoloader and UTC bootstrap
config/                    Application/DB configuration reference
routes/                    Authentication and public/portal routes
templates/layouts/         Shared Hope UI layouts
templates/components/      Header, sidebar, footer, alert, head components
templates/auth/            Auth forms
templates/portal/          Phase-1-only account pages
public/index.php           Only HTTP front controller
public/router.php          Local PHP built-in development router
public/assets/            Curated Hope UI CSS/JS/image assets + minimal app additions
database/migrations/       Versioned SQL changes
database/student_portal.sql Reproducible cumulative schema as of Phase 1
database/seeders/          Reference data policy
storage/logs/              Private logs (never expose through Apache)
storage/uploads/           Private uploads reserved for Phase 2
scripts/                   Interactive first admin and housekeeping commands
tests/                     Unit/static test scripts
deploy/                    Apache vhost example
docs/                      Decisions, test guide and phase completion report
```

`public/` **must be the Apache DocumentRoot**. Do not point Apache at the project root. Never put `.env`, raw student records, database exports, or writable uploads into `public/`.

## Requirements for Ubuntu

```bash
sudo apt update
sudo apt install apache2 mysql-server php php-cli libapache2-mod-php php-mysql php-common unzip git
php -v
php -m | grep -Ei 'PDO|pdo_mysql|fileinfo|openssl|session'
mysql --version
```

PHP must have **pdo_mysql**; in the inspection workspace this driver was absent, so database integration was NOT executed there. Verify PHP's CLI and Apache runtime are using compatible modules (`php -i`, `phpinfo` only on a **temporary access-restricted page** if truly necessary; remove it after). In production, HTTPS and appropriate OS/package updates are required.

## 1. Install the source

Unzip the provided `prospak-academy-phase1.zip` somewhere under your home folder, then install into your chosen path. This guide uses `/var/www/prospak-academy`:

```bash
sudo mkdir -p /var/www/prospak-academy
sudo cp -a /path/to/unzipped/prospak-academy/. /var/www/prospak-academy/
cd /var/www/prospak-academy
sudo cp .env.example .env
sudo nano .env
```

Set `APP_URL=http://prospak.test`, generate and insert **your own** `APP_KEY`, and set the database account/password. Do **not** copy any test key or paste real secrets into chat or Git.

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Set `.env` permissions appropriate to your Apache PHP user (typically `www-data`):

```bash
sudo chown root:www-data .env
sudo chmod 640 .env
sudo chown -R www-data:www-data storage/logs storage/uploads
sudo chmod 750 storage/logs storage/uploads
```

Never commit `.env`. The supplied `.env.example` has placeholders only.

## 2. Provision MySQL 8.x

**Only on a fresh development database**: log in as your privileged MySQL administrator (not as the low-privilege web-app account):

```bash
sudo mysql
```

Then enter SQL interactively (replace the example password in your *terminal*, do not store it in the project):

```sql
CREATE DATABASE IF NOT EXISTS prospak_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'prospak_app'@'127.0.0.1' IDENTIFIED BY 'CHOOSE_A_PRIVATE_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON prospak_academy.* TO 'prospak_app'@'127.0.0.1';
EXIT;
```

If you use a different database host, adapt the MySQL account host accordingly. The web application does **not** need schema-modification permissions. Set the chosen password in `.env` (`DB_HOST=127.0.0.1`, `DB_USERNAME=prospak_app`). Import **once** as a privileged database user:

```bash
cd /var/www/prospak-academy
sudo mysql < database/student_portal.sql
sudo mysql -e 'USE prospak_academy; SHOW TABLES;'
```

Do not import `database/student_portal.sql` and `database/migrations/0001_phase1_identity.sql` into the *same* already-initialised database: they contain the same Phase 1 tables. If applying the migration into an empty, pre-created database instead, use `sudo mysql prospak_academy < database/migrations/0001_phase1_identity.sql`.

### Database tables in this phase

`roles`, `permissions`, `role_permissions`, `users`, `password_reset_tokens`, `login_attempts`, `audit_logs` (all InnoDB with FK constraints). Role and permission seed values are **reference configuration only**, not institutional student or lecturer data. No default user or password is shipped. The SQL export is cumulative **through Phase 1 only**; future phases will extend it after separate approval.

## 3. Bootstrap the first administrator

From an **interactive terminal** on the server after creating/importing the database:

```bash
cd /var/www/prospak-academy
php scripts/create-admin.php --email=admin@your-real-institution-domain.example --name='System Administrator'
```

Replace the example with a real, private email address you control. The command will securely prompt for a password on the terminal; type a unique 12–72-byte password. The password is not accepted on the command line, is not printed, and is stored only as a bcrypt hash. The command refuses to create accounts once **any** user exists. **All subsequent student/lecturer account creation is reserved for Phase 2 administrator management.**

## 4. Set up Apache

```bash
sudo cp deploy/prospak-academy.conf /etc/apache2/sites-available/prospak-academy.conf
sudo a2ensite prospak-academy.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Check that the example site file's `DocumentRoot` matches your installed path. For local testing, add `127.0.0.1 prospak.test` to `/etc/hosts`. Open **http://prospak.test/login**. For production use a real domain, valid TLS certificate and HTTPS; set `APP_ENV=production` and `APP_URL=https://your-domain`. The production application intentionally rejects plain-HTTP requests.

For quick **development only**, you may instead use:

```bash
cd /var/www/prospak-academy
# Set APP_URL=http://127.0.0.1:8000 in .env for this approach.
php -S 127.0.0.1:8000 -t public public/router.php
```

Visit `http://127.0.0.1:8000/login`. Do not use the PHP built-in server in production.

## 5. Authentication routes

| Method | Route | Access |
|---|---|---|
| GET/POST | `/login` | Public; login POST requires CSRF |
| POST | `/logout` | CSRF-protected; normally signed-in |
| GET/POST | `/forgot-password` | Public; rate limited; email delivery optional |
| GET/POST | `/reset-password` | Public; time-limited emailed token, CSRF on POST |
| GET/POST | `/change-password` | Signed-in user with `auth.change_password` |
| GET | `/dashboard` | Signed-in user with `dashboard.view`; Phase 1 landing only |
| GET | `/admin/security-check` | `admin.access` (administrator only) |
| GET | `/` | Redirect to `/login` |
| any | `/register` | Not implemented; 404 |

Roles and permissions are verified against the database on each new request; hiding a navigation link is not authorization. Account suspension or password change invalidates existing sessions on subsequent requests.

## 6. Email/password reset configuration

By default `MAIL_ENABLED=false` because no mail service was supplied or inspected. The recovery page returns a non-enumerating message but cannot deliver links until you set up your own sending transport.

To enable email recovery, provision and **test** a local sendmail-compatible mail transport (such as a correctly configured Postfix relay), then set `MAIL_ENABLED=true` and `MAIL_FROM=...` to a verified sender. Test actual receipt at the configured user address. Do not configure an open relay. The application cannot promise email delivery simply because `mail()` returned true; verify mailbox receipt. Password-reset link URLs use `?selector=<public identifier>#token=<secret>`, and the JS moves the fragment secret to a POST-only hidden field. The secret is hashed in MySQL; plain tokens must not be stored in logs.

## 7. Verification and development workflow

```bash
cd /var/www/prospak-academy
find app bootstrap config routes templates public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/phase1_unit.php
python3 tests/static_checks.py
# Browser and database testing are documented in docs/phase1-testing.md
```

The static checks require Python 3. JS syntax was checked using `node --check` in the inspection environment (Node is not required to run the application). `tests/phase1_unit.php` does **not** use a MySQL database and is not evidence that login/queries work.

For read-only DB verification:

```sql
USE prospak_academy;
SHOW TABLES;
SELECT slug, display_name FROM roles ORDER BY id;
SELECT slug FROM permissions ORDER BY id;
SELECT r.slug, p.slug FROM role_permissions rp
 JOIN roles r ON r.id = rp.role_id
 JOIN permissions p ON p.id = rp.permission_id
 ORDER BY r.slug, p.slug;
SHOW CREATE TABLE users;
```

Follow **[Phase 1 Testing Guide](docs/phase1-testing.md)** for manual login, logout, RBAC, CSRF, throttling, reset/change password, invalid input, session invalidation, audit records and troubleshooting.

## 8. Timestamp policy

MySQL `DATETIME(6)` fields contain UTC values, with the PDO connection explicitly setting `time_zone='+00:00'`. PHP uses UTC internally. Display human-readable dates with `Prospak\Core\Clock::display()` to convert to `Africa/Lagos` (WAT, UTC+01:00). Avoid making the server's local timezone determine stored timestamps.

## 9. Security and operational cautions

- Never expose the root project folder, `.env`, logs or private uploads through Apache.
- Keep production HTTPS mandatory, with `Secure`, `HttpOnly`, `SameSite=Lax` session cookies.
- CSRF uses unpredictable tokens and `hash_equals` on POST.
- Passwords use `PASSWORD_BCRYPT` with cost 12, 12–72 bytes. No public registration or default password.
- Failed-login attempts have limits per identity and IP; all stored fingerprints use a keyed HMAC rather than raw IP/email. Periodically prune old attempts and expired tokens:
  ```bash
  php scripts/prune-security.php
  ```
- Store diagnostic event **types/codes only** in private `storage/logs/application.log`; server access/error log retention and redaction remain operational responsibilities.
- Protect SQL dumps and backups (particularly once real student data exists). Test restoring backups before risky future migrations.
- Web app DB account has only SELECT/INSERT/UPDATE/DELETE; schema changes use an operator account and versioned migrations.
- Route authorization is required in code, not merely UI. Future student result access will need **record ownership** guards, not yet tested or implemented.
- Monitor failed login traffic and audit logs; protect database and mail service availability.

## 10. Git workflow

New repository initiated from the Phase 0 approved baseline with local Git history:

- `main`: stable initial planning baseline.
- `feature/phase-1-authentication`: Phase 1 implementation and test/doc changes.

`git status`, `git log --oneline --all --decorate` and `git diff main...feature/phase-1-authentication` are recommended for review. There is no configured GitHub remote in the provided environment; **nothing has been pushed**. When the user supplies a repository URL and authorises a push, connect and push the feature branch without force-pushing.

## 11. Known limitations and next development gate

The source workspace had PHP CLI 8.4.24 but **no MySQL server/client and no `pdo_mysql` extension**, so MySQL migration execution, real login, reset, password changes, RBAC backed by database, and Ubuntu Apache browser tests remain **unverified** here. Apache 2.4.68 was installed but the application was smoke-tested with PHP's built-in server. Actual email delivery remains unverified and disabled by default. No actual user accounts, grades or student data are included. Phase 1 is **awaiting your manual acceptance**. Do not start Phase 2 until the user confirms its tests passed.
