<?php
/**
 * TeamService - Central team-based authorization and visibility logic (AP16).
 *
 * All team access checks go through this service.
 * No authorization checks in views.
 */
class TeamService
{
    /**
     * Map team roles to equivalent global role level for comparison.
     * Lower index = more privileges.
     */
    private const TEAM_ROLE_MAP = [
        'team_admin'  => 'admin',
        'team_member' => 'member',
        'team_viewer' => 'viewer',
    ];

    private const ROLE_LEVEL = [
        'admin'  => 0,
        'member' => 1,
        'viewer' => 2,
    ];

    // ── Session: active team ────────────────────────────────────────

    /**
     * Get the currently active team ID from session.
     * Returns null for "Alle Teams".
     */
    public static function getActiveTeamId(): ?int
    {
        return isset($_SESSION['active_team_id']) ? (int) $_SESSION['active_team_id'] : null;
    }

    /**
     * Set the active team in session.
     * Pass null for "Alle Teams".
     */
    public static function setActiveTeamId(?int $teamId): void
    {
        if ($teamId === null) {
            unset($_SESSION['active_team_id']);
        } else {
            $_SESSION['active_team_id'] = $teamId;
        }
    }

    // ── Effective role calculation ──────────────────────────────────

    /**
     * Calculate the effective role for a user within a team context.
     * Effective role = the more restrictive of global role and team role.
     *
     * @return string|null  Effective role ('admin', 'member', 'viewer') or null if no access
     */
    public static function getEffectiveRole(int $userId, int $teamId): ?string
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return null;
        }

        // Global admins always have access
        if ($globalRole === 'admin') {
            $teamRole = TeamUser::getRole($teamId, $userId);
            if ($teamRole === null) {
                // Global admin can see team content even without membership
                return 'admin';
            }
            // Map team role and take the more restrictive
            $mappedTeamRole = self::TEAM_ROLE_MAP[$teamRole] ?? 'viewer';
            return self::moreRestrictive($globalRole, $mappedTeamRole);
        }

        // Non-admin users must be team members
        $teamRole = TeamUser::getRole($teamId, $userId);
        if ($teamRole === null) {
            return null; // Not a member, no access
        }

        $mappedTeamRole = self::TEAM_ROLE_MAP[$teamRole] ?? 'viewer';
        return self::moreRestrictive($globalRole, $mappedTeamRole);
    }

    /**
     * Return the more restrictive of two roles.
     */
    private static function moreRestrictive(string $roleA, string $roleB): string
    {
        $levelA = self::ROLE_LEVEL[$roleA] ?? 2;
        $levelB = self::ROLE_LEVEL[$roleB] ?? 2;
        return $levelA >= $levelB ? $roleA : $roleB;
    }

    // ── Access checks for pages ────────────────────────────────────

    /**
     * Check if a user can view a page.
     */
    public static function canViewPage(int $userId, array $page): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        // Global admin sees everything
        if ($globalRole === 'admin') {
            return true;
        }

        // Page without team = global, visible to all
        $teamId = $page['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        // Page with team = must be team member
        return TeamUser::isMember((int) $teamId, $userId);
    }

    /**
     * Check if a user can edit a page.
     */
    public static function canEditPage(int $userId, array $page): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        // Global viewers can never edit
        if ($globalRole === 'viewer') {
            return false;
        }

        // Page without team = global role decides
        $teamId = $page['team_id'] ?? null;
        if ($teamId === null) {
            return in_array($globalRole, ['admin', 'member'], true);
        }

        // Global admin always
        if ($globalRole === 'admin') {
            return true;
        }

        // Page with team = check effective role
        $effectiveRole = self::getEffectiveRole($userId, (int) $teamId);
        if ($effectiveRole === null) {
            return false;
        }

        return in_array($effectiveRole, ['admin', 'member'], true);
    }

    // ── Access checks for tasks ────────────────────────────────────

    /**
     * Resolve the team_id for a task.
     * If the task has an explicit team_id, use it.
     * Otherwise, check linked pages.
     */
    public static function resolveTaskTeamId(array $task): ?int
    {
        // Explicit team_id on task
        if (!empty($task['team_id'])) {
            return (int) $task['team_id'];
        }

        // Check linked pages for inherited team
        $pages = PageTask::getPages((int) $task['id']);
        foreach ($pages as $pg) {
            $fullPage = Page::findById((int) $pg['id']);
            if ($fullPage && !empty($fullPage['team_id'])) {
                return (int) $fullPage['team_id'];
            }
        }

        return null;
    }

    /**
     * Check if a user can view a task.
     */
    public static function canViewTask(int $userId, array $task): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        if ($globalRole === 'admin') {
            return true;
        }

        $teamId = self::resolveTaskTeamId($task);
        if ($teamId === null) {
            return true; // Global task
        }

        return TeamUser::isMember($teamId, $userId);
    }

    /**
     * Check if a user can edit a task.
     */
    public static function canEditTask(int $userId, array $task): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        if ($globalRole === 'viewer') {
            return false;
        }

        $teamId = self::resolveTaskTeamId($task);
        if ($teamId === null) {
            return in_array($globalRole, ['admin', 'member'], true);
        }

        if ($globalRole === 'admin') {
            return true;
        }

        $effectiveRole = self::getEffectiveRole($userId, $teamId);
        if ($effectiveRole === null) {
            return false;
        }

        return in_array($effectiveRole, ['admin', 'member'], true);
    }

    // ── Team admin access ──────────────────────────────────────────

    /**
     * Check if a user can manage a specific team.
     * Global admins or team_admins of the team.
     */
    public static function canManageTeam(int $userId, int $teamId): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === 'admin') {
            return true;
        }

        return TeamUser::isTeamAdmin($teamId, $userId);
    }

    // ── Visibility SQL helpers ─────────────────────────────────────

    /**
     * Build WHERE clause fragment for page visibility.
     * Returns [sql_fragment, params_array].
     *
     * @param int      $userId     Current user ID
     * @param string   $globalRole Current user global role
     * @param string   $tableAlias Table alias for pages (e.g. 'p')
     * @param int|null $filterTeamId  Specific team to filter by (from team switcher)
     * @return array{0: string, 1: array}
     */
    public static function pageVisibilityWhere(int $userId, string $globalRole, string $tableAlias = 'p', ?int $filterTeamId = null): array
    {
        // Global admin sees everything
        if ($globalRole === 'admin') {
            if ($filterTeamId !== null) {
                return ["({$tableAlias}.team_id IS NULL OR {$tableAlias}.team_id = ?)", [$filterTeamId]];
            }
            return ['1=1', []];
        }

        $teamIds = TeamUser::getTeamIds($userId);

        if ($filterTeamId !== null) {
            // Filter to specific team, but user must be member
            if (!in_array($filterTeamId, $teamIds, true)) {
                return ['0=1', []]; // No access
            }
            return ["({$tableAlias}.team_id IS NULL OR {$tableAlias}.team_id = ?)", [$filterTeamId]];
        }

        // "Alle Teams": show global pages + pages in user's teams
        if (empty($teamIds)) {
            return ["{$tableAlias}.team_id IS NULL", []];
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        return [
            "({$tableAlias}.team_id IS NULL OR {$tableAlias}.team_id IN ({$placeholders}))",
            $teamIds,
        ];
    }

    /**
     * Build WHERE clause fragment for task visibility.
     * Returns [sql_fragment, params_array].
     */
    public static function taskVisibilityWhere(int $userId, string $globalRole, string $tableAlias = 't', ?int $filterTeamId = null): array
    {
        if ($globalRole === 'admin') {
            if ($filterTeamId !== null) {
                return ["{$tableAlias}.team_id = ?", [$filterTeamId]];
            }
            return ['1=1', []];
        }

        $teamIds = TeamUser::getTeamIds($userId);

        if ($filterTeamId !== null) {
            if (!in_array($filterTeamId, $teamIds, true)) {
                return ['0=1', []];
            }
            return ["{$tableAlias}.team_id = ?", [$filterTeamId]];
        }

        if (empty($teamIds)) {
            return ["{$tableAlias}.team_id IS NULL", []];
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        return [
            "({$tableAlias}.team_id IS NULL OR {$tableAlias}.team_id IN ({$placeholders}))",
            $teamIds,
        ];
    }

    /**
     * Check if a user has access to an entity (page or task) by team.
     * Used for notification filtering, watcher validation, etc.
     */
    public static function userHasAccessToEntity(int $userId, string $entityType, int $entityId): bool
    {
        $globalRole = self::getUserGlobalRole($userId);
        if ($globalRole === 'admin') {
            return true;
        }

        if ($entityType === 'page') {
            $page = Page::findById($entityId);
            if (!$page) {
                return false;
            }
            return self::canViewPage($userId, $page);
        }

        if ($entityType === 'task') {
            $task = Task::findById($entityId);
            if (!$task) {
                return false;
            }
            return self::canViewTask($userId, $task);
        }

        return true;
    }

    /**
     * Get the global role of a user by ID.
     */
    private static function getUserGlobalRole(int $userId): ?string
    {
        // If it's the current session user, use session
        if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {
            return $_SESSION['user_role'] ?? null;
        }

        // Otherwise look up from DB
        $user = User::findById($userId);
        return $user ? ($user['role'] ?? null) : null;
    }

    /**
     * Get teams visible to a user for the team switcher.
     * Global admin sees all teams, others see their teams.
     */
    public static function getTeamsForSwitcher(int $userId, string $globalRole): array
    {
        if ($globalRole === 'admin') {
            return Team::all();
        }
        return Team::getTeamsForUser($userId);
    }
}
