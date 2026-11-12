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
