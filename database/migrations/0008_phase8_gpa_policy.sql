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
