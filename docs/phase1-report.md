# Prospak Academy — Phase 1 Implementation Report

**Phase:** 1 — Project Foundation and Authentication
**Implementation state:** Code and documentation prepared.
**User acceptance:** **PENDING** — local Ubuntu/MySQL/browser manual testing and explicit confirmation required.
**Next phase:** 2 — User, Student and Lecturer Management (**not started**).

## Implemented within Phase 1 only

- New Git repository from a Phase 0 planning baseline and focused Phase 1 feature branch.
- Directory separation for `public/`, internal `app/`, `config/`, `templates/`, `routes/`, `storage/` and `database/`.
- Method-aware router, front controller, centralized error responses, secure headers and Apache example.
- PDO MySQL connector with UTF-8 (`utf8mb4`), native prepared statements, exception handling and UTC session timezone.
- Seven Phase 1-only InnoDB tables, role/permission seed data and reproducible `database/student_portal.sql`.
- One-time CLI first administrator bootstrap with a hidden password prompt and bcrypt hashing.
- Login/logout, secure PHP sessions, CSRF on every POST, generic login errors and rate limiting.
- Authentication and server-side permission guards; admin-only RBAC checkpoint; status/version checks.
- Transactional change-password and reset-password flows; short-lived SHA-256-hashed single-use reset tokens, no token values logged.
- Mail delivery integration via native PHP `mail()` (disabled by default until an operator configures the host mail transport).
- Adapted sign-in and reset forms, responsive portal shell and reusable components using selected Hope UI v5.0.0 CSS/JS/assets.
- Test scripts, read-only database checks, comprehensive manual test guide and installation documentation.

**Not implemented:** Public account registration (intentionally prohibited); student or lecturer management screens; departments, programmes, courses, score entry, results, GPA, dashboards with real school metrics, notifications, reports or transcripts.

## Files created or changed

**Core:** `bootstrap/app.php`, `app/Core/{Env,Config,Database,Session,Csrf,Validation,View,Router,Guard,Clock}.php`
**Authentication:** `app/Services/AuthService.php`, `app/Repositories/UsersRepository.php`, `app/Controllers/{AuthController,PortalController}.php`
**Routes/public:** `routes/{auth,web}.php`, `public/{index,router}.php`, selected `public/assets/` Hope UI files and `public/assets/{css/app.css,js/reset-token.js}`
**Presentation:** `templates/layouts/{auth,portal}.php`, `templates/components/{head,sidebar,header,footer,alerts}.php`, `templates/auth/{login,forgot,reset,change}.php`, `templates/portal/{home,security}.php`
**Database:** `database/migrations/0001_phase1_identity.sql`, `database/student_portal.sql`, `database/seeders/README.md`
**CLI:** `scripts/{create-admin,prune-security}.php`
**Quality/docs:** `tests/{phase1_unit.php,static_checks.py}`, `docs/{phase0-decisions,phase1-testing,phase1-report}.md`, `README.md`, `.env.example`, `.gitignore`, `deploy/prospak-academy.conf`.

## SQL schema changes

**Planned Phase 1 schema implemented as files; not executed on an actual MySQL server in this workspace.**

- `roles`: unique role slug.
- `permissions`: unique permission slug.
- `role_permissions`: composite primary key and both foreign keys.
- `users`: unique email, role FK, bcrypt hash, active/suspended status, session version, UTC timestamps.
- `password_reset_tokens`: selector uniqueness, token hash, TTL and FK to users.
- `login_attempts`: keyed hashes of identity and IP, event type and expiry indexes.
- `audit_logs`: actor/subject relationships and private authentication event history.

Reference seeds: 4 roles, 3 permissions, 9 role-permission assignments. No user passwords or institutional/student records are seeded.

## Security measures

CSRF with random 32-byte token and `hash_equals`, POST-only state changes, cookie `HttpOnly`/`SameSite=Lax`/conditional `Secure`, session regeneration on authentication and idle timeout, bcrypt cost 12 with 12–72-byte password policy, generic invalid-credential response, keyed login-attempt fingerprinting and throttling, server-side RBAC checks, private credential configuration, contextual HTML escaping, prepared SQL, account status+session-version revalidation, private error logs without request bodies or tokens, password-reset token hashing and 30-minute expiry, and production HTTPS enforcement.

Some controls still require real hosted integration tests. The static presence of a guard is **not** evidence it works against MySQL and live sessions.

## Tests actually performed and observed results

1. PHP syntax checks across the changed PHP application/test files: **passed** (`php -l`).
2. `php tests/phase1_unit.php`: **25 assertions passed**, including CSRF, input validation, bcrypt, HTML escaping, route 404/405, timezone conversion and template safety (no DB access).
3. `python3 tests/static_checks.py`: **passed**, with seven expected Phase 1 table definitions and reference seed audit (static SQL/file inspection only).
4. `node --check public/assets/js/reset-token.js`: **passed**, JavaScript syntax only.
5. PHP built-in HTTP smoke tests: **passed** for GET `/login` 200, `/forgot-password` 200, `/register` 404, unauthenticated `/dashboard` 303, GET `/logout` 405, POST `/login` missing CSRF 419, bad `/reset-password?selector` 400, valid-format selector page 200, selected static CSS/image/JS assets 200.
6. A login POST with valid CSRF and cookie under the test runtime: **503 Service temporarily unavailable**, as anticipated because the environment has **no `pdo_mysql` extension**. This is **not** a successful login/database test.
7. Git: new repository and local feature-branch commit(s) generated; no GitHub remote configured and **no push performed**.

## Tests not run / limitations

- Actual MySQL migrations, FK/index inspection, and transaction rollback against MySQL 8.x: **not run** (no MySQL server/client available here).
- Login success, password change/reset, account suspension, authorization with non-administrator records, and logout during a live authenticated session: **not run** (no PDO MySQL driver).
- Apache virtual-host operation on your Ubuntu host: **not run** (tested PHP's built-in server only).
- Native mail delivery: **not run**, `MAIL_ENABLED=false` by default; requires a real configured mail transport and mailbox check.
- Real-browser responsive review across devices: **not run** (responsive assets integrated but user verification required).
- No customer/institutional academic policies or student data incorporated in Phase 1.

## Manual test guide and user sign-off

Follow **`docs/phase1-testing.md`**, including terminal commands, test data, 16 numbered browser/security test cases, expected results, negative cases, SQL read-only checks, optional dev-only permission tests and troubleshooting.

After manual verification, the user must explicitly confirm that Phase 1 works before it can be marked completed. **Do not begin Phase 2 automatically.**

## Version control

Branch: `feature/phase-1-authentication`, branching from an initial `main` planning-baseline commit. The Phase 1 source, tests and testing guide have a focused feature commit. Read `git log --oneline --all --decorate` for the exact local commit identifiers. There is no GitHub remote or push in this environment. `.env` and logs are ignored by Git.
