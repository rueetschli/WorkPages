-- AP19: API audit log for tracking API usage
CREATE TABLE IF NOT EXISTS api_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_prefix CHAR(8) NOT NULL,
    user_id INT NOT NULL,
    method VARCHAR(10) NOT NULL,
    route VARCHAR(255) NOT NULL,
    status_code INT NOT NULL,
    duration_ms INT NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_prefix (key_prefix),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
