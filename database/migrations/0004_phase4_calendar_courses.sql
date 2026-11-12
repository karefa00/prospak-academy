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
