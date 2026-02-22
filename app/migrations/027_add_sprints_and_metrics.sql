-- ================================================================
-- WorkPages - Migration 027: Sprints and Daily Metrics (AP26)
-- Adds sprint management, task-sprint association, and daily
-- snapshot table for burndown/velocity reports.
-- ================================================================

-- 1. Sprints table
CREATE TABLE IF NOT EXISTS `sprints` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `board_id`    INT UNSIGNED NOT NULL,
    `name`        VARCHAR(150) NOT NULL,
    `start_date`  DATE NOT NULL,
    `end_date`    DATE NOT NULL,
    `status`      ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`   DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_sprints_board_status` (`board_id`, `status`),
    INDEX `idx_sprints_board_dates` (`board_id`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sprint daily metrics (snapshot table)
CREATE TABLE IF NOT EXISTS `sprint_daily_metrics` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sprint_id`            INT UNSIGNED NOT NULL,
    `date`                 DATE NOT NULL,
    `total_task_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `remaining_task_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `completed_task_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_sdm_sprint_date` (`sprint_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add sprint_id to tasks
ALTER TABLE `tasks` ADD COLUMN `sprint_id` INT UNSIGNED NULL DEFAULT NULL AFTER `board_id`;
CREATE INDEX `idx_tasks_sprint_id` ON `tasks` (`sprint_id`);
