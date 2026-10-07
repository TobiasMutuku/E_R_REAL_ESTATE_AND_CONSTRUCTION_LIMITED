ALTER TABLE admin_users
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN last_login_at DATETIME NULL AFTER created_at,
    ADD INDEX admin_users_active_role_idx (is_active, role);

ALTER TABLE projects
    ADD COLUMN scope_summary TEXT NULL AFTER description,
    ADD COLUMN outcome_summary TEXT NULL AFTER scope_summary;

ALTER TABLE enquiries
    ADD COLUMN notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown' AFTER status;

ALTER TABLE quote_requests
    ADD COLUMN notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown' AFTER status;

CREATE TABLE academy_enquiries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(201) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    course VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('New', 'Read', 'Responded', 'Closed') NOT NULL DEFAULT 'New',
    assigned_admin_id INT UNSIGNED NULL,
    follow_up_at DATETIME NULL,
    notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX academy_enquiries_status_created_idx (status, created_at),
    INDEX academy_enquiries_assigned_admin_idx (assigned_admin_id),
    CONSTRAINT academy_enquiries_assigned_admin_fk
        FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX admin_password_resets_user_expiry_idx (admin_user_id, expires_at),
    CONSTRAINT admin_password_resets_user_fk
        FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id INT UNSIGNED NULL,
    actor_name VARCHAR(150) NOT NULL,
    actor_role VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    remote_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX admin_audit_logs_created_idx (created_at),
    INDEX admin_audit_logs_actor_idx (actor_admin_id, created_at),
    INDEX admin_audit_logs_entity_idx (entity_type, entity_id, created_at),
    CONSTRAINT admin_audit_logs_actor_fk
        FOREIGN KEY (actor_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
