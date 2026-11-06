"""Phase 3 SQL and source guardrails. No DB driver or network required."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
m=(root/'database/migrations/0003_phase3_academics.sql').read_text()
s=(root/'database/student_portal.sql').read_text()
for table in ('faculties','departments','programmes','academic_levels'):
    assert f'CREATE TABLE {table} (' in m, table
    assert s.count(f'CREATE TABLE {table} (')==1, f'cumulative schema {table}'
for fk in ('fk_departments_faculty','fk_programmes_department','fk_students_programme','fk_students_level','fk_lecturers_department'):
    assert fk in m and fk in s
for key in ('academics.view','academics.manage'):
    assert key in m and key in s
assert 'ADD COLUMN programme_id' in m and 'ADD COLUMN department_id' in m
assert 'DELETE FROM students' not in m and 'DROP TABLE' not in m
assert 'ON DELETE RESTRICT' in m
for file in ('app/Core/AcademicValidation.php','app/Controllers/AcademicController.php','app/Services/AcademicService.php','app/Repositories/AcademicRepository.php','templates/academics/list.php','templates/academics/form.php','templates/academics/students.php','templates/academics/placement.php'):
    assert (root/file).is_file(),file
routes=(root/'routes/web.php').read_text()
assert 'academicController' in routes and "'/academics/students'" in routes
assert "'academics.manage'" in (root/'app/Controllers/AcademicController.php').read_text()
assert 'FOR UPDATE' in (root/'app/Services/AcademicService.php').read_text()
assert "Csrf::token()" in (root/'templates/academics/form.php').read_text()
assert "Csrf::token()" in (root/'templates/academics/placement.php').read_text()
print('PASS Phase 3 static/schema/source checks')
