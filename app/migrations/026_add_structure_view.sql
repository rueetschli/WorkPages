-- ================================================================
-- AP25: Structure View - task hierarchy (Epic → Feature → Task)
-- ================================================================

-- Add task_type: determines hierarchy level
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS task_type ENUM('epic','feature','task') NOT NULL DEFAULT 'task';

-- Add parent_task_id: self-referential FK for hierarchy
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS parent_task_id INT NULL DEFAULT NULL;

-- Add structure_position: ordering within same parent
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS structure_position INT NOT NULL DEFAULT 0;

-- Initialize structure_position from id for stable, unique ordering
UPDATE tasks SET structure_position = id * 1000 WHERE structure_position = 0;

-- Foreign key for parent_task_id (ON DELETE SET NULL keeps orphan tasks alive)
ALTER TABLE tasks
    ADD CONSTRAINT fk_tasks_parent
        FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE SET NULL;

-- Indexes for structure queries
CREATE INDEX IF NOT EXISTS idx_tasks_board_parent   ON tasks (board_id, parent_task_id);
CREATE INDEX IF NOT EXISTS idx_tasks_parent_struct  ON tasks (parent_task_id, structure_position);
CREATE INDEX IF NOT EXISTS idx_tasks_board_type     ON tasks (board_id, task_type);
