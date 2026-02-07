CREATE TABLE IF NOT EXISTS `pages` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `title`       VARCHAR(190) NOT NULL,
    `slug`        VARCHAR(190) NOT NULL,
    `parent_id`   INT NULL DEFAULT NULL,
    `content_md`  LONGTEXT NOT NULL,
    `created_by`  INT NOT NULL,
    `updated_by`  INT NULL DEFAULT NULL,
    `created_at`  DATETIME NOT NULL,
    `updated_at`  DATETIME NULL DEFAULT NULL,
    `deleted_at`  DATETIME NULL DEFAULT NULL,
    UNIQUE INDEX `idx_pages_slug` (`slug`),
    INDEX `idx_pages_parent_id` (`parent_id`),
    CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_pages_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
