-- PROSPAK ACADEMY — CLEAN NEW INSTALL ONLY, MySQL 8.x
-- DO NOT IMPORT INTO AN EXISTING DATABASE; it would duplicate tables.
-- Timestamps are stored in UTC.
CREATE DATABASE IF NOT EXISTS `prospak_academy` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `prospak_academy`;


-- 0001_phase1_identity.sql
-- Prospak Academy / Phase 1 only. Import into an existing empty prospak_academy database.
-- MySQL 8.x / InnoDB / utf8mb4. This migration is not designed to be rerun.
-- UTC session time zone is enforced by the PHP PDO connection.

CREATE TABLE roles (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(40) NOT NULL,
 display_name VARCHAR(80) NOT NULL,
 UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(80) NOT NULL,
 description VARCHAR(180) NOT NULL,
 UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
 role_id SMALLINT UNSIGNED NOT NULL,
 permission_id SMALLINT UNSIGNED NOT NULL,
 PRIMARY KEY (role_id, permission_id),
 CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 role_id SMALLINT UNSIGNED NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 account_status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
 session_version INT UNSIGNED NOT NULL DEFAULT 1,
 last_login_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_users_email (email),
 KEY idx_users_role_status (role_id, account_status),
 CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
 CONSTRAINT chk_users_session_version CHECK (session_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_reset_selector (selector),
 KEY idx_reset_user (user_id),
 KEY idx_reset_expiry (expires_at),
 CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 attempt_type ENUM('login', 'reset_request') NOT NULL,
 identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_attempt_ident (attempt_type, identifier_hash, occurred_at),
 KEY idx_attempt_ip (attempt_type, ip_hash, occurred_at),
 KEY idx_attempt_expiry (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NULL,
 subject_user_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(80) NOT NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_audit_actor (actor_user_id, occurred_at),
 KEY idx_audit_subject (subject_user_id, occurred_at),
 KEY idx_audit_type_time (event_type, occurred_at),
 CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_audit_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (slug, display_name) VALUES
 ('administrator', 'Administrator'),
 ('academic_officer', 'Academic Officer'),
 ('lecturer', 'Lecturer'),
 ('student', 'Student');

INSERT INTO permissions (slug, description) VALUES
 ('dashboard.view', 'Access the Phase 1 signed-in landing page'),
 ('auth.change_password', 'Change the current account password'),
 ('admin.access', 'Access the administrator-only Phase 1 authorization checkpoint');

INSERT INTO role_permissions (role_id, permission_id)
 SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
 WHERE permissions.slug IN ('dashboard.view', 'auth.change_password');

INSERT INTO role_permissions (role_id, permission_id)
 SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
 WHERE roles.slug = 'administrator' AND permissions.slug = 'admin.access';


-- 0002_phase2_people.sql
-- Phase 2 incremental migration. Apply ONCE to an existing, backed-up Phase 1 database.
-- Does not erase or alter existing users' passwords, roles, or data.
ALTER TABLE users
 ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER account_status,
 ADD CONSTRAINT chk_users_must_change_password CHECK (must_change_password IN (0, 1));

CREATE TABLE students (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 matriculation_number VARCHAR(40) NOT NULL,
 first_name VARCHAR(75) NOT NULL,
 last_name VARCHAR(75) NOT NULL,
 phone VARCHAR(30) NULL,
 gender ENUM('female','male','other','prefer_not_to_say') NULL,
 date_of_birth DATE NULL,
 admission_year YEAR NULL,
 passport_filename VARCHAR(100) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_students_user (user_id),
 UNIQUE KEY uq_students_matric (matriculation_number),
 KEY idx_students_names (last_name, first_name),
 KEY idx_students_admission (admission_year),
 CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 staff_number VARCHAR(40) NOT NULL,
 first_name VARCHAR(75) NOT NULL,
 last_name VARCHAR(75) NOT NULL,
 phone VARCHAR(30) NULL,
 designation VARCHAR(120) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_lecturers_user (user_id),
 UNIQUE KEY uq_lecturers_staff (staff_number),
 KEY idx_lecturers_names (last_name, first_name),
 CONSTRAINT fk_lecturers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, description)
VALUES ('users.manage', 'Create, edit and suspend user and student/lecturer profiles');
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'administrator' AND p.slug = 'users.manage';


-- 0003_phase3_academics.sql
-- Prospak Academy Phase 3. Apply ONCE after the Phase 2 migration to a backed-up database.
-- Do not import the cumulative student_portal.sql into an existing installation.
-- InnoDB constraints and nullable links preserve existing people and identifiers.

CREATE TABLE faculties (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) NOT NULL,
 name VARCHAR(150) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_faculties_code (code),
 UNIQUE KEY uq_faculties_name (name),
 KEY idx_faculties_status_name (status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 faculty_id INT UNSIGNED NOT NULL,
 code VARCHAR(20) NOT NULL,
 name VARCHAR(150) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_departments_code (code),
 UNIQUE KEY uq_departments_faculty_name (faculty_id,name),
 KEY idx_departments_status_name (status,name),
 CONSTRAINT fk_departments_faculty FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE programmes (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 department_id INT UNSIGNED NOT NULL,
 code VARCHAR(30) NOT NULL,
 name VARCHAR(150) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_programmes_code (code),
 UNIQUE KEY uq_programmes_department_name (department_id,name),
 KEY idx_programmes_status_name (status,name),
 CONSTRAINT fk_programmes_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_levels (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) NOT NULL,
 name VARCHAR(150) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_levels_code (code),
 UNIQUE KEY uq_levels_name (name),
 UNIQUE KEY uq_levels_sort (sort_order),
 KEY idx_levels_status_sort (status,sort_order),
 CONSTRAINT chk_levels_sort CHECK (sort_order >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE students
 ADD COLUMN programme_id INT UNSIGNED NULL AFTER admission_year,
 ADD COLUMN academic_level_id INT UNSIGNED NULL AFTER programme_id,
 ADD KEY idx_students_programme (programme_id),
 ADD KEY idx_students_level (academic_level_id),
 ADD CONSTRAINT fk_students_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE RESTRICT,
 ADD CONSTRAINT fk_students_level FOREIGN KEY (academic_level_id) REFERENCES academic_levels(id) ON DELETE RESTRICT;

ALTER TABLE lecturers
 ADD COLUMN department_id INT UNSIGNED NULL AFTER designation,
 ADD KEY idx_lecturers_department (department_id),
 ADD CONSTRAINT fk_lecturers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT;

INSERT INTO permissions (slug,description) VALUES
 ('academics.view','View academic organisation and enrolled students'),
 ('academics.manage','Create and update faculties departments programmes and levels and academic placement');

INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.slug IN ('administrator','academic_officer') AND p.slug='academics.view';

INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.slug='administrator' AND p.slug='academics.manage';


-- 0004_phase4_calendar_courses.sql
-- Prospak Academy Phase 4 — run ONCE against a backed-up Phase 3 database.
-- Import this migration into existing installations. Do NOT import student_portal.sql there.
-- All Phase 3 organisation IDs use INT UNSIGNED; this migration matches that exactly.
-- Date-only academic periods are calendar dates (not timezone-converted timestamps).
-- All created_at/updated_at DATETIME values are UTC via the PDO session time_zone.

CREATE TABLE academic_sessions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name CHAR(9) NOT NULL,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 registration_deadline DATE NULL,
 exam_starts_on DATE NULL,
 exam_ends_on DATE NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
 active_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_sessions_name (name),
 UNIQUE KEY uq_sessions_one_active (active_guard),
 KEY idx_sessions_dates (starts_on,ends_on),
 CONSTRAINT chk_session_dates CHECK (starts_on <= ends_on),
 CONSTRAINT chk_session_registration CHECK (registration_deadline IS NULL OR registration_deadline BETWEEN starts_on AND ends_on),
 CONSTRAINT chk_session_exam CHECK ((exam_starts_on IS NULL AND exam_ends_on IS NULL) OR (exam_starts_on IS NOT NULL AND exam_ends_on IS NOT NULL AND exam_starts_on BETWEEN starts_on AND ends_on AND exam_ends_on BETWEEN exam_starts_on AND ends_on))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 academic_session_id INT UNSIGNED NOT NULL,
 semester_number TINYINT UNSIGNED NOT NULL,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 registration_deadline DATE NULL,
 exam_starts_on DATE NULL,
 exam_ends_on DATE NULL,
 grading_starts_on DATE NULL,
 grading_ends_on DATE NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
 active_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_semesters_session_number (academic_session_id,semester_number),
 UNIQUE KEY uq_semesters_one_active (active_guard),
 KEY idx_semesters_dates (academic_session_id,starts_on,ends_on),
 CONSTRAINT fk_semesters_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT chk_semester_number CHECK (semester_number IN (1,2)),
 CONSTRAINT chk_semester_dates CHECK (starts_on <= ends_on),
 CONSTRAINT chk_semester_registration CHECK (registration_deadline IS NULL OR registration_deadline BETWEEN starts_on AND ends_on),
 CONSTRAINT chk_semester_exam CHECK ((exam_starts_on IS NULL AND exam_ends_on IS NULL) OR (exam_starts_on IS NOT NULL AND exam_ends_on IS NOT NULL AND exam_starts_on BETWEEN starts_on AND ends_on AND exam_ends_on BETWEEN exam_starts_on AND ends_on)),
 CONSTRAINT chk_semester_grading CHECK ((grading_starts_on IS NULL AND grading_ends_on IS NULL) OR (grading_starts_on IS NOT NULL AND grading_ends_on IS NOT NULL AND grading_starts_on <= grading_ends_on))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) NOT NULL,
 title VARCHAR(200) NOT NULL,
 credit_units TINYINT UNSIGNED NOT NULL,
 department_id INT UNSIGNED NOT NULL,
 academic_level_id INT UNSIGNED NOT NULL,
 semester_number TINYINT UNSIGNED NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_courses_code (code),
 KEY idx_courses_catalogue (department_id,academic_level_id,semester_number,status,code),
 KEY idx_courses_level (academic_level_id),
 CONSTRAINT fk_courses_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
 CONSTRAINT fk_courses_level FOREIGN KEY (academic_level_id) REFERENCES academic_levels(id) ON DELETE RESTRICT,
 CONSTRAINT chk_course_credit CHECK (credit_units >= 1 AND credit_units <= 99),
 CONSTRAINT chk_course_semester CHECK (semester_number IN (1,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_programmes (
 course_id INT UNSIGNED NOT NULL,
 programme_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (course_id,programme_id),
 KEY idx_cp_programme (programme_id,course_id),
 CONSTRAINT fk_cp_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cp_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_prerequisites (
 course_id INT UNSIGNED NOT NULL,
 prerequisite_course_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (course_id,prerequisite_course_id),
 KEY idx_pre_course (prerequisite_course_id,course_id),
 CONSTRAINT fk_pre_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pre_required FOREIGN KEY (prerequisite_course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT chk_pre_not_self CHECK (course_id <> prerequisite_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, description) VALUES
 ('calendar.view','View academic sessions and semester calendar'),
 ('calendar.manage','Manage sessions and semesters and their activation'),
 ('courses.view','View course catalogue and prerequisites'),
 ('courses.manage','Create and update courses and prerequisites');

INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.slug IN ('administrator','academic_officer') AND p.slug IN ('calendar.view','courses.view');

INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.slug='administrator' AND p.slug IN ('calendar.manage','courses.manage');


-- 0005_phase5_assignment_registration.sql
-- Phase 5 only. Import ONCE into a backed-up Phase 4 MySQL 8 database.
-- Existing users/students/lecturers/calendar/course data are unchanged.
CREATE TABLE course_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 semester_id INT UNSIGNED NOT NULL,
 lecturer_id BIGINT UNSIGNED NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_assignment (course_id,semester_id,lecturer_id),
 KEY idx_assignment_lecturer (lecturer_id,semester_id,status),
 KEY idx_assignment_semester (semester_id,course_id),
 CONSTRAINT fk_assignment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_assignment_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
 CONSTRAINT fk_assignment_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_registrations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 student_id BIGINT UNSIGNED NOT NULL,
 course_id INT UNSIGNED NOT NULL,
 semester_id INT UNSIGNED NOT NULL,
 status ENUM('registered','dropped') NOT NULL DEFAULT 'registered',
 registered_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 dropped_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_registration (student_id,course_id,semester_id),
 KEY idx_registration_student (student_id,semester_id,status),
 KEY idx_registration_offering (semester_id,course_id,status),
 CONSTRAINT fk_registration_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_registration_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_registration_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_registration_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 registration_id BIGINT UNSIGNED NOT NULL,
 action ENUM('registered','dropped','re_registered') NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_registration_event_history (registration_id,occurred_at,id),
 CONSTRAINT fk_reg_event_registration FOREIGN KEY (registration_id) REFERENCES course_registrations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_reg_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug,description) VALUES
 ('assignments.view','View appropriately scoped lecturer course assignments'),
 ('assignments.manage','Manage lecturer assignments to courses and semesters'),
 ('registrations.view','View course registrations within permitted role scope'),
 ('registrations.self','Register and drop own eligible courses');
INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE (r.slug IN ('administrator','academic_officer') AND p.slug IN ('assignments.view','assignments.manage','registrations.view'))
    OR (r.slug='lecturer' AND p.slug='assignments.view')
    OR (r.slug='student' AND p.slug IN ('registrations.view','registrations.self'));


-- 0006_phase6_assessment_grades.sql
-- Phase 6 only. Import ONCE after migration 0005. Scores/results remain DRAFT until Phase 7.
-- Grading schemes are deliberately not seeded or activated: an institution must configure its policy.
CREATE TABLE grading_schemes (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
 active_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_grading_scheme_name (name),
 UNIQUE KEY uq_grading_one_active (active_guard)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE grading_bands (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 grading_scheme_id INT UNSIGNED NOT NULL,
 min_score DECIMAL(5,2) NOT NULL,
 max_score DECIMAL(5,2) NOT NULL,
 grade VARCHAR(3) NOT NULL,
 grade_point DECIMAL(4,2) NOT NULL,
 UNIQUE KEY uq_band_start (grading_scheme_id,min_score),
 UNIQUE KEY uq_band_grade (grading_scheme_id,grade),
 CONSTRAINT fk_band_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes(id) ON DELETE RESTRICT,
 CONSTRAINT chk_band_bounds CHECK (min_score >= 0 AND max_score <= 100 AND min_score <= max_score),
 CONSTRAINT chk_band_point CHECK (grade_point >= 0 AND grade_point <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE assessments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 semester_id INT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 component_type ENUM('ca','exam') NOT NULL,
 max_score DECIMAL(5,2) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_assessment (course_id,semester_id,name),
 KEY idx_assessment_offering (semester_id,course_id,status),
 CONSTRAINT fk_assessment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_assessment_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
 CONSTRAINT chk_assessment_max CHECK (max_score > 0 AND max_score <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE student_scores (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 registration_id BIGINT UNSIGNED NOT NULL,
 assessment_id BIGINT UNSIGNED NOT NULL,
 score DECIMAL(5,2) NOT NULL,
 entered_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_score_registration_assessment (registration_id,assessment_id),
 KEY idx_score_assessment (assessment_id),
 CONSTRAINT fk_score_registration FOREIGN KEY (registration_id) REFERENCES course_registrations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_score_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE RESTRICT,
 CONSTRAINT fk_score_entered_by FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT chk_score_nonnegative CHECK (score >= 0 AND score <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE offering_grading_schemes (
 course_id INT UNSIGNED NOT NULL,
 semester_id INT UNSIGNED NOT NULL,
 grading_scheme_id INT UNSIGNED NOT NULL,
 PRIMARY KEY (course_id,semester_id),
 CONSTRAINT fk_offering_scheme_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_offering_scheme_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
 CONSTRAINT fk_offering_scheme_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 registration_id BIGINT UNSIGNED NOT NULL,
 grading_scheme_id INT UNSIGNED NOT NULL,
 total_score DECIMAL(5,2) NOT NULL,
 grade VARCHAR(3) NOT NULL,
 grade_point DECIMAL(4,2) NOT NULL,
 quality_points DECIMAL(7,2) NOT NULL,
 status ENUM('draft','submitted','approved','rejected','published') NOT NULL DEFAULT 'draft',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_result_registration (registration_id),
 KEY idx_result_status (status,id),
 CONSTRAINT fk_result_registration FOREIGN KEY (registration_id) REFERENCES course_registrations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_result_scheme FOREIGN KEY (grading_scheme_id) REFERENCES grading_schemes(id) ON DELETE RESTRICT,
 CONSTRAINT chk_result_score CHECK (total_score >= 0 AND total_score <= 100),
 CONSTRAINT chk_result_point CHECK (grade_point >= 0 AND grade_point <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug,description) VALUES
 ('assessments.manage','Configure assessment components and grading schemes'),
 ('grades.view','View scoped assessment and draft grades'),
 ('grades.enter','Enter draft scores for assigned courses');
INSERT INTO role_permissions (role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE (r.slug IN ('administrator','academic_officer') AND p.slug='grades.view')
    OR (r.slug='administrator' AND p.slug='assessments.manage')
    OR (r.slug='lecturer' AND p.slug IN ('grades.view','grades.enter'));


-- 0007_phase7_results_workflow.sql
-- Phase 7: apply once after 0006, against a backed-up existing database.
-- Do not re-import the cumulative student_portal.sql into a populated installation.
ALTER TABLE results ADD COLUMN credit_units_snapshot TINYINT UNSIGNED NULL AFTER quality_points,
 ADD CONSTRAINT chk_result_credit_snapshot CHECK (credit_units_snapshot IS NULL OR credit_units_snapshot BETWEEN 1 AND 99);
CREATE TABLE result_batches (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id INT UNSIGNED NOT NULL,
 semester_id INT UNSIGNED NOT NULL,
 status ENUM('submitted','approved','rejected','published') NOT NULL,
 submitted_by BIGINT UNSIGNED NOT NULL,
 submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_result_batch_offering(course_id,semester_id),
 KEY idx_result_batches_status(status,id),
 CONSTRAINT fk_batch_course FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_batch_semester FOREIGN KEY(semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
 CONSTRAINT fk_batch_submitter FOREIGN KEY(submitted_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE result_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 reviewer_user_id BIGINT UNSIGNED NOT NULL,
 decision ENUM('approved','rejected') NOT NULL,
 reason VARCHAR(1000) NULL,
 reviewed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_result_reviews_batch(batch_id,reviewed_at,id),
 CONSTRAINT fk_review_batch FOREIGN KEY(batch_id) REFERENCES result_batches(id) ON DELETE RESTRICT,
 CONSTRAINT fk_review_actor FOREIGN KEY(reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT chk_review_reason CHECK (decision <> 'rejected' OR (reason IS NOT NULL AND CHAR_LENGTH(reason)>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE result_publications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 published_by BIGINT UNSIGNED NOT NULL,
 published_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_publication_batch(batch_id),
 CONSTRAINT fk_publication_batch FOREIGN KEY(batch_id) REFERENCES result_batches(id) ON DELETE RESTRICT,
 CONSTRAINT fk_publication_actor FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO permissions(slug,description) VALUES
 ('results.submit','Submit own assigned offering results for academic review'),
 ('results.review','Approve or reject lecturer-submitted results'),
 ('results.publish','Publish academically approved results'),
 ('results.view','View appropriately scoped result workflow and history');
INSERT INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE
 (r.slug='lecturer' AND p.slug IN ('results.submit','results.view'))
 OR (r.slug='academic_officer' AND p.slug IN ('results.review','results.view'))
 OR (r.slug='administrator' AND p.slug IN ('results.publish','results.view'));


-- 0008_phase8_gpa_policy.sql
-- Phase 8: explicit institutional rules; no automatic guessed repeat or pass policy.
CREATE TABLE academic_policies (
 policy_key VARCHAR(60) NOT NULL PRIMARY KEY,
 policy_value VARCHAR(120) NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_policy_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO academic_policies(policy_key,policy_value) VALUES
 ('repeat_policy',NULL),
 ('prerequisite_min_grade_point',NULL);
INSERT INTO permissions(slug,description) VALUES
 ('gpa.view','View own or institutionally scoped GPA and CGPA'),
 ('gpa.policy','Manage institutional grade calculation and prerequisite policies');
INSERT INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE
 (r.slug IN ('administrator','academic_officer','student') AND p.slug='gpa.view')
 OR (r.slug='administrator' AND p.slug='gpa.policy');
