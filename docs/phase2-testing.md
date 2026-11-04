# Prospak Academy — Phase 2 manual testing guide

**Phase:** 2 — User, Student and Lecturer Management
**Status:** Code implementation prepared; **your acceptance tests must pass before Phase 2 is complete**.
**Testing environment:** Your own Ubuntu + Apache/PHP 8.x + MySQL 8.x server, with **Phase 1 already functioning**.
**Safety:** The upgrade migration must only be applied once. Take a verified database backup. Use fictional records and a disposable development environment for destructive/permission tests. Never share real passwords, student PII, photo data, raw logs, or `.env` in chat.

## A. Before you start

1. **Confirm Phase 1:** sign in to your existing Prospak Academy administrator account and verify `/dashboard` and `/change-password` work. If not, resolve that issue first.
2. **Back up the existing database (no password on command line):**
   ```bash
   cd /var/www/prospak-academy
   sudo mysqldump --single-transaction --routines --triggers prospak_academy > ~/prospak-before-phase2.sql
   ls -lh ~/prospak-before-phase2.sql
   ```
   Keep the backup private, outside the web root. Run this as an operator with read privileges. If your Ubuntu MySQL account requires credentials, use your normal prompt-based login.
3. **Upgrade the application:** extract the Phase 2 ZIP to a *new temporary directory*, inspect its files, and copy the new source tree onto your development installation **without overwriting `.env`** or deleting `storage/uploads/`, `storage/logs/`, or user data. For a controlled deployment, stop web traffic briefly and keep the old tree for rollback. Example (adjust actual extracted path):
   ```bash
   cd /var/www/prospak-academy
   # Run from the NEW source tree; do not apply the full fresh-install dump here:
   sudo mysql prospak_academy < database/migrations/0002_phase2_people.sql
   ```
   **Important:** Run the above migration only after the *new* Phase 2 source and the Phase 1 DB backup are in place; deploy code and migration together, preferably during a maintenance window. If the migration fails, do not rerun blindly; inspect `SHOW TABLES`, `SHOW COLUMNS`, and your backup before retrying.
4. **Private file storage permissions:**
   ```bash
   sudo mkdir -p /var/www/prospak-academy/storage/uploads/student_photos
   sudo chown -R www-data:www-data /var/www/prospak-academy/storage/uploads
   sudo chmod 750 /var/www/prospak-academy/storage/uploads /var/www/prospak-academy/storage/uploads/student_photos
   ```
   Ensure `.env` stays outside the public web root and is not overwritten. For `php -S` development, the directory must instead be writable by the CLI process user. No public symlink to the photo directory.
5. **Lint and run checks:**
   ```bash
   cd /var/www/prospak-academy
   find app bootstrap config routes templates public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
   php tests/phase1_unit.php
   python3 tests/static_checks.py
   php tests/phase2_unit.php
   python3 tests/phase2_static.py
   php tests/phase2_db_readonly.php
   node --check public/assets/js/people-form.js  # optional if Node is installed
   ```
   Expected: no PHP syntax errors; Phase 1 25 checks; Phase 2 43 unit checks; Phase 1 and Phase 2 static checks pass; and the MySQL **read-only** checker passes. Static checks do not prove live operations.
6. **Optional development server:** set the `.env` `APP_URL=http://127.0.0.1:8000` if using:
   ```bash
   php -S 127.0.0.1:8000 -t public public/router.php
   ```
   Otherwise use your Apache site at **http://prospak.test**. On real production installations use HTTPS and a real domain.

## B. Suggested fictional test data

Use addresses you control or safely fabricated `example.org` placeholders (no reset email will be delivered unless email transport is configured):

| Type | First/Full name | Email | Identifier | Other |
|---|---|---|---|---|
| Student A | Ada Ibe | ada.phase2@example.org | PA/2026/0001 | Female, DOB 2003-02-24, admission year 2026 |
| Student B | Musa Bello | musa.phase2@example.org | PA/2026/0002 | Optional details empty |
| Lecturer | Sade Okon | sade.phase2@example.org | LECT/0021 | Designation Lecturer |
| Academic Officer | Test Academic Officer | officer.phase2@example.org | Not applicable | Admin-created |
| Optional second Administrator | Secondary Test Administrator | admin2.phase2@example.org | Not applicable | Needed for last-admin test |

**All identifiers are test-only examples, NOT a proposed official Prospak Academy numbering policy.** Admin-generated temporary passwords should be copied *once* to a safe temporary location and delivered privately only to the relevant test user. Never add them to Git, terminal shell history, screenshots, chat logs or tickets. Use separate incognito browser sessions for student/lecturer tests.

## C. Numbered browser tests — with expected outcomes

**Test 1 — Phase 1 remains functional.** Open `/login` and sign in with your existing Phase 1 administrator. Expect normal authenticated `/dashboard`, unchanged change-password and sign-out. Verify no new public `/register` page (HTTP 404).

**Test 2 — New administrative navigation.** As admin open `/admin/users`. Expect a responsive Hope UI table, filters, count, links to `/admin/students`, `/admin/lecturers`, and **Create account**. From a student session later, these admin pages must be unavailable even if their URLs are entered directly.

**Test 3 — Create a student.** Visit `/admin/users/new`, select Student, enter Student A fields above, press **Create account**. Expect redirect to `/admin/users/view?id=<new user ID>` with student details. A one-time temporary password is visible **once only**, along with a warning about private distribution. Copy it privately; reload and verify the password is no longer shown. Expect no `users` row without the linked `students` record.

**Test 4 — Create another student and a lecturer.** Repeat Test 3 for Student B and the Lecturer. Confirm staff/matric numbers and name details appear correctly on the detail pages. Their roles should be student and lecturer. Their first login must force a password change, not offer admin functions.

**Test 5 — Create an academic officer.** Create `officer.phase2@example.org` with the Academic Officer role. Expect a `users` row but **no** `students` or `lecturers` row. Admin role creation works similarly, but create a second admin only in a test environment.

**Test 6 — First-login password gate.** Open a new private/incognito browser. Visit `/login` and use Student A's email and temporary password. Expect redirect to `/change-password`. Manually type `/dashboard`, `/profile`, or `/admin/users` — each must still redirect to `/change-password`. Enter the temporary password as the current password and a new, different password 12–72 **bytes** long, then sign out. Login with the new password succeeds; the temporary password no longer works. The `must_change_password` field must change from `1` to `0`.

**Test 7 — Role and record authorization.** Signed in as Student A, `/profile` shows only Student A's details; `/admin/users`, `/admin/students`, `/admin/lecturers`, `/admin/users/new`, `/admin/users/edit?id=<B>`, and `/admin/users/view?id=<B>` must return HTTP **403** (or redirect to login if unauthenticated). Test the same with the lecturer and officer. As student, `/student-photo?id=<Student B user ID>` must return **403**. As student, `/student-photo?id=<Student A user ID>` should show only their own uploaded image once one exists.

**Test 8 — Edit student and lecturer profiles.** As admin open `/admin/users/view?id=<ID>`, click **Edit profile**. Change the student's phone, spelling, and admission year, then save. Confirm the detail page shows the changes. For the lecturer change designation and staff number. Verify updates persist after logout/relogin. Student/lecturer role conversions must be rejected, rather than silently deleting or orphaning academic records.

**Test 9 — Edit role assignment safely.** As admin create/edit an Academic Officer account, then change its role to Administrator and back. Expect role change, audit log, and invalidation of any other active sessions for that account. Attempt to change a student to lecturer by crafting a POST or changing a hidden form role; expect rejection. Attempt to change your own administrator role; expect rejection.

**Test 10 — Duplicate/invalid data.** Try creating a student with an existing email, then with another email but Student A's matric number, and a lecturer with the same staff number as an existing lecturer. Expect helpful validation/duplicate messages and **no extra orphan users**. Try blank names, invalid email, matric with spaces or illegal punctuation, DOB `2099-01-01`, invalid date `2026-02-30`, admission year `9999`, invalid role, overlong values and malformed phone. Expect HTTP 422 and field errors without exceptions or data changes.

**Test 11 — Search, filters and pagination.** Filter `/admin/users` by Student, Lecturer, Active and Suspended. Search by name, email, matric and staff number. Confirm totals and rows match; searching for `%` or `_` must match those literally, not return all accounts due to SQL wildcard interpretation. For pagination, create 21+ **fictional** accounts in a disposable development database. Expect 20 per page, stable order by newest account ID, preserved filters on Next/Previous, invalid or very large page numbers resolved without SQL errors. Empty searches show an empty-state message.

**Test 12 — Suspend and reactivate a student.** As admin open Student B's detail and choose **Suspend account**. Expect Suspended status; an existing Student B session must lose authenticated access on next protected request, and fresh login must fail generically. Then click **Reactivate account** and confirm they can log in again with their established password. Check `session_version` increments.

**Test 13 — Administrator safety controls.** On your own admin detail page, self-suspension and the temporary-password reset action are disabled and must also be rejected if manually POSTed. On a disposable test installation with **two** active admin accounts, one admin can suspend the other; verify the last remaining active admin cannot be suspended or demoted. Do not test last-admin guard by damaging a production administrator account.

**Test 14 — Reset another user's temporary password.** As admin open a student or lecturer detail page and click **Issue new temporary password**. Expect a **new one-time secret**, visible once only. Their old password must stop working, active sessions must be invalidated, and new login should force another password change. Try a second reload; the secret must not reappear. Reset yourself must be rejected.

**Test 15 — Student passport photo.** As admin, open Student A's detail. Upload a real JPEG or PNG image less than 2 MB. Expect success and display on the profile. Sign in as Student A and verify the image is visible in `/profile`. Replace it with a second photo; the old server file should be deleted after the transaction commits. The image must **not** be reachable as `/uploads/<file>` or through a path under `public/`. Verify only authorized sessions can access `/student-photo?id=<ID>`.

**Test 16 — Negative file uploads.** Attempt to upload a renamed `.php`, a text file renamed `.jpg`, an oversized image, a non-image file or a malformed image. Expect rejection, no saved filename and no new record in the `students.passport_filename` field. As a student, try POST `/admin/users/photo` with valid CSRF and a file; expect HTTP 403. A missing or invalid CSRF token should return HTTP 419 before changing data. Uploads must not execute server-side code.

**Test 17 — Cross-site scripting and injection safety.** In a **test** profile name try literal characters like `<script>alert(1)</script>` (if a user name is allowed), and a search string such as `' OR 1=1 --`. Expect user names to be *escaped as text* and search to behave as a literal safe query (no SQL errors, privilege change, or full-table bypass). If a student/lecturer name is restricted by other application policy, test a different allowed free-text field such as designation.

**Test 18 — Method, identifier and CSRF failures.** GET `/admin/users/status` and GET `/admin/users/temporary-password` should return HTTP 405. POST the corresponding actions with missing `_csrf` => HTTP 419. POST a zero/negative/text user ID with valid CSRF => HTTP 400. GET `/admin/users/view?id=999999999` (unknown, properly formed) => HTTP 404. Unauthenticated GET admin routes => redirect to login. Invalid role/status => validation error. Check audit logs for expected events only after successful actions.

**Test 19 — Legacy session and password regression.** Password change for your original Phase 1 administrator still works; password reset also works when a real email transport is enabled. Test that an administrator's old session is invalidated when another administrator changes that user's email/role or suspends their account. The original first-admin CLI script must still refuse to bootstrap another account.

**Test 20 — Responsive design.** Open list, detail and form pages at wide desktop, tablet and mobile widths. Hope UI styling should load from local assets, sidebar should stack or resize appropriately, and tables should remain scrollable rather than overflow unpredictably. Disable JavaScript and verify server validation still rejects invalid inputs (the role-specific form sections may be visible without JS, which is acceptable).

## D. MySQL verification — read-only statements

Use a local MySQL terminal or phpMyAdmin SQL tab. Do not paste user emails/passwords into support messages.

```sql
USE prospak_academy;
SHOW TABLES;
SHOW COLUMNS FROM users LIKE 'must_change_password';
SHOW CREATE TABLE students;
SHOW CREATE TABLE lecturers;
SELECT r.slug, p.slug FROM role_permissions rp
 JOIN roles r ON rp.role_id=r.id JOIN permissions p ON rp.permission_id=p.id
 WHERE p.slug='users.manage';
-- Expected: only administrator/users.manage.
SELECT u.id, u.full_name, u.email, r.slug role, u.account_status, u.must_change_password,
       u.session_version, s.matriculation_number, l.staff_number
FROM users u JOIN roles r ON r.id=u.role_id
LEFT JOIN students s ON s.user_id=u.id
LEFT JOIN lecturers l ON l.user_id=u.id
ORDER BY u.id DESC LIMIT 30;
-- Check normalized relationship: exactly one relevant profile row per student/lecturer user.
SELECT COUNT(*) AS students_without_correct_role
FROM students s JOIN users u ON u.id=s.user_id JOIN roles r ON r.id=u.role_id
WHERE r.slug <> 'student';
SELECT COUNT(*) AS lecturers_without_correct_role
FROM lecturers l JOIN users u ON u.id=l.user_id JOIN roles r ON r.id=u.role_id
WHERE r.slug <> 'lecturer';
SELECT event_type, COUNT(*) AS count_events FROM audit_logs
WHERE event_type LIKE 'people.%' GROUP BY event_type ORDER BY event_type;
SELECT COUNT(*) AS profiles FROM students;
SELECT COUNT(*) AS profiles FROM lecturers;
```

Expected: `must_change_password` present; two new tables and their `user_id` FKs and unique identifiers; only admin has `users.manage`; role mismatch counts are zero; audit event counts reflect your real operations (`people.created`, `people.updated`, `people.status_suspended`, `people.status_active`, `people.credential_reset`, `people.photo_updated`). **Do not query or display `users.password_hash` in shared screenshots.**

## E. Negative-test outcomes and troubleshooting

- **`SQLSTATE 42S02` or unknown column `must_change_password`:** Phase 2 migration was not applied, or code is pointing to the wrong DB. Check `.env` and `SHOW TABLES`; do not reimport the cumulative export into a database containing Phase 1 data.
- **Duplicate table/column on migration:** The migration may already have been partially or fully applied. Inspect state and use the backup to plan a safe recovery; do not repeatedly apply it.
- **HTTP 503 / `could not find driver`:** Install/enable `php-mysql` and restart Apache/PHP-FPM as relevant; check `php -m | grep pdo_mysql` for CLI and web SAPI.
- **403 for administrator:** Confirm login and `users.manage` permission seed. Do not grant the permission broadly. Check `roles`, `permissions`, `role_permissions`.
- **HTTP 419:** Reload the form; cookies and `_csrf` must both be present. Do not disable CSRF checks.
- **Photo cannot upload:** Check `fileinfo` PHP extension, `upload_max_filesize`, `post_max_size` (larger than 2 MB), write rights on private `storage/uploads/student_photos`, and actual MIME type. The application enforces its own 2 MB ceiling.
- **New account cannot sign in:** Check status is active, correct private temporary password (copied exactly), and login throttling. A `must_change_password=1` account should still sign in but be required to change password first.
- **Changing own admin email logs you out:** If observed, report it; the code aims to preserve the current session while invalidating other sessions via `session_version`.
- **Search/pagination errors:** Report the filter values and page number (no student PII) plus generic status and safe diagnostic event type, not raw credentials or an SQL dump.
- **Forgot password doesn't deliver mail:** Phase 1 mail transport may still be disabled. This is independent from administrator-issued temporary password setup.

## F. What to send after testing

Please reply with the **test numbers passed and failed**, whether the Phase 2 read-only DB check passed, and the HTTP response/error for any failing test. Where useful, give a sanitized screenshot showing no real student identifiers or credentials. I will fix Phase 2 issues and stop until you confirm the phase has passed and explicitly authorize Phase 3.
