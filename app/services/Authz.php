<?php
/**
 * Authorization Service - Central access control layer (AP9).
 *
 * Defines all actions as constants and maps them to allowed roles.
 * Every write route must call Authz::require() before processing.
 */
class Authz
{
    // ── Role hierarchy (lower index = more privileges) ──────────────
    private const ROLE_HIERARCHY = ['admin', 'member', 'viewer'];

    // ── Action constants ────────────────────────────────────────────

    // Pages
    const PAGE_VIEW   = 'page.view';
    const PAGE_CREATE = 'page.create';
    const PAGE_EDIT   = 'page.edit';
    const PAGE_DELETE = 'page.delete';

    // Tasks
    const TASK_VIEW          = 'task.view';
    const TASK_CREATE        = 'task.create';
    const TASK_EDIT          = 'task.edit';
    const TASK_DELETE        = 'task.delete';
    const TASK_CHANGE_STATUS = 'task.change_status';

    // Page-Task relations
    const PAGE_TASK_LINK    = 'page_task.link';
    const PAGE_TASK_UNLINK  = 'page_task.unlink';
    const PAGE_TASK_REORDER = 'page_task.reorder';

    // Comments
    const COMMENT_CREATE = 'comment.create';
    const COMMENT_DELETE = 'comment.delete';

    // Board
    const BOARD_VIEW    = 'board.view';
    const BOARD_MOVE    = 'board.move';
    const BOARD_REORDER = 'board.reorder';

    // Board Columns (AP13)
    const BOARD_COLUMNS_MANAGE = 'board.columns.manage';

    // Search
    const SEARCH_VIEW = 'search.view';

    // Admin
    const ADMIN_USERS_MANAGE    = 'admin.users.manage';
    const ADMIN_SETTINGS_MANAGE = 'admin.settings.manage';

    // Sharing
    const SHARE_CREATE = 'share.create';
    const SHARE_REVOKE = 'share.revoke';

    // ── Action-to-roles mapping ─────────────────────────────────────

    /**
     * Maps each action to the minimum set of roles allowed.
     * viewer: only view/search actions
     * member: view + create/edit/delete + comment + share
     * admin: everything
     */
    private const ACTION_ROLES = [
        // Pages
        self::PAGE_VIEW   => ['admin', 'member', 'viewer'],
        self::PAGE_CREATE => ['admin', 'member'],
        self::PAGE_EDIT   => ['admin', 'member'],
        self::PAGE_DELETE => ['admin', 'member'],

        // Tasks
        self::TASK_VIEW          => ['admin', 'member', 'viewer'],
        self::TASK_CREATE        => ['admin', 'member'],
        self::TASK_EDIT          => ['admin', 'member'],
        self::TASK_DELETE        => ['admin', 'member'],
        self::TASK_CHANGE_STATUS => ['admin', 'member'],

        // Page-Task relations
        self::PAGE_TASK_LINK    => ['admin', 'member'],
        self::PAGE_TASK_UNLINK  => ['admin', 'member'],
        self::PAGE_TASK_REORDER => ['admin', 'member'],

        // Comments
        self::COMMENT_CREATE => ['admin', 'member'],
        self::COMMENT_DELETE => ['admin', 'member'],

        // Board
        self::BOARD_VIEW    => ['admin', 'member', 'viewer'],
        self::BOARD_MOVE    => ['admin', 'member'],
        self::BOARD_REORDER => ['admin', 'member'],

        // Board Columns (AP13)
        self::BOARD_COLUMNS_MANAGE => ['admin', 'member'],

        // Search
        self::SEARCH_VIEW => ['admin', 'member', 'viewer'],

        // Admin
        self::ADMIN_USERS_MANAGE    => ['admin'],
        self::ADMIN_SETTINGS_MANAGE => ['admin'],

        // Sharing
        self::SHARE_CREATE => ['admin', 'member'],
        self::SHARE_REVOKE => ['admin', 'member'],
    ];

    /**
     * Check whether the current user is allowed to perform the given action.
     *
     * @param string $action  One of the action constants defined above
     * @return bool
     */
    public static function can(string $action): bool
    {
        $userRole = $_SESSION['user_role'] ?? null;

        if ($userRole === null) {
            return false;
        }

        $allowedRoles = self::ACTION_ROLES[$action] ?? [];
        return in_array($userRole, $allowedRoles, true);
    }

    /**
     * Require the current user to be allowed the given action.
     * Sends 403 and renders the error page if denied.
     *
     * @param string $action  One of the action constants defined above
     */
    public static function require(string $action): void
    {
        Security::requireLogin();

        if (!self::can($action)) {
            self::deny();
        }
    }

    /**
     * Require the user to have at least one of the given roles.
     *
     * @param string|string[] $roles
     */
    public static function requireRole(string|array $roles): void
    {
        Security::requireLogin();

        if (is_string($roles)) {
            $roles = [$roles];
        }

        $userRole = $_SESSION['user_role'] ?? null;
        if ($userRole === null || !in_array($userRole, $roles, true)) {
            self::deny();
        }
    }

    /**
     * Send HTTP 403 and render a clean error page. Exits immediately.
     */
    public static function deny(): void
    {
        http_response_code(403);
        $pageTitle = 'Zugriff verweigert';
        require APP_DIR . '/views/403.php';
        exit;
    }
}
