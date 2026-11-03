CREATE DATABASE IF NOT EXISTS `prospak_academy` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `prospak_academy`;

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
