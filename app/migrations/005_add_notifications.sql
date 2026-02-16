-- AP15: Notifications table
-- In-app notification storage with read/unread state and deep link support.

CREATE TABLE IF NOT EXISTS notifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    type             VARCHAR(50) NOT NULL,
    priority         TINYINT NOT NULL DEFAULT 3,
    entity_type      ENUM('page','task','comment') NOT NULL,
    entity_id        INT NOT NULL,
    actor_user_id    INT NOT NULL,
    title            VARCHAR(190) NOT NULL,
    body             VARCHAR(500) NULL,
    url              VARCHAR(255) NOT NULL,
    is_read          TINYINT NOT NULL DEFAULT 0,
    read_at          DATETIME NULL,
    is_emailed       TINYINT NOT NULL DEFAULT 0,
    dedupe_key       VARCHAR(80) NULL,
    created_at       DATETIME NOT NULL,

    INDEX idx_notif_user_unread (user_id, is_read, created_at),
    INDEX idx_notif_entity (entity_type, entity_id),
    INDEX idx_notif_actor (actor_user_id),
    INDEX idx_notif_user_date (user_id, created_at),
    INDEX idx_notif_dedupe (user_id, dedupe_key),

    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE app_meta SET meta_value = '5' WHERE meta_key = 'schema_version';
