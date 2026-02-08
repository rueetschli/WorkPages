<?php
/**
 * User model - database operations for the users table.
 */
class User
{
    /** Valid roles. */
    const ROLES = ['admin', 'member', 'viewer'];

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
     * Update a user's profile fields (name, email, role, is_active).
     * Password is updated only if provided (non-empty).
     */
    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = $data['email'];
        }
        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $params[] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = (int) $data['is_active'];
        }
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        DB::query(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
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

    /**
     * Get all users for admin listing.
     */
    public static function all(): array
    {
        return DB::fetchAll(
            'SELECT id, email, name, role, is_active, created_at, last_login_at FROM users ORDER BY name ASC'
        );
    }

    /**
     * Count the number of active admin users.
     */
    public static function countActiveAdmins(): int
    {
        $row = DB::fetch("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin' AND is_active = 1");
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Check if an email already exists (optionally excluding a user ID).
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = DB::fetch('SELECT COUNT(*) AS cnt FROM users WHERE email = ? AND id != ?', [$email, $excludeId]);
        } else {
            $row = DB::fetch('SELECT COUNT(*) AS cnt FROM users WHERE email = ?', [$email]);
        }
        return ((int) ($row['cnt'] ?? 0)) > 0;
    }
}
