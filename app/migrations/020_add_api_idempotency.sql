-- AP19: Idempotency key storage for POST endpoints
CREATE TABLE IF NOT EXISTS api_idempotency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_prefix CHAR(8) NOT NULL,
    idempotency_key VARCHAR(80) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_code INT NOT NULL,
    response_body LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_idempotency (key_prefix, idempotency_key),
    INDEX idx_idempotency_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
