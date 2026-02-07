-- ================================================================
-- AP4: Tasks, Tags, Task-Tags tables
-- ================================================================

CREATE TABLE IF NOT EXISTS `tags` (
    `id`   INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    UNIQUE INDEX `idx_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `title`          VARCHAR(190) NOT NULL,
    `description_md` LONGTEXT NULL DEFAULT NULL,
    `status`         ENUM('backlog','ready','doing','review','done') NOT NULL DEFAULT 'backlog',
    `owner_id`       INT NULL DEFAULT NULL,
    `due_date`       DATE NULL DEFAULT NULL,
    `created_by`     INT NOT NULL,
    `updated_by`     INT NULL DEFAULT NULL,
    `created_at`     DATETIME NOT NULL,
    `updated_at`     DATETIME NULL DEFAULT NULL,

    INDEX `idx_tasks_status`   (`status`),
    INDEX `idx_tasks_owner_id` (`owner_id`),
    INDEX `idx_tasks_due_date` (`due_date`),

    CONSTRAINT `fk_tasks_owner`      FOREIGN KEY (`owner_id`)   REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_tasks_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_tags` (
    `task_id` INT NOT NULL,
    `tag_id`  INT NOT NULL,
    PRIMARY KEY (`task_id`, `tag_id`),

    CONSTRAINT `fk_task_tags_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_task_tags_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
