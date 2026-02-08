-- AP9: Page sharing tokens for external read-only access
CREATE TABLE IF NOT EXISTS page_shares (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    page_id     INT NOT NULL,
    token       CHAR(64) NOT NULL,
    permission  ENUM('view') NOT NULL DEFAULT 'view',
    created_by  INT NOT NULL,
    created_at  DATETIME NOT NULL,
    revoked_at  DATETIME NULL DEFAULT NULL,
    expires_at  DATETIME NULL DEFAULT NULL,

    UNIQUE KEY idx_token (token),
    INDEX idx_page_id (page_id),

    CONSTRAINT fk_page_shares_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_page_shares_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
