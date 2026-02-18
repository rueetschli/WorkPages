<?php
/**
 * ApiScopeService - Scope checking and authorization for API requests (AP19).
 *
 * Combines API key scopes with user roles and team visibility.
 */
class ApiScopeService
{
    /** @var array|null Current authenticated API key row. */
    private static ?array $currentApiKey = null;

    /**
     * Set the current authenticated API key context.
     */
    public static function setApiKey(array $apiKey): void
    {
        self::$currentApiKey = $apiKey;
    }

    /**
     * Get the current API key context.
     */
    public static function getApiKey(): ?array
    {
        return self::$currentApiKey;
    }

    /**
     * Get the authenticated user ID from the API key.
     */
    public static function getUserId(): int
    {
        return (int) (self::$currentApiKey['user_id'] ?? 0);
    }

    /**
     * Get the user's global role.
     */
    public static function getUserRole(): string
    {
        return self::$currentApiKey['user_role'] ?? 'viewer';
    }

    /**
     * Get the key prefix.
     */
    public static function getKeyPrefix(): string
    {
        return self::$currentApiKey['key_prefix'] ?? '';
    }

    /**
     * Check if the current API key has a specific scope.
     */
    public static function hasScope(string $scope): bool
    {
        if (self::$currentApiKey === null) {
            return false;
        }

        $scopes = array_map('trim', explode(',', self::$currentApiKey['scopes'] ?? ''));
        return in_array($scope, $scopes, true);
    }

    /**
     * Require a specific scope. Sends 403 if not present.
     */
    public static function requireScope(string $scope): void
    {
        if (!self::hasScope($scope)) {
            ApiResponse::forbidden('Fehlender API-Scope: ' . $scope);
        }
    }

    /**
     * Check if the user role allows write operations.
     * Viewers cannot perform write operations regardless of scope.
     */
    public static function canWrite(): bool
    {
        $role = self::getUserRole();
        return in_array($role, ['admin', 'member'], true);
    }

    /**
     * Require write capability (role-based). Sends 403 if viewer.
     */
    public static function requireWrite(): void
    {
        if (!self::canWrite()) {
            ApiResponse::forbidden('Viewer-Rolle hat keinen Schreibzugriff.');
        }
    }

    /**
     * Check if user can view a task (team visibility).
     */
    public static function canViewTask(array $task): bool
    {
        $userId = self::getUserId();
        $role = self::getUserRole();

        if ($role === 'admin') {
            return true;
        }

        $teamId = $task['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        return TeamUser::isMember((int) $teamId, $userId);
    }

    /**
     * Check if user can edit a task (team visibility + role).
     */
    public static function canEditTask(array $task): bool
    {
        if (!self::canWrite()) {
            return false;
        }

        $userId = self::getUserId();
        $role = self::getUserRole();

        if ($role === 'admin') {
            return true;
        }

        $teamId = $task['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        $effectiveRole = TeamService::getEffectiveRole($userId, (int) $teamId);
        return $effectiveRole !== null && in_array($effectiveRole, ['admin', 'member'], true);
    }

    /**
     * Check if user can view a page (team visibility).
     */
    public static function canViewPage(array $page): bool
    {
        $userId = self::getUserId();
        $role = self::getUserRole();

        if ($role === 'admin') {
            return true;
        }

        $teamId = $page['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        return TeamUser::isMember((int) $teamId, $userId);
    }

    /**
     * Check if user can edit a page (team visibility + role).
     */
    public static function canEditPage(array $page): bool
    {
        if (!self::canWrite()) {
            return false;
        }

        $userId = self::getUserId();
        $role = self::getUserRole();

        if ($role === 'admin') {
            return true;
        }

        $teamId = $page['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        $effectiveRole = TeamService::getEffectiveRole($userId, (int) $teamId);
        return $effectiveRole !== null && in_array($effectiveRole, ['admin', 'member'], true);
    }

    /**
     * Build task visibility WHERE clause for API queries.
     * Returns [$sqlFragment, $params].
     */
    public static function taskVisibilityWhere(string $alias = 't'): array
    {
        $userId = self::getUserId();
        $role = self::getUserRole();
        return TeamService::taskVisibilityWhere($userId, $role, $alias);
    }

    /**
     * Build page visibility WHERE clause for API queries.
     * Returns [$sqlFragment, $params].
     */
    public static function pageVisibilityWhere(string $alias = 'p'): array
    {
        $userId = self::getUserId();
        $role = self::getUserRole();
        return TeamService::pageVisibilityWhere($userId, $role, $alias);
    }
}
