-- AP6: Add position column for Kanban board ordering within status columns
ALTER TABLE tasks ADD COLUMN position INT NOT NULL DEFAULT 0;

-- Index for efficient board queries: tasks grouped by status, sorted by position
CREATE INDEX idx_tasks_status_position ON tasks (status, position);
