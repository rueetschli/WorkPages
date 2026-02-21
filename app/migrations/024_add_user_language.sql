-- AP24: Add user language preference for i18n
ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT NULL AFTER role;
