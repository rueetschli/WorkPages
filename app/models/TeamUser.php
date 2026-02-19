<?php
/**
 * TeamUser model - team membership and team roles (AP16).
 */
class TeamUser
{
    /** Valid team roles. */
    const ROLES = ['team_admin', 'team_member', 'team_viewer'];

    /**
     * Add a user to a team with a given role.
     * Returns true if inserted, false if already existed.
     */
    public static function add(int $teamId, int $userId, string $role = 'team_member'): bool
    {
        if (!in_array($role, self::ROLES, true)) {
            return false;
        }

        $existing = DB::fetch(
            'SELECT id FROM team_users WHERE team_id = ? AND user_id = ?',
            [$teamId, $userId]
        );

        if ($existing) {
            return false;
        }

        DB::query(
            'INSERT INTO team_users (team_id, user_id, role, created_at)
             VALUES (?, ?, ?, NOW())',
            [$teamId, $userId, $role]
        );
        return true;
    }

    /**
     * Remove a user from a team.
     * Returns true if removed, false if not found.
     */
    public static function remove(int $teamId, int $userId): bool
    {
        $stmt = DB::query(
            'DELETE FROM team_users WHERE team_id = ? AND user_id = ?',
            [$teamId, $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Update a user's role within a team.
     */
    public static function updateRole(int $teamId, int $userId, string $role): void
    {
        if (!in_array($role, self::ROLES, true)) {
            return;
        }

        DB::query(
            'UPDATE team_users SET role = ? WHERE team_id = ? AND user_id = ?',
            [$role, $teamId, $userId]
        );
    }

    /**
     * Get the team role for a user in a specific team.
     * Returns null if the user is not a member.
     */
    public static function getRole(int $teamId, int $userId): ?string
    {
        $row = DB::fetch(
            'SELECT role FROM team_users WHERE team_id = ? AND user_id = ?',
            [$teamId, $userId]
        );
        return $row ? $row['role'] : null;
    }

    /**
     * Check if a user is a member of a team.
     */
    public static function isMember(int $teamId, int $userId): bool
    {
        $row = DB::fetch(
            'SELECT id FROM team_users WHERE team_id = ? AND user_id = ?',
            [$teamId, $userId]
        );
        return $row !== null;
    }

    /**
     * Get all members of a team with user details.
     */
    public static function getMembers(int $teamId): array
    {
        return DB::fetchAll(
            'SELECT tu.*, u.name AS user_name, u.email AS user_email, u.role AS global_role, u.is_active
             FROM team_users tu
             INNER JOIN users u ON u.id = tu.user_id
             WHERE tu.team_id = ?
             ORDER BY u.name ASC',
            [$teamId]
        );
    }

    /**
     * Get all team IDs for a user.
     *
     * @return int[]
     */
    public static function getTeamIds(int $userId): array
    {
        $rows = DB::fetchAll(
            'SELECT team_id FROM team_users WHERE user_id = ?',
            [$userId]
        );
        return array_map(fn($r) => (int) $r['team_id'], $rows);
    }

    /**
     * Check if user is team_admin in a specific team.
     */
    public static function isTeamAdmin(int $teamId, int $userId): bool
    {
        $role = self::getRole($teamId, $userId);
        return $role === 'team_admin';
    }

    /**
     * Remove all watchers for a user on entities belonging to a team.
     * Called when a user is removed from a team.
     */
    public static function removeWatchersForTeam(int $teamId, int $userId): void
    {
        // Remove watchers on pages in this team
        DB::query(
            'DELETE w FROM watchers w
             INNER JOIN pages p ON w.entity_type = \'page\' AND w.entity_id = p.id
             WHERE p.team_id = ? AND w.user_id = ?',
            [$teamId, $userId]
        );

        // Remove watchers on tasks in this team
        DB::query(
            'DELETE w FROM watchers w
             INNER JOIN tasks t ON w.entity_type = \'task\' AND w.entity_id = t.id
             WHERE t.team_id = ? AND w.user_id = ?',
            [$teamId, $userId]
        );
    }
}
