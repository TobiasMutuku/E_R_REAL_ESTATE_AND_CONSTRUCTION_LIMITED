ALTER TABLE enquiries
    MODIFY COLUMN notification_status ENUM('unknown', 'pending', 'sent', 'failed') NOT NULL DEFAULT 'unknown';
ALTER TABLE quote_requests
    MODIFY COLUMN notification_status ENUM('unknown', 'pending', 'sent', 'failed') NOT NULL DEFAULT 'unknown';
ALTER TABLE academy_enquiries
    MODIFY COLUMN notification_status ENUM('unknown', 'pending', 'sent', 'failed') NOT NULL DEFAULT 'unknown';

UPDATE enquiries SET notification_status = 'unknown' WHERE notification_status = 'pending';
UPDATE quote_requests SET notification_status = 'unknown' WHERE notification_status = 'pending';
UPDATE academy_enquiries SET notification_status = 'unknown' WHERE notification_status = 'pending';

ALTER TABLE enquiries
    MODIFY COLUMN notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown';
ALTER TABLE quote_requests
    MODIFY COLUMN notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown';
ALTER TABLE academy_enquiries
    MODIFY COLUMN notification_status ENUM('unknown', 'sent', 'failed') NOT NULL DEFAULT 'unknown';
