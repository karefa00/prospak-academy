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
