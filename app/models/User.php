<?php
/**
 * User model - database operations for the users table.
 */
class User
{
    /**
     * Find a user by email address.
     */
    public static function findByEmail(string $email): ?array
    {
        return DB::fetch('SELECT * FROM users WHERE email = ?', [$email]);
    }

    /**
     * Find a user by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * Create a new user. Returns the new user's ID.
     */
    public static function create(string $email, string $name, string $password, string $role = 'member'): int
    {
        DB::query(
            'INSERT INTO users (email, name, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$email, $name, password_hash($password, PASSWORD_DEFAULT), $role]
        );
        return (int) DB::lastInsertId();
    }

    /**
     * Update the last_login_at timestamp.
     */
    public static function touchLogin(int $id): void
    {
        DB::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Check how many users exist (used for seed/setup detection).
     */
    public static function count(): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM users');
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get all users eligible for task ownership (admin + member, not viewer).
     */
    public static function allForDropdown(): array
    {
        return DB::fetchAll(
            "SELECT id, name, email, role FROM users WHERE role IN ('admin', 'member') ORDER BY name ASC"
        );
    }
}
