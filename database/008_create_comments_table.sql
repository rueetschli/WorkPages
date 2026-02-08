-- AP8: Comments table for Pages and Tasks
CREATE TABLE IF NOT EXISTS `comments` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('page','task') NOT NULL,
    `entity_id`   INT NOT NULL,
    `body_md`     LONGTEXT NOT NULL,
    `created_by`  INT NOT NULL,
    `created_at`  DATETIME NOT NULL,
    `updated_at`  DATETIME NULL DEFAULT NULL,
    `deleted_at`  DATETIME NULL DEFAULT NULL,
    INDEX `idx_comments_entity` (`entity_type`, `entity_id`, `created_at`),
    INDEX `idx_comments_created_by` (`created_by`),
    CONSTRAINT `fk_comments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AP8: Add created_at index to activity table for better sorting performance
ALTER TABLE `activity` ADD INDEX `idx_activity_entity_time` (`entity_type`, `entity_id`, `created_at`);

-- AP8: Add action index to activity table
ALTER TABLE `activity` ADD INDEX `idx_activity_action` (`action`);
