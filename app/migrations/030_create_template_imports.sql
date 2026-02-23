-- AP31: Template imports tracking for idempotent imports
CREATE TABLE IF NOT EXISTS template_imports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(255) NOT NULL COMMENT 'e.g. scrum/daily-de',
    content_hash CHAR(64) NOT NULL COMMENT 'SHA-256 hash of template content',
    page_id INT UNSIGNED NOT NULL COMMENT 'ID of the created page',
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_template_key (template_key),
    KEY idx_page_id (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
