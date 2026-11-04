# Prospak Academy — Phase 2 implementation and verification report

**Date:** 2026-11-04
**Phase:** 2 — User, Student and Lecturer Management
**Status:** **Implementation complete for testing; Phase 2 NOT accepted yet.** The user confirmed Phase 1 passed. Phase 3 remains blocked until manual Phase 2 testing is confirmed and explicitly authorized.

## 1. Agreed scope delivered

- New administrator-only user management: create accounts for Administrator, Academic Officer, Lecturer and Student; edit role-appropriate details; filter/search and paginated listing (20 per page); view details; suspend/reactivate accounts; reset temporary credentials.
- New student profile linked 1:1 to user by FK, unique institution-provided matriculation number, first and last name, phone, optional gender/DOB/admission year, private passport photo.
- New lecturer profile linked 1:1 to user by FK, unique institution-provided staff number, first and last name, phone and optional designation.
- Personal read-only `/profile` for authenticated account owners; administrators alone may manage the profiles of others.
- Temporary credentials: cryptographic random 36-character password generated per new account or admin reset, bcrypt hashed in `users`, revealed only once to the administrator (short-lived session receipt), mandatory password change on first login, sessions invalidated after credential/status/role changes.
- Administrator↔Academic Officer role changes supported, but self-demotion, self-suspension and removal of the last active administrator are blocked. Student/lecturer role conversions intentionally not supported because no safe approved academic-history migration exists.
- Retained Phase 1 login/logout/reset/change-password and server-side security, with minimal, required adjustments for forced first-login password change. Public registration remains unavailable.
- Retained Hope UI v5.0.0 styling, reusable header/sidebar/layout, local CSS/JS, and UTC-to-WAT display. Added actual account lists, input validation, pagination, safe status notices and empty-state handling.

## 2. Files created

- `app/Core/PeopleValidation.php`
- `app/Repositories/PeopleRepository.php`
- `app/Services/PeopleService.php`
- `app/Controllers/PeopleController.php`
- `database/migrations/0002_phase2_people.sql`
- `templates/people/list.php`, `templates/people/form.php`, `templates/people/detail.php`
- `public/assets/js/people-form.js`
- `storage/uploads/student_photos/.gitkeep` (contents of this directory remain gitignored)
- `tests/phase2_unit.php`, `tests/phase2_static.py`, `tests/phase2_db_readonly.php`
- `docs/phase2-preparation.md`, `docs/phase2-testing.md`, this report

## 3. Files modified

- `public/index.php`, `routes/web.php` — register Phase 2 controller and routes.
- `app/Repositories/UsersRepository.php`, `app/Services/AuthService.php`, `app/Controllers/AuthController.php`, `app/Core/Guard.php` — Phase 1-compatible forced-password-change support and secure access gating.
- `database/student_portal.sql` — cumulative reproducible fresh-install export including Phase 1 and Phase 2.
- `templates/components/sidebar.php`, `templates/auth/change.php`, `templates/portal/home.php`, `public/assets/css/app.css` — Hope UI navigation/forms/copy/styles.
- `tests/phase1_unit.php` — update the landing-page copy assertion; still verifies the original security and routing checks.
- `README.md`, `docs/phase0-decisions.md` — record approved Phase 1 acceptance, Phase 2 workflow and migration requirements.

No existing Phase 1 SQL migration was rewritten. No known existing student, course or grade data were deleted. No branch was force-pushed.

## 4. Database changes (defined, NOT executed here)

`database/migrations/0002_phase2_people.sql`:
- `users.must_change_password TINYINT(1) NOT NULL DEFAULT 0` with CHECK constraint (preserves existing Phase 1 accounts as already set up).
- `students` with PK, unique FK `user_id`, unique `matriculation_number`, name and optional profile fields, private photo filename, timestamps and indexes.
- `lecturers` with PK, unique FK `user_id`, unique `staff_number`, name and optional contact/appointment fields, timestamps and indexes.
- Seed permission `users.manage` assigned only to `administrator`.
- Fresh cumulative `database/student_portal.sql` contains 9 tables total. An existing Phase 1 database must receive ONLY the incremental `0002` migration after backup. Do not re-import cumulative SQL into existing data.

## 5. Security protections implemented in code

- Strict admin **permission and role** check, including for direct URLs, state-changing methods and private photo uploads.
- Server-enforced account ownership for individual profile/photo retrieval, before reading another student's photo.
- Global Phase 1 CSRF check on every POST remains in effect; all Phase 2 POST forms include `_csrf`.
- Role/status/IDs/server-side field validation, prepared queries, bound integer pagination, deterministic ordering and escaped LIKE special characters.
- Stored output escaped using existing `View::escape`; no dynamic HTML includes, query-string data or filenames trusted as paths.
- Atomic multi-table user/profile writes and audit events; FK and unique constraints, duplicate-safe rollback and hidden diagnostics.
- Session-version invalidation on status/credential/role/email change, and forced password change on first login.
- No public account registration, no plaintext password storage/logging, one-time display for temporary credential, no default/test accounts shipped.
- Photo uploads checked by `finfo`, `getimagesize`, fixed MIME allowlist, 2 MiB cap, 128-bit random filename, storage outside public webroot, authorized authenticated image route, safe old-file cleanup.

These controls are **source-reviewed and partially smoke-tested**, but some cannot be considered verified until live DB/browser tests pass.

## 6. Tests actually run and observed results

- **PHP syntax:** `php -l` for all 46 PHP files under app/bootstrap/config/routes/templates/public/scripts/tests — all passed, zero syntax errors.
- **Phase 1 regression:** `php tests/phase1_unit.php` — **25 checks passed**.
- **Phase 2 non-DB units:** `php tests/phase2_unit.php` — **43 checks passed**, including forms, malicious input, first/last name rules, role restrictions, identifiers, pagination input, student profile rendering, output escaping and UTC→WAT.
- **Phase 1 static SQL/security:** `python3 tests/static_checks.py` — passed (7 Phase 1-only tables still present).
- **Phase 2 static:** `python3 tests/phase2_static.py` — passed (2 tables, forced-change column, admin permission, cumulative export, required routes/assets).
- **JavaScript syntax:** `node --check public/assets/js/people-form.js` — passed.
- **PHP built-in server HTTP smoke:** `GET /login` -> 200; anonymous GET `/admin/users`, `/admin/users/new`, `/admin/users/view?id=1`, `/admin/students`, `/admin/lecturers`, `/profile`, `/student-photo?id=1` -> **303 to `/login`**; `GET /register` -> **404**; `GET /assets/js/people-form.js` -> **200**; POST `/admin/users/new` without CSRF -> **419**. Results observed using curl.
- **Real MySQL integration:** attempted `php tests/phase2_db_readonly.php` but **could not run successfully** (`PDOException` because the workspace lacks the MySQL PDO driver / connected MySQL). This is an environment limitation, **not a passing database test**.
- **Apache virtual-host/browser rendering, real admin/student/lecturer sign-in, actual CRUD, unique/foreign-key constraint execution, image uploads, admin/suspension/role enforcement for real accounts, mail delivery:** **not executed** in this environment; require user’s Ubuntu test.
- **GitHub remote/push:** none configured; no GitHub push performed.

## 7. Known limitations and outstanding decisions

1. **No live MySQL verification** here. User must run `php tests/phase2_db_readonly.php`, SQL checks and the complete manual integration suite after migration on Ubuntu.
2. **No official identifier format** supplied. Administrators type existing issued matric/staff identifiers; automated issuance deferred until official numbering format is approved. Synthetic samples in test guide are not institutional policy.
3. **Academic organisation fields** (faculty, department, programme, level) cannot have FKs until the approved Phase 3, so are not mocked, stored as text or prematurely implemented.
4. **Role conversions into/out of student/lecturer** blocked to preserve future academic relationships. If a conversion workflow becomes necessary, it must be designed and tested explicitly before allowing it.
5. First-login temporary password is shown once by an admin. There is no integrated invitation emailing mechanism or SMS transport, so admins must provide it securely out of band.
6. Need real browser testing of multipart limits and private storage writability on user's Ubuntu environment. No real institutional records or accounts included in the package.
7. Not production ready pending actual MySQL verification, secure HTTPS hosting, photo storage permissions and complete user acceptance testing.

## 8. Manual testing and troubleshooting

Follow **`docs/phase2-testing.md`**: prerequisites, safe migration/backup, five fictional account examples, 20 numbered cases (normal/negative/CSRF/auth/ownership/photos/roles/pagination/XSS), exact browser URLs, read-only MySQL queries, expected outcomes and troubleshooting.

## 9. Git

- Based on user's accepted Phase 1 branch `feature/phase-1-authentication` commit `b14ef5b`.
- Phase 2 working branch: **`feature/phase-2-people`**.
- Local commits are included in packaged repository (see `git log --oneline --max-count=5`). No GitHub remote was configured or pushed.

## 10. Phase tracking

| Phase | Status | User testing |
|---|---|---|
| Phase 0 – Inspection and Planning | Completed | Passed |
| Phase 1 – Foundation and Authentication | Completed | Passed (confirmed 2026-11-04) |
| **Phase 2 – User, Student and Lecturer Management** | **Implemented; awaiting acceptance** | **Not yet confirmed** |
| Phase 3 – Academic Organisation Management | Pending | Not started |
| Phase 4 – Academic Sessions, Semesters and Courses | Pending | Not started |
| Phase 5 – Lecturer Assignment and Registration | Pending | Not started |
| Phase 6 – Assessment and Grade Management | Pending | Not started |
| Phase 7 – Result Submission, Approval and Publication | Pending | Not started |
| Phase 8 – GPA and CGPA Calculation | Pending | Not started |
| Phase 9 – Role-Based Dashboards | Pending | Not started |
| Phase 10 – Announcements and Notifications | Pending | Not started |
| Phase 11 – Reports and Analytics | Pending | Not started |
| Phase 12 – Transcript Management | Pending | Not started |
| Phase 13 – Security Review, Integration and Final Testing | Pending | Not started |

**Recommended next phase after acceptance:** Phase 3 — Faculties, Departments, Programmes and Academic Levels. **Do not start it automatically.**
