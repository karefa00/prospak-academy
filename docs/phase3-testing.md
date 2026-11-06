# Prospak Academy — Phase 3 manual testing guide

**Phase:** 3 — Academic Organisation Management. **State:** code implemented, awaiting user-run Ubuntu/MySQL functional tests and acceptance. The package does not contain actual institution records, credentials, or secrets. Do not begin Phase 4 until Phase 3 is accepted.

## A. Prerequisites

1. A **working and accepted Phase 2** installation at `/var/www/prospak-academy` (adjust directory as needed) with PHP 8.x, PHP `pdo_mysql`, Apache or PHP built-in server, MySQL 8.x, an administrator, an academic officer, at least one sample student and one sample lecturer. Accounts must not be suspended; initial password changes must be completed.
2. This Phase 3 ZIP or its matching Git bundle and a current backup of the Phase 2 source **and** MySQL database. Never send `.env`, password hashes, real student data or reset links to this chat.
3. MySQL database name used in the example: `prospak_academy`. Confirm that it matches `.env`. Database administration uses your own MySQL permissions; the PHP application account should have normal DML, not `ALTER` or `DROP` privileges.
4. Have institutional codes ready or use the **clearly fictional testing examples** below. Don't represent test records as official university structure.

## B. Backup and deployment (existing Phase 2 installation)

**Do not reimport `database/student_portal.sql` to an existing database. That is only for empty fresh installations.**

```bash
cd /var/www/prospak-academy
pwd
git status --short
php -v
php -m | grep -Ei 'PDO|pdo_mysql|fileinfo'

# Stop if your Git tree has local tracked changes: review/commit/back them up first.
# Database backup; ensure this directory exists and has private permissions.
mkdir -p "$HOME/prospak-backups"
chmod 700 "$HOME/prospak-backups"
sudo mysqldump --single-transaction prospak_academy > "$HOME/prospak-backups/prospak-before-phase3.sql"
test -s "$HOME/prospak-backups/prospak-before-phase3.sql" && echo "Database backup exists"

# Back up your application, excluding sensitive files from any shared archive.
# For a simple on-disk safety copy:
sudo cp -a /var/www/prospak-academy /var/www/prospak-academy-before-phase3
```

**Recommended Git update if the Phase 2 `.git` directory is intact:** download `prospak-academy-phase3.git.bundle` to `~/Downloads`, then:

```bash
cd /var/www/prospak-academy
# Only proceed if 'git status --short' is empty.
git fetch ~/Downloads/prospak-academy-phase3.git.bundle \
  refs/heads/feature/phase-3-academic-organisation:refs/remotes/phase3/phase-3-academic-organisation
git switch -c feature/phase-3-academic-organisation phase3/phase-3-academic-organisation
```

This updates tracked project files without overwriting the ignored `.env` or private `storage/` files. **If your local Git state differs, stop and report the output of `git status --short` (never share `.env`).** As an alternative, extract the Phase 3 ZIP into a separate test directory and copy the `.env` and private photo storage manually; do not overwrite a live Phase 2 tree without a backup.

**Apply the Phase 3 migration exactly once after source deployment and backup:**

```bash
cd /var/www/prospak-academy
sudo mysql prospak_academy < database/migrations/0003_phase3_academics.sql
php tests/phase3_db_readonly.php
```

Expected: the migration completes without errors, and the read-only script reports the four new tables, the three placement columns, five FKs and correct administrator/academic-officer grants. If `sudo mysql` is not configured, use your known MySQL administrator account with an interactive prompt (do not put passwords in commands). The migration is **not idempotent**. If it fails midway, preserve your backup and inspect the actual schema before retrying; do not rerun blindly.

**Brand-new empty installation only:** `sudo mysql < database/student_portal.sql` creates all tables from phases 1–3 and their permitted seed roles. Do **not** use it for upgrading.

## C. Automated checks to run on Ubuntu

```bash
cd /var/www/prospak-academy
find app bootstrap config public routes scripts templates tests -name '*.php' -print0 | xargs -0 -n 1 php -l
php tests/phase1_unit.php
php tests/phase2_unit.php
php tests/phase3_unit.php
python3 tests/static_checks.py
python3 tests/phase2_static.py
python3 tests/phase3_static.py
php tests/phase2_db_readonly.php
php tests/phase3_db_readonly.php
```

Expected: zero syntax errors; previous and new unit/static suites pass; both DB read-only scripts pass. Any error should be reported by check name and its sanitized error output.

## D. Test data (fictional, for testing only)

Use your administrator account (created in Phase 1). Keep at least one academic officer, one student and one lecturer created in Phase 2. For example, only if these records do not conflict with your real institution:

| Entity | Code | Name | Parent | Status / order |
|---|---|---|---|---|
| Faculty | `SCI` | `Faculty of Science` | — | active |
| Faculty | `ARTS` | `Faculty of Arts` | — | active |
| Department | `CSC` | `Computer Science` | `SCI` | active |
| Department | `MTH` | `Mathematics` | `SCI` | active |
| Programme | `BSC-CS` | `BSc Computer Science` | `CSC` | active |
| Programme | `BSC-MTH` | `BSc Mathematics` | `MTH` | active |
| Academic level | `100` | `100 Level` | — | display order `100` |
| Academic level | `200` | `200 Level` | — | display order `200` |

These are *not* real Prospak Academy settings. Use only in a disposable test environment, or use actual approved records in a live installation.

## E. Manual browser test cases

Configure your Apache host (Phase 1 instructions) and open `http://prospak.test/login` (if using the PHP built-in server, `http://127.0.0.1:8000/login`). Use the matching domain throughout.

1. **Administrator access.** Log in with an active administrator. Open `/academics/faculties`. **Expected:** Hope UI academic list, Add button and four section tabs. Sidebar shows academic navigation.
2. **Create faculty.** Click **Add Faculty**, enter `SCI`, `Faculty of Science`, `Active`, save. **Expected:** redirect to `/academics/faculties`, success message and one row with student count 0.
3. **Create department.** Open `/academics/departments`, **Add Department**, select `SCI`, enter `CSC` and `Computer Science`. **Expected:** row appears with Faculty of Science as parent.
4. **Create programme.** Open `/academics/programmes`, add `BSC-CS` / `BSc Computer Science` under `CSC`. **Expected:** parent Computer Science is shown.
5. **Create level.** Open `/academics/levels`, add code `100`, name `100 Level`, order `100`. **Expected:** new level appears. Use distinct sort orders for additional levels.
6. **Edit academic record.** Click **Edit** on a faculty, update its name, save. **Expected:** new name appears in list and related department parent label; code preserved unless explicitly edited.
7. **Status inactivation.** Edit a programme, mark **Inactive**, save; try assigning it to a student not already assigned to it. **Expected:** unavailable for new selection or server rejects stale submission. Existing assignments remain in place and visible. Reactivate programme to continue.
8. **Student placement.** Open `/admin/users`, view a Phase 2 student, click **Assign academic placement**, select `BSC-CS` and `100 Level`, save. **Expected:** account detail shows faculty/department/programme/level; own profile shows the same to the student after login.
9. **Lecturer placement.** Open a lecturer's account, click **Assign academic placement**, choose `CSC`, save. **Expected:** lecturer profile shows department and its parent faculty, not a programme.
10. **Associated students.** In `/academics/faculties`, click its student count. Repeat for department, programme and level. **Expected:** student appears in all relevant scopes, and counts reflect real assignments; unrelated students do not appear.
11. **Search and empty listings.** Search `CSC` on departments and an impossible value. **Expected:** matching row in first case, friendly empty-state text in second. Search associated students by matriculation ID. **Expected:** matching student only.
12. **Pagination.** In a disposable test environment, add more than 20 entries to an academic list, then use Next/Previous with filters active. **Expected:** no duplicate rows, stable ordering, maximum 20 per page, filters preserved, out-of-range page clamped.
13. **Duplicate prevention.** Try creating a second faculty with code `SCI`, or two levels with the same order. **Expected:** safe validation error, no duplicate row, audit log not falsely showing success.
14. **Invalid required values.** Submit empty name, invalid code `**`, or invalid level order `0` / `10000`. **Expected:** HTTP 422, relevant field errors, no record created or modified.
15. **Inactive-parent guard.** Mark faculty inactive, then try creating a new department under it or assigning a lecturer to a department under it. **Expected:** request rejected. Existing links remain readable. Restore active status to proceed.
16. **Invalid and nonexistent IDs.** Open `/academics/faculties/form?id=abc`, `/academics/students?entity=programmes&id=0`, and `/academics/programmes/form?id=4294967295`. **Expected:** HTTP 400 for invalid IDs and HTTP 404 if a valid but nonexistent ID is requested; no SQL error details.
17. **CSRF negative test.** Submit an academic create POST without `_csrf` (using browser developer tools or `curl`, without valid login data). **Expected:** HTTP 419, no inserted rows.
18. **Unauthenticated access.** Sign out, then open `/academics/departments`, `/academics/placement`, `/academics/students?entity=faculties&id=1`. **Expected:** redirects to login; direct URL access does not bypass guards.
19. **Role restrictions.** Log in as student and lecturer; open `/academics/faculties` and `/academics/placement?id=<other-user-ID>`. **Expected:** HTTP 403 for global management after authentication. Log in as academic officer; list and associated student view work, but `GET /academics/faculties/form` and `POST /academics/faculties/save` return 403 with a valid CSRF token. An academic officer must not edit academic structure.
20. **Transaction and audit verification.** Make a successful faculty edit and student placement, then intentionally attempt a duplicate code. **Expected:** only successful changes reflected in database and audit log; failed edit leaves the original row unchanged. Existing Phase 2 login, account edits, photo authorization, suspension and password-change behavior still work.

## F. Read-only SQL verification (phpMyAdmin or MySQL terminal)

```sql
USE prospak_academy;
SHOW TABLES;
SHOW CREATE TABLE departments;
SHOW CREATE TABLE programmes;
SHOW CREATE TABLE students;
SHOW CREATE TABLE lecturers;

SELECT f.code AS faculty_code, d.code AS department_code, p.code AS programme_code
FROM programmes p
JOIN departments d ON d.id=p.department_id
JOIN faculties f ON f.id=d.faculty_id
ORDER BY f.code,d.code,p.code;

SELECT s.matriculation_number, u.full_name,
       p.code AS programme, d.code AS department, f.code AS faculty,
       al.code AS academic_level
FROM students s
JOIN users u ON u.id=s.user_id
LEFT JOIN programmes p ON p.id=s.programme_id
LEFT JOIN departments d ON d.id=p.department_id
LEFT JOIN faculties f ON f.id=d.faculty_id
LEFT JOIN academic_levels al ON al.id=s.academic_level_id
ORDER BY s.id DESC LIMIT 20;

SELECT l.staff_number, d.code AS department_code, f.code AS faculty_code
FROM lecturers l
LEFT JOIN departments d ON d.id=l.department_id
LEFT JOIN faculties f ON f.id=d.faculty_id
ORDER BY l.id DESC LIMIT 20;

SELECT r.slug AS role_slug,p.slug AS permission_slug
FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE p.slug LIKE 'academics.%'
ORDER BY r.slug,p.slug;

SELECT event_type, COUNT(*) AS total
FROM audit_logs WHERE event_type LIKE 'academics.%'
GROUP BY event_type ORDER BY event_type;
```

Expected: 4 new tables, 3 new nullable placement columns, valid FK paths, an admin with view+manage, an academic officer with view, and neither role for student/lecturer. Existing unrelated student accounts and IDs remain unchanged. SQL rows depend on the test records you actually entered.

## G. Negative/data-integrity behaviour

- **No DELETE endpoint** is provided for academic records. Use status `inactive` instead so existing associations are preserved.
- A child may not be newly associated to an inactive parent. Existing associations with subsequently inactive ancestors remain readable.
- A department with programmes or lecturers cannot move to another faculty; a programme with students cannot move to another department. This avoids silent reassignment of existing academic records.
- Student programme and level are optional until assigned; lecturers have an optional primary department. No student department_id is stored (derived from programme).
- CSRF protection applies to all POST operations. The server verifies roles and all submitted IDs; browser controls alone are insufficient.

## H. Troubleshooting and feedback

| Symptom | What to check |
|---|---|
| `could not find driver` | `php -m | grep pdo_mysql` and PHP MySQL extension installation |
| `Table 'prospak_academy.faculties' doesn't exist` | Migration not yet applied to the right database |
| `Duplicate column name` / `Table already exists` | Migration was partly or fully applied; **do not rerun blindly**. Inspect schema and backup |
| 419 CSRF | Refresh the page, keep same host/cookies and retry, check session settings |
| 403 for legitimate admin | Confirm current account role and `academics.manage` grant, first-login password change completed |
| `Parent must be active` | Verify selected faculty and department status; use active parent |
| Old links still visible after inactivation | Expected: existing associations are preserved; new associations are rejected |
| 503 on protected pages | Verify PDO MySQL, `.env` connection, DB service and application logs (do not disclose secrets) |
| New columns not visible | Check Phase 3 migration and upgrade deployment order |

**Send test case numbers and sanitized browser/terminal errors** for failed checks. Never send `.env`, passwords, full database exports, real student details or temporary credentials. A Phase 3 completion claim requires successful functionality on your Ubuntu/MySQL environment and your confirmation.
