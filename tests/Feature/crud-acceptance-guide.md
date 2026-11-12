# Prospak Academy — CRUD & Administrator Result Publication Acceptance Guide

Use **a backed-up test copy** of your own `prospak_academy` MySQL database. This update has **no new database tables or migration**; it uses columns and relationships already present in the streamlined project. Never import `database/schema/prospak_academy.sql` into a populated database.

## Preconditions

1. Backup database and private uploaded photographs; preserve your existing `.env`.
2. Extract updated ZIP into a new directory before switching the running app. Point it at a copy of your database.
3. Use PHP 8.1+ with `pdo_mysql`, `fileinfo` and sessions, and MySQL 8.x.
4. Ensure `storage/sessions`, `storage/logs`, and `storage/private-uploads` are writable by your PHP user.
5. Run: `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
6. Run: `php tests/Unit/validators.php`, `php tests/Unit/crud_validators.php`, `php tests/Feature/structure.php`, `php tests/Feature/crud_routes.php`, `php tests/Feature/crud_render.php`, `bash tests/Feature/http_smoke.sh`, `php tests/Integration/database_readonly.php`.
7. Start local web server from the extracted project root: `php -S 127.0.0.1:8000 -t public public/index.php`. Open `http://127.0.0.1:8000/login`.

## Administrator CRUD tests

1. **Accounts:** Log in as Administrator; go to **User accounts**. Create a lecturer and academic officer. Select **Edit**, modify name/email (and lecturer's staff number/department), save, then reload to verify persistence. Suspend and reactivate an account. Confirm suspended account cannot log in and existing sessions are invalidated. Attempt suspending your own account — must be rejected. Attempt suspending the final active administrator — must be rejected.
2. **Students:** Register a student. In **Students**, select **Edit**, change name, email, matriculation number, phone and optional photo. Change programme/level before any current-semester registration. Confirm the student sees changed profile. Deactivate then reactivate the student; prior grade and registration records must remain. Register the student for an active semester, then attempt to change programme/level; the update must be blocked until current-semester registrations are resolved.
3. **Academic reference records:** Go to **Academic settings**. For faculties, departments, programmes and levels, use each row's **Edit** and **Deactivate/Activate** controls. Verify duplicate codes are refused. Try to deactivate a faculty with active departments, department with active programmes/courses, programme with active students/courses, and level with active students/courses — each must be rejected until dependencies are resolved. Verify each table displays 10 rows per page, with its own Previous/Next controls when records exceed 10.
4. **Sessions and semesters:** Edit a test session's dates; verify a date change that would exclude an existing semester is blocked. Edit a semester's dates/deadline/grading window. Reject impossible dates or registration deadlines beyond the period. Activate/deactivate a semester without active registrations; try deactivating one with active registrations or unfinished results — must be blocked. Try deactivating a session with an active semester — must be blocked. Changing active session must not silently interrupt an active semester with ongoing registrations.
5. **Courses:** Create a course with no registrations, then select **Edit** in Course catalogue. Change its title/code, department, level, semester, programme associations and prerequisites. Attempt self-prerequisite and a circular prerequisite (course A needs B, then B needs A): reject. Register a student for the course and try changing credit units or programme — reject to protect historical grading. Renaming its title should still work. Try deactivating an active prerequisite course used by another active course — reject.
6. **Lecturer assignments:** Open **Lecturer assignments**. Assign a lecturer; use **Reassign** before score entry, and **Unassign/Reactivate**. Verify pagination at 10 rows. After scores are entered or a batch is under review, attempts to reassign or unassign must be blocked. Once the batch is published, archival deactivation of the historical assignment is allowed, but reassignment remains blocked.
7. **Course enrolments:** As a student, register an eligible course, confirm one `registered` record, attempt duplicate registration (reject), drop the course, and re-register during the allowed registration window. Check the registration events/history and ensure no duplicate registration row is created. Repeat with an expired registration deadline (reject).
8. **Grades & publication:** As lecturer, enter CA and Exam scores for each registered student; submit a complete batch. As academic officer, approve or reject (rejections require reason). Correct and resubmit rejected grades as lecturer. As admin, open **Publish Results** from the sidebar or dashboard and verify only approved batches are listed. Click the **Publish Results** button for an approved batch, then confirm it disappears from the pending list and appears in Recent publications. As student, log in and check only published grades are visible. Attempt publishing a non-approved batch by forging a POST — server must reject. Attempt submitting POST with a missing CSRF token — HTTP 419.

## SQL verification (read-only, run in phpMyAdmin on the TEST database)

```sql
SELECT u.id, u.full_name, u.email, r.slug AS role, u.account_status FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC LIMIT 10;
SELECT s.id, s.matriculation_number, s.first_name, s.last_name, u.account_status, s.programme_id, s.academic_level_id FROM students s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC LIMIT 10;
SELECT id, code, name, status FROM faculties ORDER BY id DESC LIMIT 10;
SELECT id, code, name, faculty_id, status FROM departments ORDER BY id DESC LIMIT 10;
SELECT id, code, name, department_id, status FROM programmes ORDER BY id DESC LIMIT 10;
SELECT id, code, name, sort_order, status FROM academic_levels ORDER BY id DESC LIMIT 10;
SELECT id, name, starts_on, ends_on, status FROM academic_sessions ORDER BY id DESC LIMIT 10;
SELECT id, academic_session_id, semester_number, starts_on, ends_on, registration_deadline, status FROM semesters ORDER BY id DESC LIMIT 10;
SELECT id, code, title, credit_units, department_id, academic_level_id, semester_number, status FROM courses ORDER BY id DESC LIMIT 10;
SELECT id, course_id, semester_id, lecturer_id, status FROM course_assignments ORDER BY id DESC LIMIT 10;
SELECT id, student_id, course_id, semester_id, status FROM course_registrations ORDER BY id DESC LIMIT 10;
SELECT id, course_id, semester_id, status FROM result_batches ORDER BY id DESC LIMIT 10;
SELECT id, batch_id, published_by, published_at FROM result_publications ORDER BY id DESC LIMIT 10;
```

## Troubleshooting

- **HTTP 303 to /login:** sign in; the route is protected.
- **HTTP 419:** refresh the form; the CSRF token is missing, expired or invalid.
- **Can't deactivate:** review active child relationships, active assignments, current registrations and unfinished batches before changing state.
- **Duplicate code/email:** use a unique value; existing relational UNIQUE constraints are enforced.
- **Results aren't visible:** lecturer must submit, academic officer must approve, then administrator must publish. A submitted or rejected batch does not appear in the admin's Ready to publish list.
- **Database error:** verify the extracted app is using a **copy of the existing Phases 1–8 schema**, not an incomplete new database. PHP/MySQL must have the `pdo_mysql` extension.

Do not use these fictional tests in a live academic database. Mark each case passed/failed after browser and SQL verification; code-only tests are insufficient to certify CRUD behavior.
