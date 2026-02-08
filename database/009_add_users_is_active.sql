-- AP9: Add is_active flag to users table for account enable/disable
ALTER TABLE users ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1 AFTER role;
