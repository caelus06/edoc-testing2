-- database/audit_logs.sql
-- Universal audit log table for tracking all system actions

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT            NULL COMMENT 'FK to users.id — NULL for unauthenticated actions (signup)',
    action      VARCHAR(10)    NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, LOGOUT',
    table_name  VARCHAR(50)    NOT NULL COMMENT 'The database table affected',
    record_id   INT            NULL COMMENT 'PK of the affected row — NULL for login/logout',
    details     TEXT           NULL COMMENT 'Human-readable context about the action',
    ip_address  VARCHAR(45)    NULL COMMENT 'Client IP address (supports IPv6)',
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user    (user_id),
    INDEX idx_audit_action  (action),
    INDEX idx_audit_table   (table_name),
    INDEX idx_audit_date    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
