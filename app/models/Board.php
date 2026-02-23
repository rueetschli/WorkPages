<?php
/**
 * Board model - database operations for the boards table (AP21).
 */
class Board
{
    /**
     * Find a board by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT b.*, u.name AS creator_name, t.name AS team_name
             FROM boards b
             LEFT JOIN users u ON u.id = b.created_by
             LEFT JOIN teams t ON t.id = b.team_id
             WHERE b.id = ?',
            [$id]
        );
    }

    /**
     * Get all boards ordered by team name, then board name.
     */
    public static function all(): array
    {
        return DB::fetchAll(
            'SELECT b.*, t.name AS team_name
             FROM boards b
             LEFT JOIN teams t ON t.id = b.team_id
             ORDER BY t.name ASC, b.name ASC'
        );
    }

    /**
     * Get boards visible to a user.
     * Admin sees all. Others see boards for their teams + global boards (team_id IS NULL).
     */
    public static function allVisibleTo(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        if ($globalRole === 'admin') {
            if ($filterTeamId !== null) {
                return DB::fetchAll(
                    'SELECT b.*, t.name AS team_name
                     FROM boards b
                     LEFT JOIN teams t ON t.id = b.team_id
                     WHERE b.team_id IS NULL OR b.team_id = ?
                     ORDER BY t.name ASC, b.name ASC',
                    [$filterTeamId]
                );
            }
            return self::all();
        }

        $teamIds = TeamUser::getTeamIds($userId);

        if ($filterTeamId !== null) {
            if (!in_array($filterTeamId, $teamIds, true)) {
                return DB::fetchAll(
                    'SELECT b.*, t.name AS team_name
                     FROM boards b
                     LEFT JOIN teams t ON t.id = b.team_id
                     WHERE b.team_id IS NULL
                     ORDER BY b.name ASC'
                );
            }

            return DB::fetchAll(
                'SELECT b.*, t.name AS team_name
                 FROM boards b
                 LEFT JOIN teams t ON t.id = b.team_id
                 WHERE b.team_id IS NULL OR b.team_id = ?
                 ORDER BY t.name ASC, b.name ASC',
                [$filterTeamId]
            );
        }

        if (empty($teamIds)) {
            return DB::fetchAll(
                'SELECT b.*, t.name AS team_name
                 FROM boards b
                 LEFT JOIN teams t ON t.id = b.team_id
                 WHERE b.team_id IS NULL
                 ORDER BY b.name ASC'
            );
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        return DB::fetchAll(
            "SELECT b.*, t.name AS team_name
             FROM boards b
             LEFT JOIN teams t ON t.id = b.team_id
             WHERE b.team_id IS NULL OR b.team_id IN ({$placeholders})
             ORDER BY t.name ASC, b.name ASC",
            $teamIds
        );
    }

    /**
     * Get boards for a specific team.
     */
    public static function allForTeam(int $teamId): array
    {
        return DB::fetchAll(
            'SELECT b.*, t.name AS team_name
             FROM boards b
             LEFT JOIN teams t ON t.id = b.team_id
             WHERE b.team_id = ?
             ORDER BY b.name ASC',
            [$teamId]
        );
    }

    /**
     * Create a new board. Returns the new board ID.
     */
    public static function create(array $data): int
    {
        DB::query(
            'INSERT INTO boards (name, description, team_id, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [
                $data['name'],
                $data['description'] ?? null,
                !empty($data['team_id']) ? (int) $data['team_id'] : null,
                (int) $data['created_by'],
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Update a board.
     */
    public static function update(int $id, array $data): void
    {
        $sets   = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[]   = 'name = ?';
            $params[] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $sets[]   = 'description = ?';
            $params[] = $data['description'];
        }

        if (empty($sets)) {
            return;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $id;

        DB::query(
            'UPDATE boards SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Delete a board. Sets board_id = NULL on associated tasks.
     */
    public static function delete(int $id): void
    {
        DB::query('UPDATE tasks SET board_id = NULL WHERE board_id = ?', [$id]);
        DB::query('DELETE FROM boards WHERE id = ?', [$id]);
    }

    /**
     * Count tasks on a board.
     */
    public static function taskCount(int $boardId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM tasks WHERE board_id = ?', [$boardId]);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Check if a board name is unique within a team context.
     * Returns true if the name is available.
     */
    public static function isNameUnique(string $name, ?int $teamId, ?int $excludeId = null): bool
    {
        if ($teamId !== null) {
            $sql = 'SELECT COUNT(*) AS cnt FROM boards WHERE team_id = ? AND name = ?';
            $params = [$teamId, $name];
        } else {
            $sql = 'SELECT COUNT(*) AS cnt FROM boards WHERE team_id IS NULL AND name = ?';
            $params = [$name];
        }

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $row = DB::fetch($sql, $params);
        return (int) ($row['cnt'] ?? 0) === 0;
    }
}
