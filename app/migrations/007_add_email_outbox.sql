-- AP15: Email outbox / queue
-- Shared-hosting friendly email queue processed via admin route or cron.

CREATE TABLE IF NOT EXISTS email_outbox (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    notification_id  INT NULL,
    to_email         VARCHAR(190) NOT NULL,
    subject          VARCHAR(190) NOT NULL,
    body_html        LONGTEXT NOT NULL,
    body_text        LONGTEXT NOT NULL,
    status           ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts         INT NOT NULL DEFAULT 0,
    last_error       VARCHAR(500) NULL,
    send_after       DATETIME NOT NULL,
    sent_at          DATETIME NULL,
    created_at       DATETIME NOT NULL,

    INDEX idx_outbox_status (status, send_after),
    INDEX idx_outbox_user (user_id, created_at),

    CONSTRAINT fk_outbox_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_outbox_notif FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE app_meta SET meta_value = '7' WHERE meta_key = 'schema_version';
