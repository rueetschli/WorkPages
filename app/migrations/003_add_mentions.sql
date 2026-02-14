-- AP14: Smart Text Commands - Mentions table
-- Stores structured @mention references extracted from Markdown text fields.

CREATE TABLE IF NOT EXISTS mentions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    entity_type      ENUM('page','task','comment') NOT NULL,
    entity_id        INT NOT NULL,
    mentioned_user_id INT NOT NULL,
    created_by       INT NOT NULL,
    created_at       DATETIME NOT NULL,

    INDEX idx_mentions_user_date (mentioned_user_id, created_at),
    INDEX idx_mentions_entity (entity_type, entity_id),

    CONSTRAINT fk_mentions_user FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_mentions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update schema version
UPDATE app_meta SET meta_value = '3' WHERE meta_key = 'schema_version';
