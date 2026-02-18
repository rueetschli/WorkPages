-- ================================================================
-- WorkPages - Migration 013: Add category to board_columns (AP18)
-- Classifies columns as backlog, active, or done for flow metrics.
-- ================================================================

ALTER TABLE `board_columns`
    ADD COLUMN `category` ENUM('backlog','active','done') NOT NULL DEFAULT 'active'
    AFTER `wip_limit`;

-- Set categories for default columns based on slug
UPDATE `board_columns` SET `category` = 'backlog' WHERE `slug` = 'backlog';
UPDATE `board_columns` SET `category` = 'done'    WHERE `slug` = 'done';
UPDATE `board_columns` SET `category` = 'active'  WHERE `slug` NOT IN ('backlog', 'done');
