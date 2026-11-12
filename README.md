# Prospak Academy — Student Portal & Grade Management System

**Purpose:** a focused PHP/MySQL assessment application demonstrating seven required professional development skills, not a full school ERP.

## Scope retained / deliberately removed

**Retained:** institution-managed account registration and login; administrator, academic officer, lecturer and student roles; protected student profiles and passport uploads; academic references (faculties, departments, programmes, levels, sessions and semesters); course catalogue; lecturer assignment; course registration; CA and examination score entry; approved and published results; student GPA/CGPA; and compact database-driven dashboards.

**Removed from the new application:** announcements and notification inboxes, transcript request workflows, broad report/PDF exports, dashboard security-demo pages, inventory, fees, attendance, CBT, demo presentation decks and phase-specific UI controllers. There are **no Composer dependencies, vendor folder, autoloader, reload scripts, or extra top-level folders**. The recommended `NotificationService.php`, `composer.json`, `composer.lock`, `vendor/` and `bootstrap/autoload.php` from the proposed example are intentionally omitted because they conflict with the final remove-unrelated-features/no-Composer instruction.

## Requirements traceability

| Requirement | Evidence to demonstrate |
|---|---|
| Architecture | `public/` document root; explicitly included modules in `bootstrap/app.php`; reused `includes/` and `templates/layouts/` for all portal pages |
| PDO MySQL | `config/database.php` with `utf8mb4`, `PDO::ERRMODE_EXCEPTION`, native prepared statements; complete fresh-install `database/schema/prospak_academy.sql` |
| LIMIT/OFFSET | `PAGE_SIZE = 10` in `config/constants.php`; parameterized pagination in the repositories and grade/registration lists |
| Validation & security | `src/Validators/`, `e()` wrapping `htmlspecialchars()`, `finfo` upload checks, CSRF middleware for all POST requests and transactions around multi-table writes |
| Authentication & access | Bcrypt `password_hash()` + `password_verify()`, secure session cookies and renewal, login throttling, `AuthMiddleware` / `RoleMiddleware`, account and resource checks |
| Hope UI / dynamic views / time | Retained original Hope UI styles under `public/assets/vendor/` and rebuilt templates; live PDO counts; UTC MySQL connection, `wat_time()` converting to `Africa/Lagos` |
| Git / GitHub | Use the documented feature branch, commit and push commands. **This ZIP does not contain `.git` history and has not been pushed to your GitHub account.** |

## Software requirements

- PHP 8.1+ with `pdo_mysql`, `fileinfo`, and sessions enabled (PHP 8.2+ recommended).
- MySQL 8.x, Apache with `mod_rewrite`, or PHP's built-in development server.
- No Composer, frameworks, or JavaScript build tools.

## Install on a new empty database

1. Extract this ZIP as `Prospak-Academy` and enter the project root.
2. Copy `.env.example` to `.env` (`cp .env.example .env`); change database host/user/password, set a **random** APP_KEY, and never commit `.env`.
3. Recommended least-privilege database account: provision a dedicated user with SELECT/INSERT/UPDATE/DELETE on `prospak_academy.*` (and separate migration credentials for DDL). Avoid MySQL `root` for the app.
4. For a **new database only**: `sudo mysql < database/schema/prospak_academy.sql`. This contains the schema and role/permission seed data, not real school records.
5. For fictional academic reference data on an empty database only: `sudo mysql < database/seeders/academic_demo.sql`. This is optional; otherwise create faculties/departments/programmes/levels in the admin portal.
6. From the project root: `php database/seeders/create_admin.php admin@example.test 'Prospak Administrator'`. This creates a bcrypt-hashed administrator account and outputs a random temporary password **once**. Store it securely and change at first login.
7. Set directory permissions for PHP to write to `storage/sessions`, `storage/logs` and `storage/private-uploads` without making them web accessible. Use HTTPS and `SESSION_SECURE=1` for production.
8. Start development server: `php -S 127.0.0.1:8000 -t public public/index.php`. Open http://127.0.0.1:8000/login. For Apache, point **DocumentRoot** at `Prospak-Academy/public` and enable `mod_rewrite`/`.htaccess`.

**APP_KEY example:** `python3 -c "import secrets; print(secrets.token_hex(32))"` — paste its 64 hexadecimal characters into `.env`.

## Existing tested Phase 13 installations — preserve data

**Do not import the clean SQL dump or replay 0001–0008 migrations against an existing populated `prospak_academy` database.** The refactored PHP code intentionally reuses the **existing Phase 1–8 table and column names** (including the Phase 7 `credit_units_snapshot`). Back up the **full** existing database and uploaded photos, extract the new code to a **separate directory**, and preserve your own `.env`. Start the new app against a database *copy*, and verify all four roles before switching environments. The old database may still contain legacy tables for announcements, transcripts, etc.; these are **not used** by the new app and are not dropped automatically, to avoid data loss. The new clean export does not create those modules.

If your old students have photo filenames pointing to the previous `storage/uploads` folder, copy/rename them to the new private upload conventions **only after mapping and reviewing their metadata**; this application deliberately does not expose legacy arbitrary filenames.

## Administrator setup order

Create the faculty, department, programme and academic level (if not already seeded), create an academic session and its first/second semester, activate the session and desired semester, create courses and assign them to a programme and level, register lecturer/students, assign lecturer offerings, activate a grading example after confirming institutional policy, set CA/Exam max scores for each offering, and test student enrolment.

### Four-role workflow

1. **Administrator:** creates staff/students, configures academics, creates course and grading scheme, assigns lecturers, then publishes academically approved results.
2. **Student:** signs in, changes first-login password, registers eligible courses in the active semester, and later sees only their own published results.
3. **Lecturer:** opens assigned offerings, enters CA/exam values per student, and submits a complete course roster for review.
4. **Academic Officer:** reviews submitted results, approves or rejects with a reason. Rejected results can be corrected by the lecturer and resubmitted. Approved and published results are locked.

Result publication snapshots credit units; CGPA uses only published grades. When the same course has repeat results, CGPA remains withheld until an institutional repeat policy is configured. Dates are stored in UTC (set on the PDO connection), displayed in West Africa Time (`Africa/Lagos`). Calendar **DATE** fields retain their literal academic dates; registration deadlines are evaluated against current WAT date.

## Pagination and uploads

Student, user, course, enrollment, result-batch and gradebook listing pages use LIMIT/OFFSET with `10` rows per page. Passwords use bcrypt; no passwords are committed as samples. Student passport photos are checked using `finfo`, restricted to JPEG/PNG/WebP and 2MB, stored at `storage/private-uploads/` and served via a server-side ownership/role check. The `public/uploads/` directory is denied to direct access and left unused.

## Tests

Run after each change:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/validators.php
php tests/Feature/structure.php
bash tests/Feature/http_smoke.sh
php tests/Integration/database_readonly.php
```

The integration script is **read-only** and requires a working `pdo_mysql`/MySQL connection. Browser acceptance tests must still include real account registration/login, invalid password, role-restricted URLs, invalid CSRF, MIME spoofing, pagination (create at least 11 students), registration timing, duplicate enrollment, score boundaries, approval/rejection, publication, and GPA. Never treat the PHP unit checks alone as proof of an end-to-end working deployment.

## Git workflow — assessment requirement 7

The ZIP intentionally excludes `.git` because you requested no other folders. In your own working copy:

```bash
git init
git add .gitignore .env.example README.md public src api process includes templates config database bootstrap storage tests
git commit -m "chore: establish simplified public-root PHP architecture"
git switch -c feature/authentication
# Make and test authentication improvements, then commit on that branch.
git add . && git commit -m "feat: tighten student portal authentication"
git switch -c feature/grade-management
# Make and test grade management improvements, then commit.
git add . && git commit -m "feat: build grade-management workflow"
# Set your own authorised GitHub remote, then push the feature branches:
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
git push -u origin feature/authentication
git push -u origin feature/grade-management
```

This is **illustrative history-building guidance, not evidence that the branches already exist**. For assessment integrity, commit actual incremental changes rather than creating fake history. Use GitHub repository settings to create a private repository if real student data is present; never push `.env` or uploaded files.

## Folder architecture

Only these top-level directories exist: `public/`, `src/`, `api/`, `process/`, `includes/`, `templates/`, `config/`, `database/`, `bootstrap/`, `storage/`, and `tests/`. No `app/`, `routes/`, `scripts/`, `docs/`, `deploy/`, `vendor/`, or runtime autoloader is included. Explicit `require_once __DIR__ . '/...'` in `bootstrap/app.php` handles class dependencies. The HTML, HTTP controllers, PDO repositories and reusable layouts are separated so responsibility and testability are clear.

## Limitations

This build uses explicit PDO prepared queries and the existing Phases 1–8 database structure. It does not automatically migrate legacy data, drop old production tables, grant MariaDB roles, create actual student accounts in a live DB, or claim browser tests passed without your environment. The initial login page has no self-signup because institutional staff control enrolment. First release does not include password-reset emails; administrators can provision temporary accounts manually. Do not enter real grades until you verify your institution's grading bands and repeat-course policy.

## CRUD completion update and administrator publication

The consolidated CRUD update adds safe edit/suspend/reactivation for staff accounts, edit/deactivation/reactivation for student accounts, and full edit/status controls for faculties, departments, programmes, levels, sessions and semesters. Courses can be edited, including programme associations and prerequisites, subject to cycle prevention and existing-registration protection. Lecturer assignments can be reassigned, deactivated or reactivated before scores or formal result processing exist. Course registration retains its existing add/drop/re-register history instead of allowing destructive deletion.

Administrator **Publish Results** is accessible in the sidebar and dashboard, with a separate `/results/publish` page listing **only approved result batches**. Publication remains guarded by `RoleMiddleware`, global CSRF verification, a database transaction and the existing `GradeService::publish()` state validation. Lecturers submit scores; academic officers approve/reject; students see only published grades.

All changes use existing Phase 1–8 tables: **no schema migration required**. Do not overwrite a live populated database with the clean-install SQL export. The administrative tables and lecturer assignments now include 10-record paginated listings. Full walkthrough, negative cases and SQL checks are in [`tests/Feature/crud-acceptance-guide.md`](tests/Feature/crud-acceptance-guide.md).

Run the added regression checks: `php tests/Unit/crud_validators.php`, `php tests/Feature/crud_routes.php`, and `php tests/Feature/crud_render.php`. The MySQL and four-role browser tests remain necessary on Ubuntu. The repository has no Composer dependencies or autoloader.
