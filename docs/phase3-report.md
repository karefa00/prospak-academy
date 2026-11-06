# Prospak Academy — Phase 3 implementation and verification report

**Scope:** Phase 3 of the user-approved 14-phase roadmap; faculties, departments, programmes and academic levels. **Date:** 6 November 2026.

**Status:** Implemented and independently checked for PHP syntax, DB-independent validation, template rendering and public HTTP routing. Actual MySQL integration, privileged browser workflows and user acceptance remain outstanding. **Do not declare the phase complete or begin Phase 4 yet.**

## Features implemented

- Normalized, user-defined academic organisation: faculties -> departments -> programmes plus institution-wide academic levels.
- CRUD **Create/Read/Update/Inactivate** (not destructive Delete), search/filter, stable paginated listings, safe HTML encoding and reusable Hope UI views.
- Read-only associated-student listing per faculty, department, programme and level.
- Student placement to one programme and one level; derived department/faculty; lecturer placement to one primary department. Existing student and lecturer accounts are not rewritten or removed.
- Admin-only editing/placement; academic officer view-only; users without grants denied. Profiles of students and lecturers show authorized academic placement.
- CSRF validated centrally for every POST, prepared PDO statements, transactions, NOT NULL/UNIQUE/FK/CHECK constraints, no destructive SQL, audit log events.
- No invented institutional faculties, programmes, levels or grading policies: only test examples in testing guide.

## Files created

- `app/Controllers/AcademicController.php`
- `app/Core/AcademicValidation.php`
- `app/Repositories/AcademicRepository.php`
- `app/Services/AcademicService.php`
- `database/migrations/0003_phase3_academics.sql`
- `templates/academics/list.php`, `form.php`, `students.php`, `placement.php`
- `tests/phase3_unit.php`, `phase3_static.py`, `phase3_db_readonly.php`
- `docs/phase3-preparation.md`, `phase3-testing.md`, `phase3-report.md`

## Files modified

- `database/student_portal.sql` — cumulative fresh-install schema appended with Phase 3 migration.
- `public/index.php`, `routes/web.php` — controller construction and routes.
- `app/Repositories/PeopleRepository.php` — academic join labels/IDs for individual authenticated profile view.
- `templates/people/detail.php`, `templates/components/sidebar.php`, `templates/portal/home.php` — placement link, academic labels and sidebar.
- `tests/phase1_unit.php` — current phase dashboard copy assertion updated, other checks preserved.
- `README.md` — install, scope, verified tests and roadmap.

## Database changes

- New InnoDB tables: `faculties`, `departments`, `programmes`, `academic_levels`.
- New nullable student columns: `programme_id`, `academic_level_id`.
- New nullable lecturer column: `department_id`.
- New permissions: `academics.view`, `academics.manage`; admin gets both, academic officer gets view only.
- FK paths: department -> faculty; programme -> department; student -> programme & level; lecturer -> department; all `ON DELETE RESTRICT`.
- Unique codes and scoped names; academic-level sort order unique. Additional indexes for lookups and pagination.

## Security/design notes

- Exact PHP route authorization via `Guard::requirePermission` plus explicit administrator-role check for changes.
- Strong CSRF token validated before request routing; all mutation forms include hidden `_csrf`.
- Fixed SQL identifier allowlist and PDO bound values (`LIMIT/OFFSET` integer bound).
- Parent and placement lookups require active records and active ancestors for new associations.
- Inactivation retains existing links; changing department faculty with dependent programmes or programme department with dependent students is blocked.
- Academy definitions and student IDs are real database references rather than hard-coded demo values.

## Actual verification results

- `php -l` all PHP sources: see command log below. **Passed.**
- `php tests/phase1_unit.php` (25 checks): **Passed.**
- `php tests/phase2_unit.php` (43 checks): **Passed.**
- `php tests/phase3_unit.php` (38 checks): **Passed.**
- `python tests/static_checks.py`: **Passed.**
- `python tests/phase2_static.py`: **Passed.**
- `python tests/phase3_static.py`: **Passed.**
- HTTP smoke: login 200, seven unauthenticated academic routes 303, registration 404, bundled CSS 200, missing-CSRF POST 419: **Passed.**
- MySQL/driver live schema and transaction checks: **Not run** — local PHP lacks `pdo_mysql`, MySQL client/server not present. The new `php tests/phase3_db_readonly.php` is provided for the user's Ubuntu environment.
- Authenticated browser feature tests, audit rows, organization hierarchy counts and actual student/lecturer placement: **Not run**, require working MySQL and user-run tests.

## Git

Local feature branch: `feature/phase-3-academic-organisation`. Implementation commit: `817eaa3` (`feat(academics): implement Phase 3 organisation and academic placement`). Documentation commit: recorded in the release summary. No GitHub remote configured; no push claimed.

## Known limitations

- Four academic entities are not seeded; administrators enter institutional approved data.
- Academic levels are a global list, not tied to specific programmes (may need an approved policy change in a later phase if required).
- A student's current programme and current level are stored; no academic movement/level history in Phase 3. Avoid treating them as a transcript history source.
- A lecturer has one primary department; course-specific teaching assignments await Phase 5.
- No destructive deletion, file uploads, email notifications or reports added in this phase.
- User must perform all MySQL and authenticated browser tests before acceptance.

## Next phase

Phase 4 — Academic Sessions, Semesters and Courses — **must not start until the user tests, confirms Phase 3, and explicitly authorizes Phase 4**.
