-- AP16: Add team_id to tasks (NULL = global visibility)
ALTER TABLE tasks ADD COLUMN team_id INT NULL AFTER column_id;
ALTER TABLE tasks ADD KEY idx_tasks_team_id (team_id);
ALTER TABLE tasks ADD CONSTRAINT fk_tasks_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL;
