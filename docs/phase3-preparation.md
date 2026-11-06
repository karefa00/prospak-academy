# Phase 3 — Inspection and implementation decision record

Date: 6 November 2026. Project: **Prospak Academy**. The user confirmed Phase 2 passed and explicitly authorised Phase 3 only.

## Verified starting state

- Recovered `prospak-academy-phase2.zip`, Git branch `feature/phase-2-people`, clean working tree.
- Inspected `0001_phase1_identity.sql`, `0002_phase2_people.sql`, cumulative export, authentication/guard, account repository/controller/service, routes, Hope UI templates and Phase 1/2 tests.
- Phase 2 contains four roles, a permission mapping, administrator-only account creation, student and lecturer extension profiles and private photos. Academic links do **not** yet exist.
- PHP CLI 8.4.24 available; `PDO` available; **PDO MySQL driver and MySQL server unavailable in the build environment**. SQL will require Ubuntu installation verification.

## Scope (approved Phase 3 only)

1. Add faculties, departments (FK to faculty), programmes (FK to department), academic levels (global configurable catalogue). Never prepopulate institution-specific programmes or levels.
2. Add nullable FK placements `students.programme_id`, `students.academic_level_id`, `lecturers.department_id` preserving Phase 2 users.
3. Show real, paginated faculty/department/programme/level lists with search/status filtering, create/edit/inactivate; no delete endpoints.
4. Show associated student lists through faculty -> department -> programme and global level with pagination and search.
5. Provide an explicit administrator-only academic placement form on existing Phase 2 account details. Do not change people creation, credential storage, profile editing, or student numbers.
6. Grant `academics.view` to administrators and academic officers and `academics.manage` to administrators only. Students and lecturers may view their own academic placement in existing self-profile but not global lists.
7. Add a separate `0003_phase3_academics.sql` migration and append it to the cumulative fresh-install SQL. Update README, Phase 3 checks, test guide and completion report.

## Business-rule assumptions and limits

- One institution. No department or programme is created by seeding a sample. Admin enters real institutional names and codes.
- Academic level codes and order are **not assumed** (e.g. `100` is sample test data only).
- A student belongs to at most one current programme and one current academic level. The student's department/faculty are derived through the programme (no denormalized student department field).
- A lecturer has at most one primary current department. Course-specific teaching assignments belong to Phase 5.
- Existing unassigned people remain valid. Inactivated academic records remain visible for historical integrity; they cannot be newly selected. Reactivations and changes to parent link require an active ancestor.
- Because future course/grade history may reference these entities, there is **no destructive delete**. Changes to a department's parent are blocked if programmes or lecturers are linked, and programme parent changes are blocked if any students are assigned.
- No academic calendar, courses, grading, dashboards, notifications, reports or transcript functionality has been introduced.

## Reuse and dependencies

- Reuse `Guard::requirePermission`, `AuthService`, `Csrf` in the front controller, centralized PDO connection and `View::escape`.
- Reuse existing portal Hope UI layout and sidebar; add templates under `templates/academics/`.
- Reuse `PeopleRepository::find()` to show derived academic labels on existing profiles.
- Phase 3 migration needs the completed Phase 2 database, not vice versa.
- Manual testing of real MySQL operations and authenticated browser actions is required before Phase 3 can be considered complete.
