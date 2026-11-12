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
