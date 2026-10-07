ALTER TABLE enquiries
    ADD COLUMN assigned_admin_id INT UNSIGNED NULL AFTER status,
    ADD COLUMN follow_up_at DATETIME NULL AFTER assigned_admin_id,
    ADD INDEX enquiries_assigned_admin_idx (assigned_admin_id),
    ADD CONSTRAINT enquiries_assigned_admin_fk
        FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE quote_requests
    ADD COLUMN assigned_admin_id INT UNSIGNED NULL AFTER status,
    ADD COLUMN follow_up_at DATETIME NULL AFTER assigned_admin_id,
    ADD INDEX quote_requests_assigned_admin_idx (assigned_admin_id),
    ADD CONSTRAINT quote_requests_assigned_admin_fk
        FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL;
