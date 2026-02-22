-- AP29: Backup & Betrieb – add backup tracking fields to system_settings
ALTER TABLE system_settings
    ADD COLUMN last_backup_at DATETIME NULL DEFAULT NULL AFTER maintenance_active,
    ADD COLUMN last_backup_note VARCHAR(255) NOT NULL DEFAULT '' AFTER last_backup_at;
