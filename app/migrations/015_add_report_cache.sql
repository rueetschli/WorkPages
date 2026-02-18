-- ================================================================
-- WorkPages - Migration 015: Add report cache table (AP18)
-- DB-based cache for computed report aggregations.
-- ================================================================

CREATE TABLE IF NOT EXISTS `report_cache` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `cache_key`    VARCHAR(255) NOT NULL,
    `payload_json` MEDIUMTEXT NOT NULL,
    `generated_at` DATETIME NOT NULL,
    `expires_at`   DATETIME NOT NULL,
    UNIQUE KEY `uq_report_cache_key` (`cache_key`),
    INDEX `idx_report_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily snapshot table for WIP trend tracking
CREATE TABLE IF NOT EXISTS `report_snapshots` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `snap_date`  DATE NOT NULL,
    `team_id`    INT NULL DEFAULT NULL,
    `column_id`  INT NOT NULL,
    `task_count` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_snap_date_team_col` (`snap_date`, `team_id`, `column_id`),
    INDEX `idx_snap_team_date` (`team_id`, `snap_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
