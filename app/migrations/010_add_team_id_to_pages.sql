-- AP16: Add team_id to pages (NULL = global visibility)
ALTER TABLE pages ADD COLUMN team_id INT NULL AFTER parent_id;
ALTER TABLE pages ADD KEY idx_pages_team_id (team_id);
ALTER TABLE pages ADD CONSTRAINT fk_pages_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL;
