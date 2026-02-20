-- ================================================================
-- WorkPages - Migration 023: Multi-Board Support (AP21)
-- Creates boards table and adds board_id to tasks.
-- ================================================================

-- 1. Create boards table
CREATE TABLE IF NOT EXISTS `boards` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `team_id`     INT NULL DEFAULT NULL,
    `created_by`  INT NOT NULL,
    `created_at`  DATETIME NOT NULL,
    `updated_at`  DATETIME NULL DEFAULT NULL,
    INDEX `idx_boards_team_id` (`team_id`),
    UNIQUE INDEX `idx_boards_team_name` (`team_id`, `name`),
    CONSTRAINT `fk_boards_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_boards_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add board_id to tasks
ALTER TABLE `tasks`
    ADD COLUMN `board_id` INT NULL DEFAULT NULL AFTER `team_id`;

ALTER TABLE `tasks`
    ADD INDEX `idx_tasks_board_id` (`board_id`),
    ADD CONSTRAINT `fk_tasks_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE SET NULL;
