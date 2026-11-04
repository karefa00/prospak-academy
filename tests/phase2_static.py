#!/usr/bin/env python3
"""Static checks are not MySQL execution or end-to-end proof."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
phase1=(root/'database/migrations/0001_phase1_identity.sql').read_text()
phase2=(root/'database/migrations/0002_phase2_people.sql').read_text()
snapshot=(root/'database/student_portal.sql').read_text()
assert phase1 in snapshot and phase2 in snapshot, 'reproducible full SQL export out of sync'
assert len(set(re.findall(r'CREATE TABLE (\w+) \(',phase1))) == 7
assert set(re.findall(r'CREATE TABLE (\w+) \(',phase2)) == {'students','lecturers'}
assert phase2.count('ENGINE=InnoDB')==2
assert 'must_change_password' in phase2
assert "'users.manage'" in phase2
assert 'FOREIGN KEY (user_id) REFERENCES users(id)' in phase2
assert 'UNIQUE KEY uq_students_matric' in phase2 and 'UNIQUE KEY uq_lecturers_staff' in phase2
assert 'INSERT INTO users' not in phase2 and 'INSERT INTO students' not in phase2
assert '/storage/uploads/*' in (root/'.gitignore').read_text()
assert (root/'public/assets/js/people-form.js').exists()
assert not (root/'public/uploads').exists()
assert (root/'storage/uploads/student_photos').is_dir()
routes=(root/'routes/web.php').read_text()
assert "$router->post('/admin/users/new'" in routes
assert "$router->post('/admin/users/status'" in routes
assert "$router->post('/admin/users/photo'" in routes
assert '$router->get(\'/profile\'' in routes
assert "$router->get('/register'" not in (root/'routes/auth.php').read_text()
print('PASS Phase 2 static checks: 2 new tables, 1 user column, new permission, cumulative SQL snapshot, secure routes/assets.')
