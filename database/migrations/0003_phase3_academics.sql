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
