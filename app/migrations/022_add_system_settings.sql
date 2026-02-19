-- AP20: System settings for branding, theming, maintenance messages
CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED NOT NULL DEFAULT 1,
    company_name VARCHAR(150) NOT NULL DEFAULT '',
    logo_path VARCHAR(255) NOT NULL DEFAULT '',
    theme_mode ENUM('preset','custom') NOT NULL DEFAULT 'preset',
    theme_preset VARCHAR(50) NOT NULL DEFAULT 'blau',
    color_primary CHAR(7) NOT NULL DEFAULT '#2563eb',
    color_secondary CHAR(7) NOT NULL DEFAULT '#1e293b',
    color_accent CHAR(7) NOT NULL DEFAULT '#3b82f6',
    maintenance_message VARCHAR(255) NOT NULL DEFAULT '',
    maintenance_level ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    maintenance_active TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (id) VALUES (1);
