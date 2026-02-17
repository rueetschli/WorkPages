-- AP17: File Attachments and Media Management
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('page','task') NOT NULL,
    entity_id INT NOT NULL,
    team_id INT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_ext VARCHAR(20) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NULL,
    uploaded_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    KEY idx_attachments_entity (entity_type, entity_id, created_at),
    KEY idx_attachments_team (team_id),
    KEY idx_attachments_uploader (uploaded_by),
    KEY idx_attachments_deleted (deleted_at),
    CONSTRAINT fk_attachments_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
