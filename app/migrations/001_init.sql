-- ================================================================
-- WorkPages - Migration 001: Initial Schema
-- Creates all tables for a fresh installation.
-- ================================================================

CREATE TABLE IF NOT EXISTS `app_meta` (
    `meta_key`   VARCHAR(100) NOT NULL,
    `meta_value` TEXT NULL DEFAULT NULL,
    PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(190) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    `is_active`     TINYINT NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL,
    `updated_at`    DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    UNIQUE INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `title`       VARCHAR(190) NOT NULL,
    `slug`        VARCHAR(190) NOT NULL,
    `parent_id`   INT NULL DEFAULT NULL,
    `content_md`  LONGTEXT NOT NULL,
    `created_by`  INT NOT NULL,
    `updated_by`  INT NULL DEFAULT NULL,
    `created_at`  DATETIME NOT NULL,
    `updated_at`  DATETIME NULL DEFAULT NULL,
    `deleted_at`  DATETIME NULL DEFAULT NULL,
    UNIQUE INDEX `idx_pages_slug` (`slug`),
    INDEX `idx_pages_parent_id` (`parent_id`),
    INDEX `idx_pages_title` (`title`(191)),
    CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_pages_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id`   INT NOT NULL,
    `action`      VARCHAR(50) NOT NULL,
    `meta_json`   TEXT NULL DEFAULT NULL,
    `created_by`  INT NOT NULL,
    `created_at`  DATETIME NOT NULL,
    INDEX `idx_activity_entity` (`entity_type`, `entity_id`),
    INDEX `idx_activity_created_by` (`created_by`),
    INDEX `idx_activity_entity_time` (`entity_type`, `entity_id`, `created_at`),
    INDEX `idx_activity_action` (`action`),
    CONSTRAINT `fk_activity_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
    `id`   INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    UNIQUE INDEX `idx_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `title`          VARCHAR(190) NOT NULL,
    `description_md` LONGTEXT NULL DEFAULT NULL,
    `status`         ENUM('backlog','ready','doing','review','done') NOT NULL DEFAULT 'backlog',
    `owner_id`       INT NULL DEFAULT NULL,
    `due_date`       DATE NULL DEFAULT NULL,
    `position`       INT NOT NULL DEFAULT 0,
    `created_by`     INT NOT NULL,
    `updated_by`     INT NULL DEFAULT NULL,
    `created_at`     DATETIME NOT NULL,
    `updated_at`     DATETIME NULL DEFAULT NULL,
    INDEX `idx_tasks_status` (`status`),
    INDEX `idx_tasks_owner_id` (`owner_id`),
    INDEX `idx_tasks_due_date` (`due_date`),
    INDEX `idx_tasks_status_position` (`status`, `position`),
    INDEX `idx_tasks_title` (`title`(191)),
    CONSTRAINT `fk_tasks_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_tasks_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_tags` (
    `task_id` INT NOT NULL,
    `tag_id`  INT NOT NULL,
    PRIMARY KEY (`task_id`, `tag_id`),
    CONSTRAINT `fk_task_tags_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_task_tags_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_tasks` (
    `page_id`    INT NOT NULL,
    `task_id`    INT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`page_id`, `task_id`),
    INDEX `idx_page_sort` (`page_id`, `sort_order`),
    INDEX `idx_task` (`task_id`),
    CONSTRAINT `fk_pt_page`    FOREIGN KEY (`page_id`)    REFERENCES `pages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pt_task`    FOREIGN KEY (`task_id`)    REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pt_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('page','task') NOT NULL,
    `entity_id`   INT NOT NULL,
    `body_md`     LONGTEXT NOT NULL,
    `created_by`  INT NOT NULL,
    `created_at`  DATETIME NOT NULL,
    `updated_at`  DATETIME NULL DEFAULT NULL,
    `deleted_at`  DATETIME NULL DEFAULT NULL,
    INDEX `idx_comments_entity` (`entity_type`, `entity_id`, `created_at`),
    INDEX `idx_comments_created_by` (`created_by`),
    CONSTRAINT `fk_comments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_shares` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `page_id`    INT NOT NULL,
    `token`      CHAR(64) NOT NULL,
    `permission` ENUM('view') NOT NULL DEFAULT 'view',
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    UNIQUE KEY `idx_token` (`token`),
    INDEX `idx_page_id` (`page_id`),
    CONSTRAINT `fk_page_shares_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_page_shares_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Set initial schema version
INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES ('schema_version', '1');
INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES ('app_version', '1.0.0');
INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES ('installed_at', NOW());
