#!/usr/bin/env python3
"""Static checks only. Real schema execution requires MySQL 8.x."""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[1]
sql = (root/'database/migrations/0001_phase1_identity.sql').read_text()
export = (root/'database/student_portal.sql').read_text()
assert sql in export
expected = {'roles','permissions','role_permissions','users','password_reset_tokens','login_attempts','audit_logs'}
actual = set(re.findall(r'CREATE TABLE (\w+) \(', sql))
assert actual == expected, (actual, expected)
assert all(f'ENGINE=InnoDB' in p for p in sql.split('CREATE TABLE ')[1:])
assert 'utf8mb4' in sql
assert 'INSERT INTO roles' in sql and 'administrator' in sql and 'student' in sql
assert 'INSERT INTO users' not in sql  # no default accounts or secrets
assert 'admin.access' in sql and "roles.slug = 'administrator'" in sql
assert 'CREATE TABLE students' not in sql  # Phase 2, not Phase 1
assert (root/'public/assets/css/hope-ui.min.css').stat().st_size > 100_000
assert (root/'public/assets/css/app.css').stat().st_size > 300
# An installed environment may legitimately have a private .env; verify ignore rule instead.
assert '/.env' in (root/'.gitignore').read_text()
routes = (root/'routes/auth.php').read_text()
assert "$router->get('/register'" not in routes and "$router->post('/register'" not in routes
assert "$router->get('/logout'" not in routes
print(f'PASS static schema/file checks: {len(expected)} Phase 1-only tables, seeded roles and permissions, no default users.')
