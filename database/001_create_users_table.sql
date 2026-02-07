-- ================================================================
-- Work Pages - Migration 001: Create users table
-- Arbeitspaket 2: Authentifizierung und Benutzerverwaltung
-- ================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(190) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    `created_at`    DATETIME NOT NULL,
    `updated_at`    DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    UNIQUE INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
