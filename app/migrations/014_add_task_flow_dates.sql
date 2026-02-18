-- ================================================================
-- WorkPages - Migration 014: Add flow date fields to tasks (AP18)
-- Tracks started_at and done_at for cycle time / throughput.
-- ================================================================

ALTER TABLE `tasks`
    ADD COLUMN `started_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD COLUMN `done_at`    DATETIME NULL DEFAULT NULL AFTER `started_at`;

-- Backfill: Tasks in done columns get done_at = updated_at (or created_at)
UPDATE `tasks` t
    INNER JOIN `board_columns` bc ON bc.id = t.column_id
SET t.done_at    = COALESCE(t.updated_at, t.created_at),
    t.started_at = t.created_at
WHERE bc.category = 'done';

-- Backfill: Tasks in active columns get started_at = created_at
UPDATE `tasks` t
    INNER JOIN `board_columns` bc ON bc.id = t.column_id
SET t.started_at = t.created_at
WHERE bc.category = 'active';

-- Indexes for reporting queries
CREATE INDEX `idx_tasks_started_at` ON `tasks` (`started_at`);
CREATE INDEX `idx_tasks_done_at`    ON `tasks` (`done_at`);
CREATE INDEX `idx_tasks_team_done`  ON `tasks` (`team_id`, `done_at`);
CREATE INDEX `idx_tasks_team_started` ON `tasks` (`team_id`, `started_at`);
