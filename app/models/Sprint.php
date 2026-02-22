<?php
/**
 * Sprint model - database operations for the sprints table (AP26).
 */
class Sprint
{
    /**
     * Find a sprint by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT s.*, b.name AS board_name, b.team_id, u.name AS creator_name
             FROM sprints s
             LEFT JOIN boards b ON b.id = s.board_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * Get all sprints for a board, ordered by status priority then start_date.
     */
    public static function allForBoard(int $boardId): array
    {
        return DB::fetchAll(
            "SELECT s.*, u.name AS creator_name
             FROM sprints s
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.board_id = ?
             ORDER BY FIELD(s.status, 'active', 'planned', 'closed'), s.start_date DESC",
            [$boardId]
        );
    }

    /**
     * Get the active sprint for a board (max one).
     */
    public static function getActiveForBoard(int $boardId): ?array
    {
        return DB::fetch(
            "SELECT * FROM sprints WHERE board_id = ? AND status = 'active' LIMIT 1",
            [$boardId]
        );
    }

    /**
     * Get planned sprints for a board.
     */
    public static function getPlannedForBoard(int $boardId): array
    {
        return DB::fetchAll(
            "SELECT * FROM sprints WHERE board_id = ? AND status = 'planned' ORDER BY start_date ASC",
            [$boardId]
        );
    }

    /**
     * Get closed sprints for a board, most recent first.
     */
    public static function getClosedForBoard(int $boardId, int $limit = 10): array
    {
        return DB::fetchAll(
            "SELECT * FROM sprints WHERE board_id = ? AND status = 'closed' ORDER BY closed_at DESC LIMIT " . (int) $limit,
            [$boardId]
        );
    }

    /**
     * Check if a board already has an active sprint.
     */
    public static function hasActiveSprint(int $boardId): bool
    {
        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt FROM sprints WHERE board_id = ? AND status = 'active'",
            [$boardId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Create a new sprint. Returns the new sprint ID.
     */
    public static function create(array $data): int
    {
        DB::query(
            "INSERT INTO sprints (board_id, name, start_date, end_date, status, created_by, created_at)
             VALUES (?, ?, ?, ?, 'planned', ?, NOW())",
            [
                (int) $data['board_id'],
                $data['name'],
                $data['start_date'],
                $data['end_date'],
                (int) $data['created_by'],
            ]
        );
        return (int) DB::lastInsertId();
    }

    /**
     * Activate a sprint (status = active).
     */
    public static function activate(int $id): void
    {
        DB::query(
            "UPDATE sprints SET status = 'active' WHERE id = ? AND status = 'planned'",
            [$id]
        );
    }

    /**
     * Close a sprint (status = closed, set closed_at).
     */
    public static function close(int $id): void
    {
        DB::query(
            "UPDATE sprints SET status = 'closed', closed_at = NOW() WHERE id = ? AND status = 'active'",
            [$id]
        );
    }

    /**
     * Update sprint name and dates (only while planned).
     */
    public static function update(int $id, array $data): void
    {
        $sets   = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[]   = 'name = ?';
            $params[] = $data['name'];
        }
        if (array_key_exists('start_date', $data)) {
            $sets[]   = 'start_date = ?';
            $params[] = $data['start_date'];
        }
        if (array_key_exists('end_date', $data)) {
            $sets[]   = 'end_date = ?';
            $params[] = $data['end_date'];
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $id;
        DB::query(
            'UPDATE sprints SET ' . implode(', ', $sets) . " WHERE id = ? AND status = 'planned'",
            $params
        );
    }

    /**
     * Delete a sprint (only if planned). Unassigns tasks.
     */
    public static function delete(int $id): void
    {
        DB::query('UPDATE tasks SET sprint_id = NULL WHERE sprint_id = ?', [$id]);
        DB::query("DELETE FROM sprint_daily_metrics WHERE sprint_id = ?", [$id]);
        DB::query("DELETE FROM sprints WHERE id = ? AND status = 'planned'", [$id]);
    }

    /**
     * Count tasks assigned to a sprint.
     */
    public static function taskCount(int $sprintId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM tasks WHERE sprint_id = ?', [$sprintId]);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get tasks for a sprint with column info.
     */
    public static function getTasks(int $sprintId): array
    {
        return DB::fetchAll(
            'SELECT t.*, u.name AS owner_name,
                    bc.name AS column_name, bc.category AS column_category
             FROM tasks t
             LEFT JOIN users u ON u.id = t.owner_id
             LEFT JOIN board_columns bc ON bc.id = t.column_id
             WHERE t.sprint_id = ?
             ORDER BY bc.position ASC, t.position ASC',
            [$sprintId]
        );
    }

    /**
     * Count remaining (not done) tasks in a sprint.
     */
    public static function remainingTaskCount(int $sprintId): int
    {
        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             WHERE t.sprint_id = ? AND bc.category != 'done'",
            [$sprintId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count completed (done) tasks in a sprint.
     */
    public static function completedTaskCount(int $sprintId): int
    {
        $row = DB::fetch(
            "SELECT COUNT(*) AS cnt
             FROM tasks t
             INNER JOIN board_columns bc ON bc.id = t.column_id
             WHERE t.sprint_id = ? AND bc.category = 'done'",
            [$sprintId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Assign a task to a sprint.
     */
    public static function assignTask(int $taskId, int $sprintId): void
    {
        DB::query('UPDATE tasks SET sprint_id = ? WHERE id = ?', [$sprintId, $taskId]);
    }

    /**
     * Remove a task from its sprint.
     */
    public static function unassignTask(int $taskId): void
    {
        DB::query('UPDATE tasks SET sprint_id = NULL WHERE id = ?', [$taskId]);
    }

    /**
     * Get all sprints for a board that can be assigned tasks (planned or active).
     */
    public static function assignableForBoard(int $boardId): array
    {
        return DB::fetchAll(
            "SELECT id, name, status, start_date, end_date
             FROM sprints
             WHERE board_id = ? AND status IN ('planned', 'active')
             ORDER BY FIELD(status, 'active', 'planned'), start_date ASC",
            [$boardId]
        );
    }

    /**
     * Get velocity data: completed task count per closed sprint for a board.
     */
    public static function getVelocityData(int $boardId, int $limit = 8): array
    {
        $sprints = DB::fetchAll(
            "SELECT s.id, s.name, s.start_date, s.end_date, s.closed_at
             FROM sprints s
             WHERE s.board_id = ? AND s.status = 'closed'
             ORDER BY s.closed_at DESC
             LIMIT " . (int) $limit,
            [$boardId]
        );

        $result = [];
        foreach (array_reverse($sprints) as $sprint) {
            $completed = DB::fetch(
                "SELECT COUNT(*) AS cnt
                 FROM tasks t
                 INNER JOIN board_columns bc ON bc.id = t.column_id
                 WHERE t.sprint_id = ? AND bc.category = 'done'",
                [(int) $sprint['id']]
            );
            $result[] = [
                'id'         => (int) $sprint['id'],
                'name'       => $sprint['name'],
                'start_date' => $sprint['start_date'],
                'end_date'   => $sprint['end_date'],
                'closed_at'  => $sprint['closed_at'],
                'completed'  => (int) ($completed['cnt'] ?? 0),
            ];
        }

        return $result;
    }
}
