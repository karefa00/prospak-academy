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
