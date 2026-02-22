-- ================================================================
-- AP27: Saved Views – personal work modes per user
-- ================================================================

CREATE TABLE IF NOT EXISTS `user_views` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `view_type`  ENUM('board','structure','tasks') NOT NULL,
    `context_id` INT NULL DEFAULT NULL COMMENT 'board_id for board/structure views, NULL for tasks',
    `parameters` JSON NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,

    INDEX `idx_user_views_user_type` (`user_id`, `view_type`),
    INDEX `idx_user_views_user_default` (`user_id`, `is_default`),

    CONSTRAINT `fk_user_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
