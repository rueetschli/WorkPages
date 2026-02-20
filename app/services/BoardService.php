<?php
/**
 * BoardService - Board-level authorization and visibility logic (AP21).
 */
class BoardService
{
    /**
     * Check if a user can view a board.
     */
    public static function canView(int $userId, array $board): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        // Global admin sees everything
        if ($globalRole === 'admin') {
            return true;
        }

        // Global board (no team) - visible to all logged-in users
        $teamId = $board['team_id'] ?? null;
        if ($teamId === null) {
            return true;
        }

        // Team board - must be team member
        return TeamUser::isMember((int) $teamId, $userId);
    }

    /**
     * Check if a user can create/edit/delete boards.
     * Global admin or team_admin of the board's team.
     */
    public static function canManage(int $userId, array $board): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        if ($globalRole === 'admin') {
            return true;
        }

        $teamId = $board['team_id'] ?? null;
        if ($teamId === null) {
            // Global boards: only admin can manage
            return false;
        }

        // Team board: team_admin can manage
        return TeamUser::isTeamAdmin((int) $teamId, $userId);
    }

    /**
     * Check if a user can create tasks on a board.
     * Requires at least member role (global or team).
     */
    public static function canCreateTask(int $userId, array $board): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        if ($globalRole === 'viewer') {
            return false;
        }

        if ($globalRole === 'admin') {
            return true;
        }

        $teamId = $board['team_id'] ?? null;
        if ($teamId === null) {
            return in_array($globalRole, ['admin', 'member'], true);
        }

        $effectiveRole = TeamService::getEffectiveRole($userId, (int) $teamId);
        if ($effectiveRole === null) {
            return false;
        }

        return in_array($effectiveRole, ['admin', 'member'], true);
    }

    /**
     * Check if a user can move tasks on a board.
     */
    public static function canMoveTask(int $userId, array $board): bool
    {
        return self::canCreateTask($userId, $board);
    }

    /**
     * Check if a user can create a new board for a given team.
     */
    public static function canCreateBoard(int $userId, ?int $teamId): bool
    {
        $globalRole = $_SESSION['user_role'] ?? null;
        if ($globalRole === null) {
            return false;
        }

        if ($globalRole === 'admin') {
            return true;
        }

        if ($teamId === null) {
            // Only admin can create global boards
            return false;
        }

        return TeamUser::isTeamAdmin($teamId, $userId);
    }

    /**
     * Store the last visited board ID for a user in session.
     */
    public static function setLastBoardId(int $boardId): void
    {
        $_SESSION['last_board_id'] = $boardId;
    }

    /**
     * Get the last visited board ID from session.
     */
    public static function getLastBoardId(): ?int
    {
        return isset($_SESSION['last_board_id']) ? (int) $_SESSION['last_board_id'] : null;
    }
}
