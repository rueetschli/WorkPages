<?php
/**
 * SprintService - Sprint lifecycle, snapshot logic, and chart data (AP26).
 *
 * Handles:
 * - Sprint activation and closure
 * - On-demand daily snapshot generation (no cron)
 * - Burndown chart data
 * - Velocity chart data
 */
class SprintService
{
    // ── Lifecycle ────────────────────────────────────────────────────

    /**
     * Activate a sprint: set status to active, create initial snapshot.
     * Validates that no other sprint is active on the same board.
     *
     * @return string|null Error message key on failure, null on success.
     */
    public static function activate(int $sprintId, int $userId): ?string
    {
        $sprint = Sprint::findById($sprintId);
        if (!$sprint) {
            return 'sprint.error.not_found';
        }

        if ($sprint['status'] !== 'planned') {
            return 'sprint.error.not_planned';
        }

        if (Sprint::hasActiveSprint((int) $sprint['board_id'])) {
            return 'sprint.error.already_active';
        }

        Sprint::activate($sprintId);

        // Create initial snapshot
        self::createSnapshot($sprintId, $sprint['start_date']);

        ActivityService::log('sprint', $sprintId, 'sprint_started', $userId, [
            'sprint_name' => $sprint['name'],
            'board_id'    => (int) $sprint['board_id'],
        ]);

        return null;
    }

    /**
     * Close a sprint: set status to closed, create final snapshot.
     *
     * @return string|null Error message key on failure, null on success.
     */
    public static function close(int $sprintId, int $userId): ?string
    {
        $sprint = Sprint::findById($sprintId);
        if (!$sprint) {
            return 'sprint.error.not_found';
        }

        if ($sprint['status'] !== 'active') {
            return 'sprint.error.not_active';
        }

        // Final snapshot for today
        self::createSnapshot($sprintId, date('Y-m-d'));

        Sprint::close($sprintId);

        ActivityService::log('sprint', $sprintId, 'sprint_closed', $userId, [
            'sprint_name' => $sprint['name'],
            'board_id'    => (int) $sprint['board_id'],
        ]);

        return null;
    }

    // ── Snapshot Logic ──────────────────────────────────────────────

    /**
     * Create a snapshot for a specific date.
     * Counts remaining and completed tasks at the current moment.
     */
    public static function createSnapshot(int $sprintId, string $date): void
    {
        $totalCount     = Sprint::taskCount($sprintId);
        $remainingCount = Sprint::remainingTaskCount($sprintId);
        $completedCount = Sprint::completedTaskCount($sprintId);

        // Upsert: replace if date already exists
        $existing = DB::fetch(
            'SELECT id FROM sprint_daily_metrics WHERE sprint_id = ? AND date = ?',
            [$sprintId, $date]
        );

        if ($existing) {
            DB::query(
                'UPDATE sprint_daily_metrics
                 SET total_task_count = ?, remaining_task_count = ?, completed_task_count = ?, created_at = NOW()
                 WHERE sprint_id = ? AND date = ?',
                [$totalCount, $remainingCount, $completedCount, $sprintId, $date]
            );
        } else {
            DB::query(
                'INSERT INTO sprint_daily_metrics (sprint_id, date, total_task_count, remaining_task_count, completed_task_count, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$sprintId, $date, $totalCount, $remainingCount, $completedCount]
            );
        }
    }

    /**
     * Ensure snapshots exist for all days from sprint start to today (or end_date if closed).
     * Called on-demand when viewing reports.
     */
    public static function ensureSnapshots(int $sprintId): void
    {
        $sprint = Sprint::findById($sprintId);
        if (!$sprint || $sprint['status'] === 'planned') {
            return;
        }

        $startDate = $sprint['start_date'];
        $today     = date('Y-m-d');
        $endDate   = ($sprint['status'] === 'closed')
            ? min($sprint['end_date'], substr($sprint['closed_at'] ?? $today, 0, 10))
            : min($sprint['end_date'], $today);

        // Get existing snapshot dates
        $existing = DB::fetchAll(
            'SELECT date FROM sprint_daily_metrics WHERE sprint_id = ? ORDER BY date ASC',
            [$sprintId]
        );
        $existingDates = array_column($existing, 'date');

        // Fill missing dates
        $current = new DateTime($startDate);
        $end     = new DateTime($endDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            if (!in_array($dateStr, $existingDates, true)) {
                // For past dates we can only record current state; this is a best-effort approach
                self::createSnapshot($sprintId, $dateStr);
            }
            $current->modify('+1 day');
        }

        // Always refresh today's snapshot for active sprints
        if ($sprint['status'] === 'active' && $today >= $startDate && $today <= $sprint['end_date']) {
            self::createSnapshot($sprintId, $today);
        }
    }

    // ── Chart Data ──────────────────────────────────────────────────

    /**
     * Get burndown chart data for a sprint.
     *
     * Returns:
     *   [
     *     'dates'      => ['2026-02-01', '2026-02-02', ...],
     *     'ideal'      => [10, 9.5, 9, ...],
     *     'actual'     => [10, 10, 8, ...],
     *     'commitment' => 10,
     *   ]
     */
    public static function getBurndownData(int $sprintId): array
    {
        $sprint = Sprint::findById($sprintId);
        if (!$sprint) {
            return ['dates' => [], 'ideal' => [], 'actual' => [], 'commitment' => 0];
        }

        // Ensure snapshots are up to date
        self::ensureSnapshots($sprintId);

        $metrics = DB::fetchAll(
            'SELECT date, total_task_count, remaining_task_count, completed_task_count
             FROM sprint_daily_metrics
             WHERE sprint_id = ?
             ORDER BY date ASC',
            [$sprintId]
        );

        if (empty($metrics)) {
            return ['dates' => [], 'ideal' => [], 'actual' => [], 'commitment' => 0];
        }

        // Build date range from sprint start to end
        $startDate = $sprint['start_date'];
        $endDate   = $sprint['end_date'];

        $dates   = [];
        $current = new DateTime($startDate);
        $end     = new DateTime($endDate);
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        // Commitment = total tasks at sprint start (from first snapshot)
        $commitment = (int) ($metrics[0]['total_task_count'] ?? 0);
        if ($commitment === 0) {
            // Fallback: use total tasks currently assigned
            $commitment = Sprint::taskCount($sprintId);
        }

        // Ideal line: linear from commitment to 0
        $totalDays = count($dates);
        $ideal = [];
        for ($i = 0; $i < $totalDays; $i++) {
            if ($totalDays > 1) {
                $ideal[] = round($commitment * (1 - $i / ($totalDays - 1)), 2);
            } else {
                $ideal[] = 0;
            }
        }

        // Actual line: from snapshots
        $metricsByDate = [];
        foreach ($metrics as $m) {
            $metricsByDate[$m['date']] = (int) $m['remaining_task_count'];
        }

        $actual    = [];
        $lastKnown = $commitment;
        $today     = date('Y-m-d');
        foreach ($dates as $date) {
            if (isset($metricsByDate[$date])) {
                $lastKnown = $metricsByDate[$date];
                $actual[]  = $lastKnown;
            } elseif ($date <= $today) {
                // Fill with last known value
                $actual[] = $lastKnown;
            } else {
                // Future: null (no data yet)
                $actual[] = null;
            }
        }

        return [
            'dates'      => $dates,
            'ideal'      => $ideal,
            'actual'     => $actual,
            'commitment' => $commitment,
        ];
    }

    /**
     * Get velocity data for a board (last N closed sprints).
     *
     * Returns:
     *   [
     *     'sprints'  => [['name' => 'Sprint 1', 'completed' => 12], ...],
     *     'average'  => 10.5,
     *   ]
     */
    public static function getVelocityData(int $boardId, int $limit = 8): array
    {
        $sprints = Sprint::getVelocityData($boardId, $limit);

        $total = 0;
        foreach ($sprints as $s) {
            $total += $s['completed'];
        }

        $average = count($sprints) > 0 ? round($total / count($sprints), 1) : 0;

        return [
            'sprints' => $sprints,
            'average' => $average,
        ];
    }

    // ── Task Assignment ─────────────────────────────────────────────

    /**
     * Validate and assign a task to a sprint.
     *
     * @return string|null Error message key on failure, null on success.
     */
    public static function assignTask(int $taskId, int $sprintId, int $userId): ?string
    {
        $task = Task::findById($taskId);
        if (!$task) {
            return 'sprint.error.task_not_found';
        }

        $sprint = Sprint::findById($sprintId);
        if (!$sprint) {
            return 'sprint.error.not_found';
        }

        // Task and sprint must belong to the same board
        if ((int) ($task['board_id'] ?? 0) !== (int) $sprint['board_id']) {
            return 'sprint.error.different_board';
        }

        // Only assign to planned or active sprints
        if (!in_array($sprint['status'], ['planned', 'active'], true)) {
            return 'sprint.error.sprint_closed';
        }

        Sprint::assignTask($taskId, $sprintId);

        ActivityService::log('task', $taskId, 'task_sprint_added', $userId, [
            'sprint_id'   => $sprintId,
            'sprint_name' => $sprint['name'],
        ]);

        return null;
    }

    /**
     * Remove a task from its sprint.
     */
    public static function unassignTask(int $taskId, int $userId): void
    {
        $task = Task::findById($taskId);
        if (!$task || empty($task['sprint_id'])) {
            return;
        }

        $sprint = Sprint::findById((int) $task['sprint_id']);
        Sprint::unassignTask($taskId);

        ActivityService::log('task', $taskId, 'task_sprint_removed', $userId, [
            'sprint_id'   => $sprint ? (int) $sprint['id'] : null,
            'sprint_name' => $sprint ? $sprint['name'] : '',
        ]);
    }
}
