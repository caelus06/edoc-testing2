-- compliance_tables.sql
-- Non-Compliant User Management Module — schema additions

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compliance_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT NOT NULL,
    notification_type ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'MANUAL',
    channel ENUM('EMAIL','IN_APP','BOTH') NOT NULL DEFAULT 'BOTH',
    message TEXT NOT NULL,
    sent_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cn_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_cn_sender FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_cn_user_request (user_id, request_id),
    INDEX idx_cn_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('compliance_threshold_days', '7'),
    ('compliance_cooldown_hours', '48'),
    ('smtp_email', ''),
    ('smtp_app_password', ''),
    ('smtp_sender_name', 'E-Doc System')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
