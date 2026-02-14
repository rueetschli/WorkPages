-- ================================================================
-- WorkPages - Migration 002: Flexible Kanban Columns (AP13)
-- Replaces fixed status ENUM with configurable board_columns table.
-- ================================================================

-- 1. Create board_columns table
CREATE TABLE IF NOT EXISTS `board_columns` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(100) NOT NULL,
    `position`   INT NOT NULL DEFAULT 0,
    `color`      VARCHAR(20) NULL DEFAULT NULL,
    `wip_limit`  INT NULL DEFAULT NULL,
    `is_default` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_board_columns_position` (`position`),
    INDEX `idx_board_columns_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insert default columns matching previous status values
INSERT INTO `board_columns` (`name`, `slug`, `position`, `color`, `wip_limit`, `is_default`, `created_at`)
VALUES
    ('Backlog',  'backlog', 1000,  NULL, NULL, 1, NOW()),
    ('Ready',    'ready',   2000,  NULL, NULL, 0, NOW()),
    ('Doing',    'doing',   3000,  NULL, NULL, 0, NOW()),
    ('Review',   'review',  4000,  NULL, NULL, 0, NOW()),
    ('Done',     'done',    5000,  NULL, NULL, 0, NOW());

-- 3. Add column_id to tasks table
ALTER TABLE `tasks` ADD COLUMN `column_id` INT NULL DEFAULT NULL AFTER `status`;

-- 4. Populate column_id from existing status values
UPDATE `tasks` t
    INNER JOIN `board_columns` bc ON bc.slug = t.status
SET t.column_id = bc.id;

-- 5. Set column_id NOT NULL and add foreign key
ALTER TABLE `tasks` MODIFY COLUMN `column_id` INT NOT NULL;
ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_column` FOREIGN KEY (`column_id`) REFERENCES `board_columns` (`id`);
ALTER TABLE `tasks` ADD INDEX `idx_tasks_column_id` (`column_id`);
ALTER TABLE `tasks` ADD INDEX `idx_tasks_column_position` (`column_id`, `position`);

-- 6. Drop old status indexes and column
ALTER TABLE `tasks` DROP INDEX `idx_tasks_status`;
ALTER TABLE `tasks` DROP INDEX `idx_tasks_status_position`;
ALTER TABLE `tasks` DROP COLUMN `status`;
