-- AP16: Team-User membership with role
CREATE TABLE IF NOT EXISTS team_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('team_admin','team_member','team_viewer') NOT NULL DEFAULT 'team_member',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_team_users (team_id, user_id),
    KEY idx_team_users_user (user_id),
    CONSTRAINT fk_team_users_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
