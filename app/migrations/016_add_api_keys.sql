-- AP19: API Keys for REST API authentication
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    key_prefix CHAR(8) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    scopes VARCHAR(500) NOT NULL DEFAULT '',
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    INDEX idx_api_keys_user (user_id),
    INDEX idx_api_keys_prefix (key_prefix),
    CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
