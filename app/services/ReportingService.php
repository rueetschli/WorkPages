<?php
/**
 * ReportingService - All reporting queries and aggregations for AP18.
 *
 * All methods accept a $filters array with standardised keys:
 *   team_id, from, to, owner_id, tag, column_id
 *
 * Visibility is enforced via TeamService::taskVisibilityWhere().
 * No N+1 queries. All aggregations use GROUP BY.
 */
class ReportingService
{
    // ── Filter helpers ────────────────────────────────────────────────

    /**
     * Build WHERE clauses and params from standard filter array.
     * Returns [$whereParts, $params, $joinSql].
     */
    private static function buildFilterClauses(array $filters, int $userId, string $globalRole): array
    {
        $where  = [];
        $whereParams = [];
        $joinParams = [];
        $join   = '';

        // Team visibility
        $filterTeamId = !empty($filters['team_id']) ? (int) $filters['team_id'] : null;
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);
        $where[] = $visSql;
        $whereParams = array_merge($whereParams, $visParams);

        // Date range on done_at (for throughput/cycle time reports)
        if (!empty($filters['from'])) {
            $where[]  = 't.done_at >= ?';
            $whereParams[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[]  = 't.done_at <= ?';
            $whereParams[] = $filters['to'] . ' 23:59:59';
        }

        // Owner
        if (!empty($filters['owner_id'])) {
            $where[]  = 't.owner_id = ?';
            $whereParams[] = (int) $filters['owner_id'];
        }

        // Tag (join)
        if (!empty($filters['tag'])) {
            $join    .= ' INNER JOIN task_tags tt_filter ON tt_filter.task_id = t.id
                          INNER JOIN tags tg_filter ON tg_filter.id = tt_filter.tag_id AND tg_filter.name = ?';
            $joinParams[] = mb_strtolower(trim($filters['tag']), 'UTF-8');
        }

        // Column
        if (!empty($filters['column_id'])) {
            $where[]  = 't.column_id = ?';
            $whereParams[] = (int) $filters['column_id'];
        }

        return [$where, array_merge($joinParams, $whereParams), $join];
    }

    /**
     * Build filter clauses using created_at as date range (for aging/open tasks).
     */
    private static function buildOpenFilterClauses(array $filters, int $userId, string $globalRole): array
    {
        $where  = [];
        $whereParams = [];
        $joinParams = [];
        $join   = '';

        $filterTeamId = !empty($filters['team_id']) ? (int) $filters['team_id'] : null;
        [$visSql, $visParams] = TeamService::taskVisibilityWhere($userId, $globalRole, 't', $filterTeamId);
        $where[] = $visSql;
        $whereParams = array_merge($whereParams, $visParams);

        if (!empty($filters['owner_id'])) {
            $where[]  = 't.owner_id = ?';
            $whereParams[] = (int) $filters['owner_id'];
        }

        if (!empty($filters['tag'])) {
            $join    .= ' INNER JOIN task_tags tt_filter ON tt_filter.task_id = t.id
                          INNER JOIN tags tg_filter ON tg_filter.id = tt_filter.tag_id AND tg_filter.name = ?';
            $joinParams[] = mb_strtolower(trim($filters['tag']), 'UTF-8');
        }

        if (!empty($filters['column_id'])) {
            $where[]  = 't.column_id = ?';
            $whereParams[] = (int) $filters['column_id'];
        }

        return [$where, array_merge($joinParams, $whereParams), $join];
    }

    // ── Overview KPIs ─────────────────────────────────────────────────

    /**
     * Get current WIP count (tasks in active columns).
     */
    public static function getWipCount(array $filters, int $userId, string $globalRole): int
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category = ?';
        $params[] = 'active';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             {$join}
             {$whereSql}",
            $params
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get throughput (tasks completed in date range).
     */
    public static function getThroughput(array $filters, int $userId, string $globalRole): int
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM tasks t
             {$join}
             {$whereSql}",
            $params
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get average cycle time in days for completed tasks in range.
     */
    public static function getAvgCycleTime(array $filters, int $userId, string $globalRole): ?float
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';
        $where[] = 't.started_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, t.started_at, t.done_at) / 24.0) AS avg_days
             FROM tasks t
             {$join}
             {$whereSql}",
            $params
        );

        return $row['avg_days'] !== null ? round((float) $row['avg_days'], 1) : null;
    }

    /**
     * Get overdue count.
     */
    public static function getOverdueCount(array $filters, int $userId, string $globalRole): int
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.due_date < CURDATE()';
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             {$join}
             {$whereSql}",
            $params
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get bottleneck column (active column with highest WIP).
     */
    public static function getBottleneckColumn(array $filters, int $userId, string $globalRole): ?array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category = ?';
        $params[] = 'active';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetch(
            "SELECT bc.id, bc.name, COUNT(*) AS task_count
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             {$join}
             {$whereSql}
             GROUP BY bc.id, bc.name
             ORDER BY task_count DESC
             LIMIT 1",
            $params
        );
    }

    /**
     * Get top tags in period (based on completed tasks).
     */
    public static function getTopTags(array $filters, int $userId, string $globalRole, int $limit = 5): array
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT tg.name, COUNT(DISTINCT t.id) AS task_count
             FROM tasks t
             INNER JOIN task_tags tt ON tt.task_id = t.id
             INNER JOIN tags tg ON tg.id = tt.tag_id
             {$join}
             {$whereSql}
             GROUP BY tg.name
             ORDER BY task_count DESC
             LIMIT " . (int) $limit,
            $params
        );
    }

    /**
     * Get top overdue tasks.
     */
    public static function getTopOverdueTasks(array $filters, int $userId, string $globalRole, int $limit = 10): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.due_date < CURDATE()';
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT t.id, t.title, t.due_date, t.owner_id, u.name AS owner_name,
                    bc.name AS column_name, DATEDIFF(CURDATE(), t.due_date) AS overdue_days
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             LEFT JOIN users u ON u.id = t.owner_id
             {$join}
             {$whereSql}
             ORDER BY t.due_date ASC
             LIMIT " . (int) $limit,
            $params
        );
    }

    /**
     * Get top aged open tasks.
     */
    public static function getTopAgedTasks(array $filters, int $userId, string $globalRole, int $limit = 10): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT t.id, t.title, t.created_at, t.started_at, t.owner_id, u.name AS owner_name,
                    bc.name AS column_name,
                    DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) AS age_days
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             LEFT JOIN users u ON u.id = t.owner_id
             {$join}
             {$whereSql}
             ORDER BY age_days DESC
             LIMIT " . (int) $limit,
            $params
        );
    }

    // ── Flow Report ───────────────────────────────────────────────────

    /**
     * Throughput per ISO week in the date range.
     */
    public static function getThroughputPerWeek(array $filters, int $userId, string $globalRole): array
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT YEARWEEK(t.done_at, 3) AS yw,
                    MIN(DATE(t.done_at)) AS week_start,
                    COUNT(*) AS throughput
             FROM tasks t
             {$join}
             {$whereSql}
             GROUP BY YEARWEEK(t.done_at, 3)
             ORDER BY yw ASC",
            $params
        );
    }

    /**
     * Get cycle time data for percentile calculation.
     * Returns sorted array of cycle times in days.
     */
    public static function getCycleTimesArray(array $filters, int $userId, string $globalRole): array
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';
        $where[] = 't.started_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = DB::fetchAll(
            "SELECT TIMESTAMPDIFF(HOUR, t.started_at, t.done_at) / 24.0 AS cycle_days
             FROM tasks t
             {$join}
             {$whereSql}
             ORDER BY cycle_days ASC",
            $params
        );

        return array_map(fn($r) => round((float) $r['cycle_days'], 2), $rows);
    }

    /**
     * Compute percentiles from a sorted array.
     *
     * @param float[] $sorted Sorted array of values
     * @param float   $p      Percentile (0-100)
     * @return float|null
     */
    public static function percentile(array $sorted, float $p): ?float
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }
        if ($n === 1) {
            return $sorted[0];
        }

        $rank = ($p / 100.0) * ($n - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);
        $frac  = $rank - $lower;

        if ($lower === $upper || $upper >= $n) {
            return round($sorted[$lower], 1);
        }

        return round($sorted[$lower] + $frac * ($sorted[$upper] - $sorted[$lower]), 1);
    }

    /**
     * Get cycle time summary (avg, p50, p85, p95).
     */
    public static function getCycleTimeSummary(array $filters, int $userId, string $globalRole): array
    {
        $cycleTimes = self::getCycleTimesArray($filters, $userId, $globalRole);
        $count = count($cycleTimes);

        if ($count === 0) {
            return [
                'count'   => 0,
                'average' => null,
                'p50'     => null,
                'p85'     => null,
                'p95'     => null,
            ];
        }

        $sum = array_sum($cycleTimes);

        return [
            'count'   => $count,
            'average' => round($sum / $count, 1),
            'p50'     => self::percentile($cycleTimes, 50),
            'p85'     => self::percentile($cycleTimes, 85),
            'p95'     => self::percentile($cycleTimes, 95),
        ];
    }

    /**
     * Get WIP per column (current snapshot).
     */
    public static function getWipPerColumn(array $filters, int $userId, string $globalRole): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);

        $joinSql = ' AND (' . implode(' AND ', $where) . ')';

        return DB::fetchAll(
            "SELECT bc.id AS column_id, bc.name AS column_name, bc.category, bc.position,
                    COUNT(t.id) AS task_count
             FROM board_columns bc
             LEFT JOIN tasks t ON t.column_id = bc.id{$join}{$joinSql}
             GROUP BY bc.id, bc.name, bc.category, bc.position
             ORDER BY bc.position ASC",
            $params
        );
    }

    /**
     * Generate or retrieve WIP snapshot for a date range.
     * Creates snapshots on demand for dates that don't have them.
     */
    public static function getWipTrend(array $filters, int $userId, string $globalRole): array
    {
        $from = $filters['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $filters['to'] ?? date('Y-m-d');
        $filterTeamId = !empty($filters['team_id']) ? (int) $filters['team_id'] : null;

        // Generate today's snapshot if missing
        self::ensureTodaySnapshot($filterTeamId);

        $where  = ['rs.snap_date BETWEEN ? AND ?'];
        $params = [$from, $to];

        if ($filterTeamId !== null) {
            $where[]  = 'rs.team_id = ?';
            $params[] = $filterTeamId;
        } else {
            $where[] = 'rs.team_id IS NULL';
        }

        $where[] = 'bc.category = ?';
        $params[] = 'active';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return DB::fetchAll(
            "SELECT rs.snap_date, SUM(rs.task_count) AS wip
             FROM report_snapshots rs
             INNER JOIN board_columns bc ON bc.id = rs.column_id
             {$whereSql}
             GROUP BY rs.snap_date
             ORDER BY rs.snap_date ASC",
            $params
        );
    }

    /**
     * Create today's WIP snapshot if it doesn't exist.
     */
    public static function ensureTodaySnapshot(?int $teamId = null): void
    {
        $today = date('Y-m-d');

        // Check if snapshot exists for today
        if ($teamId !== null) {
            $existing = DB::fetch(
                'SELECT id FROM report_snapshots WHERE snap_date = ? AND team_id = ? LIMIT 1',
                [$today, $teamId]
            );
        } else {
            $existing = DB::fetch(
                'SELECT id FROM report_snapshots WHERE snap_date = ? AND team_id IS NULL LIMIT 1',
                [$today]
            );
        }

        if ($existing) {
            return;
        }

        // Generate snapshot
        $columns = BoardColumn::allOrdered();
        foreach ($columns as $col) {
            $colId = (int) $col['id'];

            if ($teamId !== null) {
                $row = DB::fetch(
                    'SELECT COUNT(*) AS cnt FROM tasks WHERE column_id = ? AND team_id = ?',
                    [$colId, $teamId]
                );
            } else {
                $row = DB::fetch(
                    'SELECT COUNT(*) AS cnt FROM tasks WHERE column_id = ?',
                    [$colId]
                );
            }

            $count = (int) ($row['cnt'] ?? 0);

            DB::query(
                'INSERT INTO report_snapshots (snap_date, team_id, column_id, task_count)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE task_count = VALUES(task_count)',
                [$today, $teamId, $colId, $count]
            );
        }
    }

    // ── Aging Report ──────────────────────────────────────────────────

    /**
     * Get aging bucket distribution for open tasks.
     *
     * Buckets: 0-2, 3-7, 8-14, 15-30, 31-60, 60+
     */
    public static function getAgingBuckets(array $filters, int $userId, string $globalRole): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) BETWEEN 0 AND 2 THEN 1 ELSE 0 END) AS d0_2,
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) BETWEEN 3 AND 7 THEN 1 ELSE 0 END) AS d3_7,
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS d8_14,
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) BETWEEN 15 AND 30 THEN 1 ELSE 0 END) AS d15_30,
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS d31_60,
                SUM(CASE WHEN DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) > 60 THEN 1 ELSE 0 END) AS d60plus
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             {$join}
             {$whereSql}",
            $params
        );

        return [
            ['label' => '0 - 2 Tage',  'count' => (int) ($row['d0_2'] ?? 0)],
            ['label' => '3 - 7 Tage',  'count' => (int) ($row['d3_7'] ?? 0)],
            ['label' => '8 - 14 Tage', 'count' => (int) ($row['d8_14'] ?? 0)],
            ['label' => '15 - 30 Tage','count' => (int) ($row['d15_30'] ?? 0)],
            ['label' => '31 - 60 Tage','count' => (int) ($row['d31_60'] ?? 0)],
            ['label' => '60+ Tage',    'count' => (int) ($row['d60plus'] ?? 0)],
        ];
    }

    /**
     * Get overdue bucket distribution.
     *
     * Buckets: 1-3, 4-7, 8-14, 15+
     */
    public static function getOverdueBuckets(array $filters, int $userId, string $globalRole): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category != ?';
        $params[] = 'done';
        $where[] = 't.due_date < CURDATE()';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $row = DB::fetch(
            "SELECT
                SUM(CASE WHEN DATEDIFF(CURDATE(), t.due_date) BETWEEN 1 AND 3 THEN 1 ELSE 0 END) AS d1_3,
                SUM(CASE WHEN DATEDIFF(CURDATE(), t.due_date) BETWEEN 4 AND 7 THEN 1 ELSE 0 END) AS d4_7,
                SUM(CASE WHEN DATEDIFF(CURDATE(), t.due_date) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS d8_14,
                SUM(CASE WHEN DATEDIFF(CURDATE(), t.due_date) > 14 THEN 1 ELSE 0 END) AS d15plus
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             {$join}
             {$whereSql}",
            $params
        );

        return [
            ['label' => '1 - 3 Tage',   'count' => (int) ($row['d1_3'] ?? 0)],
            ['label' => '4 - 7 Tage',   'count' => (int) ($row['d4_7'] ?? 0)],
            ['label' => '8 - 14 Tage',  'count' => (int) ($row['d8_14'] ?? 0)],
            ['label' => '15+ Tage',     'count' => (int) ($row['d15plus'] ?? 0)],
        ];
    }

    /**
     * Get oldest open tasks (paginated).
     */
    public static function getOldestOpenTasks(array $filters, int $userId, string $globalRole, int $limit = 50): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $tasks = DB::fetchAll(
            "SELECT t.id, t.title, t.created_at, t.started_at, t.due_date,
                    t.owner_id, u.name AS owner_name,
                    bc.name AS column_name,
                    DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) AS age_days,
                    CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE()
                         THEN DATEDIFF(CURDATE(), t.due_date)
                         ELSE NULL END AS overdue_days
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             LEFT JOIN users u ON u.id = t.owner_id
             {$join}
             {$whereSql}
             ORDER BY age_days DESC
             LIMIT " . (int) $limit,
            $params
        );

        // Load tags in batch
        if (!empty($tasks)) {
            $tasks = self::attachTagsBatch($tasks);
        }

        return $tasks;
    }

    // ── Export helpers ─────────────────────────────────────────────────

    /**
     * Get flow export data (completed tasks with cycle/lead time).
     */
    public static function getFlowExportData(array $filters, int $userId, string $globalRole, int $limit = 5000): array
    {
        [$where, $params, $join] = self::buildFilterClauses($filters, $userId, $globalRole);
        $where[] = 't.done_at IS NOT NULL';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $tasks = DB::fetchAll(
            "SELECT t.id, t.title, t.owner_id, u.name AS owner_name,
                    t.team_id, tm.name AS team_name,
                    t.started_at, t.done_at, t.created_at,
                    CASE WHEN t.started_at IS NOT NULL
                         THEN ROUND(TIMESTAMPDIFF(HOUR, t.started_at, t.done_at) / 24.0, 2)
                         ELSE NULL END AS cycle_time_days,
                    ROUND(TIMESTAMPDIFF(HOUR, t.created_at, t.done_at) / 24.0, 2) AS lead_time_days
             FROM tasks t
             LEFT JOIN users u ON u.id = t.owner_id
             LEFT JOIN teams tm ON tm.id = t.team_id
             {$join}
             {$whereSql}
             ORDER BY t.done_at DESC
             LIMIT " . (int) $limit,
            $params
        );

        if (!empty($tasks)) {
            $tasks = self::attachTagsBatch($tasks);
        }

        return $tasks;
    }

    /**
     * Get aging export data (open tasks with age/overdue).
     */
    public static function getAgingExportData(array $filters, int $userId, string $globalRole, int $limit = 5000): array
    {
        [$where, $params, $join] = self::buildOpenFilterClauses($filters, $userId, $globalRole);
        $where[] = 'bc.category != ?';
        $params[] = 'done';

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $tasks = DB::fetchAll(
            "SELECT t.id, t.title, t.owner_id, u.name AS owner_name,
                    t.team_id, tm.name AS team_name,
                    t.created_at, t.started_at, t.due_date,
                    bc.name AS column_name,
                    DATEDIFF(NOW(), COALESCE(t.started_at, t.created_at)) AS age_days,
                    CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE()
                         THEN DATEDIFF(CURDATE(), t.due_date)
                         ELSE NULL END AS overdue_days
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             LEFT JOIN users u ON u.id = t.owner_id
             LEFT JOIN teams tm ON tm.id = t.team_id
             {$join}
             {$whereSql}
             ORDER BY age_days DESC
             LIMIT " . (int) $limit,
            $params
        );

        if (!empty($tasks)) {
            $tasks = self::attachTagsBatch($tasks);
        }

        return $tasks;
    }

    // ── Tag batch loading ────────────────────────────────────────────

    /**
     * Attach tags to a list of tasks in a single query (no N+1).
     */
    private static function attachTagsBatch(array $tasks): array
    {
        $taskIds = array_column($tasks, 'id');
        if (empty($taskIds)) {
            return $tasks;
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $tagRows = DB::fetchAll(
            "SELECT tt.task_id, tg.name
             FROM task_tags tt
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tt.task_id IN ({$placeholders})
             ORDER BY tg.name",
            $taskIds
        );

        $tagMap = [];
        foreach ($tagRows as $tr) {
            $tagMap[(int) $tr['task_id']][] = $tr['name'];
        }

        foreach ($tasks as &$task) {
            $task['tags'] = $tagMap[(int) $task['id']] ?? [];
            $task['tag_list'] = implode(', ', $task['tags']);
        }
        unset($task);

        return $tasks;
    }

    // ── Filter presets ────────────────────────────────────────────────

    /**
     * Resolve date range from preset or custom values.
     *
     * @param array $input GET parameters
     * @return array ['from' => string, 'to' => string]
     */
    public static function resolveDateRange(array $input): array
    {
        if (!empty($input['from']) && !empty($input['to'])) {
            return [
                'from' => $input['from'],
                'to'   => $input['to'],
            ];
        }

        $preset = $input['preset'] ?? '30d';

        switch ($preset) {
            case '7d':
                return [
                    'from' => date('Y-m-d', strtotime('-7 days')),
                    'to'   => date('Y-m-d'),
                ];
            case '90d':
                return [
                    'from' => date('Y-m-d', strtotime('-90 days')),
                    'to'   => date('Y-m-d'),
                ];
            case 'quarter':
                $month = (int) date('n');
                $year  = (int) date('Y');
                $qStart = (int) (floor(($month - 1) / 3) * 3 + 1);
                return [
                    'from' => sprintf('%04d-%02d-01', $year, $qStart),
                    'to'   => date('Y-m-d'),
                ];
            case '30d':
            default:
                return [
                    'from' => date('Y-m-d', strtotime('-30 days')),
                    'to'   => date('Y-m-d'),
                ];
        }
    }

    /**
     * Build a standard filters array from GET parameters.
     */
    public static function parseFilters(array $input): array
    {
        $dateRange = self::resolveDateRange($input);

        return [
            'team_id'   => !empty($input['team_id']) ? (int) $input['team_id'] : null,
            'from'      => $dateRange['from'],
            'to'        => $dateRange['to'],
            'owner_id'  => !empty($input['owner_id']) ? (int) $input['owner_id'] : null,
            'tag'       => !empty($input['tag']) ? trim($input['tag']) : null,
            'column_id' => !empty($input['column_id']) ? (int) $input['column_id'] : null,
            'preset'    => $input['preset'] ?? '30d',
        ];
    }
}
