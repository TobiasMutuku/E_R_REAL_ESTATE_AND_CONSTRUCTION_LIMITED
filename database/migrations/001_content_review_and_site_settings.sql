ALTER TABLE projects
    ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER image;

ALTER TABLE properties
    ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER image;

CREATE TABLE site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('company_name', 'E&R Real Estate and Construction Limited'),
    ('public_phone', '+254 748 766 822'),
    ('public_email', 'errealestateconstruction@gmail.com'),
    ('office_address', 'Watamu Mall, Office 34, 1st Floor, Watamu, Kilifi County'),
    ('whatsapp_phone', '254748766822')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
