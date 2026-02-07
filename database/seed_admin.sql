-- ================================================================
-- Work Pages - Seed: Initial admin user
-- Only run this ONCE after creating the users table.
--
-- Credentials:
--   Email:    admin@example.com
--   Password: admin123
--   Role:     admin
--
-- The password hash below was generated with PHP password_hash().
-- Change the password immediately after first login.
-- ================================================================

INSERT INTO `users` (`email`, `name`, `password_hash`, `role`, `created_at`)
SELECT 'admin@example.com', 'Admin',
       '$2y$12$LbjI0iQHI.9VUeEabXT8ZOzLs33Bs1FBVUZXirBQZZay6NVoTCAyy',
       'admin', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `users` WHERE `email` = 'admin@example.com'
);
