<?php
/**
 * HomeDashboardService - Queries for the personal Home dashboard (AP22).
 *
 * All queries respect team visibility and exclude done tasks
 * from operative lists. No N+1 queries.
 */
class HomeDashboardService
{
    /**
     * Overdue tasks assigned to user (due_date < today, not done).
     * @return array
     */
    public static function overdue(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);

        $sql = "SELECT t.id, t.title, t.due_date, t.owner_id, t.board_id,
                       u.name AS owner_name,
                       bc.name AS column_name, bc.color AS column_color,
                       b.name AS board_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                LEFT JOIN boards b ON b.id = t.board_id
                WHERE t.owner_id = ?
                  AND t.due_date < CURDATE()
                  AND t.done_at IS NULL
                  AND {$visSql}
                ORDER BY t.due_date ASC
                LIMIT 20";

        return DB::fetchAll($sql, array_merge([$userId], $visParams));
    }

    /**
     * Tasks due today assigned to user (not done).
     */
    public static function dueToday(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);

        $sql = "SELECT t.id, t.title, t.due_date, t.owner_id, t.board_id,
                       u.name AS owner_name,
                       bc.name AS column_name, bc.color AS column_color,
                       b.name AS board_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                LEFT JOIN boards b ON b.id = t.board_id
                WHERE t.owner_id = ?
                  AND t.due_date = CURDATE()
                  AND t.done_at IS NULL
                  AND {$visSql}
                ORDER BY t.title ASC
                LIMIT 20";

        return DB::fetchAll($sql, array_merge([$userId], $visParams));
    }

    /**
     * Tasks due this week assigned to user (not done, not today, not overdue).
     */
    public static function dueThisWeek(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);

        $sql = "SELECT t.id, t.title, t.due_date, t.owner_id, t.board_id,
                       u.name AS owner_name,
                       bc.name AS column_name, bc.color AS column_color,
                       b.name AS board_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                LEFT JOIN boards b ON b.id = t.board_id
                WHERE t.owner_id = ?
                  AND t.due_date > CURDATE()
                  AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  AND t.done_at IS NULL
                  AND {$visSql}
                ORDER BY t.due_date ASC
                LIMIT 20";

        return DB::fetchAll($sql, array_merge([$userId], $visParams));
    }

    /**
     * All tasks assigned to user (not done).
     */
    public static function assignedToMe(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);

        $sql = "SELECT t.id, t.title, t.due_date, t.owner_id, t.board_id,
                       u.name AS owner_name,
                       bc.name AS column_name, bc.color AS column_color,
                       b.name AS board_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                LEFT JOIN boards b ON b.id = t.board_id
                WHERE t.owner_id = ?
                  AND t.done_at IS NULL
                  AND {$visSql}
                ORDER BY CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                         t.due_date ASC,
                         t.updated_at DESC
                LIMIT 20";

        return DB::fetchAll($sql, array_merge([$userId], $visParams));
    }

    /**
     * Tasks the user is watching (not done), sorted by last activity.
     */
    public static function watching(int $userId, string $globalRole, ?int $filterTeamId = null): array
    {
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);

        $sql = "SELECT t.id, t.title, t.due_date, t.owner_id, t.board_id, t.updated_at,
                       u.name AS owner_name,
                       bc.name AS column_name, bc.color AS column_color,
                       b.name AS board_name
                FROM tasks t
                INNER JOIN watchers w ON w.entity_type = 'task'
                  AND w.entity_id = t.id AND w.user_id = ?
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                LEFT JOIN boards b ON b.id = t.board_id
                WHERE t.done_at IS NULL
                  AND t.owner_id != ?
                  AND {$visSql}
                ORDER BY t.updated_at DESC
                LIMIT 15";

        return DB::fetchAll($sql, array_merge([$userId, $userId], $visParams));
    }

    /**
     * Get the first done-category column ID (for quick-done action).
     */
    public static function getDoneColumnId(): ?int
    {
        $col = DB::fetch("SELECT id FROM board_columns WHERE category = 'done' ORDER BY position ASC LIMIT 1");
        return $col ? (int) $col['id'] : null;
    }
}
