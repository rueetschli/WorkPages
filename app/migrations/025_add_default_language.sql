-- AP24: Add default_language to system_settings
ALTER TABLE system_settings ADD COLUMN default_language VARCHAR(10) NOT NULL DEFAULT 'de' AFTER maintenance_active;
