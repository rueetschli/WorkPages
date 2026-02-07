CREATE TABLE IF NOT EXISTS `activity` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id`   INT NOT NULL,
    `action`      VARCHAR(50) NOT NULL,
    `meta_json`   TEXT NULL DEFAULT NULL,
    `created_by`  INT NOT NULL,
    `created_at`  DATETIME NOT NULL,
    INDEX `idx_activity_entity` (`entity_type`, `entity_id`),
    INDEX `idx_activity_created_by` (`created_by`),
    CONSTRAINT `fk_activity_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
