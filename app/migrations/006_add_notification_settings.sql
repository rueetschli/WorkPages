-- AP15: Notification settings per user
-- Controls email delivery mode and auto-watch behaviour.

CREATE TABLE IF NOT EXISTS notification_settings (
    user_id                INT NOT NULL PRIMARY KEY,
    email_enabled          TINYINT NOT NULL DEFAULT 1,
    email_mode             ENUM('immediate','digest_daily','digest_weekly','digest_off') NOT NULL DEFAULT 'immediate',
    email_address_override VARCHAR(190) NULL,
    watch_auto_on_create   TINYINT NOT NULL DEFAULT 1,
    watch_auto_on_comment  TINYINT NOT NULL DEFAULT 1,
    notify_on_task_updates TINYINT NOT NULL DEFAULT 1,
    notify_on_page_updates TINYINT NOT NULL DEFAULT 1,
    notify_on_comments     TINYINT NOT NULL DEFAULT 1,
    notify_on_mentions     TINYINT NOT NULL DEFAULT 1,
    notify_on_assignments  TINYINT NOT NULL DEFAULT 1,
    notify_on_moves        TINYINT NOT NULL DEFAULT 0,

    CONSTRAINT fk_ns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE app_meta SET meta_value = '6' WHERE meta_key = 'schema_version';
