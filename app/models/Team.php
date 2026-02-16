<?php
/**
 * Team model - database operations for the teams table (AP16).
 */
class Team
{
    /**
     * Find a team by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM teams WHERE id = ?', [$id]);
    }

    /**
     * Find a team by name.
     */
    public static function findByName(string $name): ?array
    {
        return DB::fetch('SELECT * FROM teams WHERE name = ?', [$name]);
    }

    /**
     * Fetch all teams ordered by name.
     */
    public static function all(): array
    {
        return DB::fetchAll(
            'SELECT t.*, u.name AS creator_name
             FROM teams t
             LEFT JOIN users u ON u.id = t.created_by
             ORDER BY t.name ASC'
        );
    }

    /**
     * Create a new team. Returns the new team ID.
     */
    public static function create(array $data): int
    {
        DB::query(
            'INSERT INTO teams (name, description, created_at, created_by)
             VALUES (?, ?, NOW(), ?)',
            [
                $data['name'],
                $data['description'] ?? null,
                (int) $data['created_by'],
            ]
        );
        return (int) DB::lastInsertId();
    }

    /**
     * Update an existing team.
     */
    public static function update(int $id, array $data): void
    {
        $sets = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $sets[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $id;
        DB::query('UPDATE teams SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /**
     * Delete a team by ID.
     */
    public static function delete(int $id): void
    {
        DB::query('DELETE FROM teams WHERE id = ?', [$id]);
    }

    /**
     * Check if a team name already exists (optionally excluding an ID).
     */
    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = DB::fetch(
                'SELECT id FROM teams WHERE name = ? AND id != ?',
                [$name, $excludeId]
            );
        } else {
            $row = DB::fetch('SELECT id FROM teams WHERE name = ?', [$name]);
        }
        return $row !== null;
    }

    /**
     * Count pages assigned to a team.
     */
    public static function countPages(int $teamId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM pages WHERE team_id = ? AND deleted_at IS NULL',
            [$teamId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Count tasks assigned to a team.
     */
    public static function countTasks(int $teamId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM tasks WHERE team_id = ?',
            [$teamId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Get teams the current user belongs to (for dropdowns / switcher).
     */
    public static function getTeamsForUser(int $userId): array
    {
        return DB::fetchAll(
            'SELECT t.*, tu.role AS team_role
             FROM teams t
             INNER JOIN team_users tu ON tu.team_id = t.id
             WHERE tu.user_id = ?
             ORDER BY t.name ASC',
            [$userId]
        );
    }

    /**
     * Get member count for a team.
     */
    public static function countMembers(int $teamId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM team_users WHERE team_id = ?',
            [$teamId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }
}
