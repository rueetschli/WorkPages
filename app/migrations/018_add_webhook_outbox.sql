-- AP19: Webhook outbox for async delivery
CREATE TABLE IF NOT EXISTS webhook_outbox (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_endpoint_id INT NOT NULL,
    event_name VARCHAR(50) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status ENUM('pending','sent','failed','dead') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL,
    last_error VARCHAR(500) NULL,
    last_http_status INT NULL,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    INDEX idx_webhook_outbox_status (status, next_attempt_at),
    INDEX idx_webhook_outbox_endpoint (webhook_endpoint_id),
    CONSTRAINT fk_webhook_outbox_endpoint FOREIGN KEY (webhook_endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
