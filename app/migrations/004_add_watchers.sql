-- AP15: Watchers table
-- Allows users to subscribe to pages and tasks for notification delivery.

CREATE TABLE IF NOT EXISTS watchers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    entity_type      ENUM('page','task') NOT NULL,
    entity_id        INT NOT NULL,
    user_id          INT NOT NULL,
    created_at       DATETIME NOT NULL,

    UNIQUE KEY uq_watcher (entity_type, entity_id, user_id),
    INDEX idx_watchers_user (user_id),
    INDEX idx_watchers_entity (entity_type, entity_id),

    CONSTRAINT fk_watchers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE app_meta SET meta_value = '4' WHERE meta_key = 'schema_version';
