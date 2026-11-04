# Phase 2 preparation — 2026-11-04

**Authorization:** User explicitly confirmed Phase 1 passed and instructed Phase 2 implementation. The 14-phase roadmap is unchanged. No Phase 3 or later modules are authorized.

## Existing application inspected
- Phase 1 branch `feature/phase-1-authentication`, commit `b14ef5b`, clean working tree, no GitHub remote.
- Front controller `public/index.php`, GET/POST fixed-path `Router`, Phase 1 CSRF global POST checks, `AuthService`, `UsersRepository`, `Guard`, reusable Hope UI views and `users` / `roles` / `permissions` / `audit_logs` schema.
- Existing `students` / `lecturers` tables, CRUD routes, academic-organisation tables: none. No live MySQL accessible in this build environment.

## Deliverable and dependencies
- Depends on Phase 1 passed authentication and database migration.
- Phase 2 creates/administers accounts for the four roles, students and lecturers; updates identity profiles; suspends/reactivates accounts; offers one-time temporary passwords and mandatory first-login password changes; searches/paginates accounts; securely stores optional student passport photographs; exposes read-only personal profile.
- `users` existing fields remain the single authority for email, account status, role and password. Student/lecturer extension tables have a unique `user_id` FK.
- **Phase 3 is required** before faculty, department, programme and academic-level foreign keys can be established. Do not create orphaned or pretend academic organisation data.
- **No official matriculation/staff numbering policy supplied.** Administrator enters the institution-issued identifier; server normalizes and enforces uniqueness. This deliberate limitation avoids inventing policy. Automated number generation is deferred pending approval of official format.
- Existing student/lecturer role cannot convert into another type; conversions into a profile-bearing role are also blocked to protect identity and later academic history. Admin↔academic-officer transitions are supported, with protections against self-demotion and losing the last active administrator.
- Password reset by administrator generates an unpredictable bcrypt-hashed temporary secret displayed once; first login is forced to `/change-password`. This requires small, documented changes in Phase 1 `AuthService`, `AuthController`, `Guard`, and `UsersRepository`, not a new authentication system.
- Account creation and updates are transactional. Access control and record ownership are checked on the server. No public account creation route.

## Expected modifications
- Migration `database/migrations/0002_phase2_people.sql`; append Phase 2 to fresh-install `database/student_portal.sql` without altering Phase 1 migration.
- New `PeopleValidation`, `PeopleRepository`, `PeopleService`, `PeopleController`; new `templates/people/{list,form,detail}.php`; new `public/assets/js/people-form.js` and private photo storage.
- Phase 1 compatibility edits to `UsersRepository`, `AuthService`, `AuthController`, `Guard` for first-password-change requirement.
- New Phase 2 routes, registered controller, shared sidebar and styling changes, phase2 tests/documentation.

## Security and verification plan
- Verify `users.manage` and strict administrator role; CSRF POSTs; allowlisted role/status; mandatory role-specific fields; unique indexes, FKs, prepare/bind SQL, pagination integer binds; prevent self-suspension/self-demotion; invalidate sessions on status/role/email/temp-secret changes; upload `finfo`+image validation, 2 MiB cap, random server filenames and private storage.
- Run PHP lints, Phase 1 regression unit/static checks, Phase 2 unit/static tests, JS syntax and unauthenticated HTTP smoke tests. If real MySQL isn't available, explicitly label integration, stored records, permission enforcement *for logged-in accounts*, upload and login flows as unverified, with full manual Ubuntu/MySQL guide.

## Git workflow
- Create `feature/phase-2-people` from Phase 1 accepted branch. Commit focused changes. No GitHub push without remote/authorization.
