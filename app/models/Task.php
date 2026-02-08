<?php
/**
 * Task model - database operations for the tasks table.
 */
class Task
{
    /** Valid status values. */
    public const STATUSES = ['backlog', 'ready', 'doing', 'review', 'done'];

    /** Human-readable status labels. */
    public const STATUS_LABELS = [
        'backlog' => 'Backlog',
        'ready'   => 'Ready',
        'doing'   => 'Doing',
        'review'  => 'Review',
        'done'    => 'Done',
    ];

    /**
     * Find a task by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT t.*, u.name AS owner_name, c.name AS creator_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.owner_id
             LEFT JOIN users c ON c.id = t.created_by
             WHERE t.id = ?',
            [$id]
        );
    }

    /**
     * Fetch all tasks with optional filters.
     *
     * Supported filters: status, owner_id, due_date, tag
     */
    public static function all(array $filters = []): array
    {
        $where  = [];
        $params = [];
        $join   = '';

        if (!empty($filters['status'])) {
            $where[]  = 't.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['owner_id'])) {
            $where[]  = 't.owner_id = ?';
            $params[] = (int) $filters['owner_id'];
        }

        if (!empty($filters['due_date'])) {
            $where[]  = 't.due_date = ?';
            $params[] = $filters['due_date'];
        }

        if (!empty($filters['tag'])) {
            $join     = ' INNER JOIN task_tags tt_filter ON tt_filter.task_id = t.id
                          INNER JOIN tags tg_filter ON tg_filter.id = tt_filter.tag_id AND tg_filter.name = ?';
            $params[] = mb_strtolower(trim($filters['tag']), 'UTF-8');
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "SELECT t.*, u.name AS owner_name, c.name AS creator_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN users c ON c.id = t.created_by
                {$join}
                {$whereSql}
                ORDER BY t.created_at DESC";

        return DB::fetchAll($sql, $params);
    }

    /**
     * Create a new task. Returns the new task ID.
     */
    public static function create(array $data): int
    {
        DB::query(
            'INSERT INTO tasks (title, description_md, status, owner_id, due_date, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['title'],
                $data['description_md'] ?? null,
                $data['status'] ?? 'backlog',
                !empty($data['owner_id']) ? (int) $data['owner_id'] : null,
                !empty($data['due_date']) ? $data['due_date'] : null,
                (int) $data['created_by'],
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Update an existing task by ID.
     */
    public static function update(int $id, array $data): void
    {
        $task = DB::fetch('SELECT * FROM tasks WHERE id = ?', [$id]);
        if (!$task) {
            return;
        }

        DB::query(
            'UPDATE tasks
             SET title = ?, description_md = ?, status = ?, owner_id = ?,
                 due_date = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['title'] ?? $task['title'],
                array_key_exists('description_md', $data) ? $data['description_md'] : $task['description_md'],
                $data['status'] ?? $task['status'],
                array_key_exists('owner_id', $data)
                    ? (!empty($data['owner_id']) ? (int) $data['owner_id'] : null)
                    : $task['owner_id'],
                array_key_exists('due_date', $data)
                    ? (!empty($data['due_date']) ? $data['due_date'] : null)
                    : $task['due_date'],
                (int) $data['updated_by'],
                $id,
            ]
        );
    }

    /**
     * Delete a task by ID.
     */
    public static function delete(int $id): void
    {
        DB::query('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    /**
     * Set tags for a task. Creates missing tags, syncs pivot table.
     *
     * @param int      $taskId   Task ID
     * @param string[] $tagNames Array of tag name strings
     */
    public static function setTags(int $taskId, array $tagNames): void
    {
        // Remove all existing tag associations
        DB::query('DELETE FROM task_tags WHERE task_id = ?', [$taskId]);

        foreach ($tagNames as $name) {
            $name = mb_strtolower(trim($name), 'UTF-8');
            if ($name === '') {
                continue;
            }

            // Find or create the tag
            $tag = DB::fetch('SELECT id FROM tags WHERE name = ?', [$name]);
            if (!$tag) {
                DB::query('INSERT INTO tags (name) VALUES (?)', [$name]);
                $tagId = (int) DB::lastInsertId();
            } else {
                $tagId = (int) $tag['id'];
            }

            DB::query(
                'INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)',
                [$taskId, $tagId]
            );
        }
    }

    /**
     * Get tags for a task as array of tag rows.
     */
    public static function getTags(int $taskId): array
    {
        return DB::fetchAll(
            'SELECT tg.id, tg.name
             FROM tags tg
             INNER JOIN task_tags tt ON tt.tag_id = tg.id
             WHERE tt.task_id = ?
             ORDER BY tg.name ASC',
            [$taskId]
        );
    }

    /**
     * Get all distinct tags used across tasks.
     */
    public static function allTags(): array
    {
        return DB::fetchAll(
            'SELECT DISTINCT tg.id, tg.name
             FROM tags tg
             INNER JOIN task_tags tt ON tt.tag_id = tg.id
             ORDER BY tg.name ASC'
        );
    }

    /**
     * Parse a comma-separated tag string into an array of trimmed, lowercased names.
     */
    public static function parseTagString(string $input): array
    {
        $tags = [];
        $parts = explode(',', $input);
        foreach ($parts as $part) {
            $name = mb_strtolower(trim($part), 'UTF-8');
            if ($name !== '') {
                $tags[] = $name;
            }
        }
        return array_unique($tags);
    }

    // ── Board (AP6) ─────────────────────────────────────────────────

    /**
     * Fetch all tasks for the Kanban board with optional filters.
     * Uses GROUP_CONCAT to load tags in a single query for performance.
     *
     * Supported filters: owner_id, tag, due, q (title search)
     *
     * @return array Tasks ordered by (status, position ASC, updated_at DESC)
     */
    public static function allForBoard(array $filters = []): array
    {
        $where  = [];
        $params = [];
        $join   = '';

        if (!empty($filters['owner_id'])) {
            $where[]  = 't.owner_id = ?';
            $params[] = (int) $filters['owner_id'];
        }

        if (!empty($filters['tag'])) {
            $join    .= ' INNER JOIN task_tags tt_filter ON tt_filter.task_id = t.id
                          INNER JOIN tags tg_filter ON tg_filter.id = tt_filter.tag_id AND tg_filter.name = ?';
            $params[] = mb_strtolower(trim($filters['tag']), 'UTF-8');
        }

        if (!empty($filters['due'])) {
            switch ($filters['due']) {
                case 'overdue':
                    $where[] = 't.due_date < CURDATE()';
                    break;
                case 'today':
                    $where[] = 't.due_date = CURDATE()';
                    break;
                case 'week':
                    $where[] = 't.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
                    break;
                case 'none':
                    $where[] = 't.due_date IS NULL';
                    break;
            }
        }

        if (!empty($filters['q'])) {
            $where[]  = 't.title LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "SELECT t.*, u.name AS owner_name,
                       GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ',') AS tag_list
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN task_tags tt ON tt.task_id = t.id
                LEFT JOIN tags tg ON tg.id = tt.tag_id
                {$join}
                {$whereSql}
                GROUP BY t.id
                ORDER BY t.status, t.position ASC, t.updated_at DESC";

        return DB::fetchAll($sql, $params);
    }

    /**
     * Initialize board positions for tasks that have position = 0.
     * Sets position in 1000-step increments per status group.
     */
    public static function initBoardPositions(): void
    {
        $needsInit = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM tasks WHERE position = 0'
        );

        if ((int) ($needsInit['cnt'] ?? 0) === 0) {
            return;
        }

        foreach (self::STATUSES as $status) {
            $tasks = DB::fetchAll(
                'SELECT id FROM tasks WHERE status = ? ORDER BY updated_at DESC, id ASC',
                [$status]
            );
            $pos = 1000;
            foreach ($tasks as $task) {
                DB::query(
                    'UPDATE tasks SET position = ? WHERE id = ? AND position = 0',
                    [$pos, (int) $task['id']]
                );
                $pos += 1000;
            }
        }
    }

    /**
     * Get the maximum position value for a given status.
     */
    public static function maxPosition(string $status): int
    {
        $row = DB::fetch(
            'SELECT MAX(position) AS max_pos FROM tasks WHERE status = ?',
            [$status]
        );
        return (int) ($row['max_pos'] ?? 0);
    }

    /**
     * Move a task to a new status and/or position.
     *
     * @param int    $taskId      Task ID
     * @param string $newStatus   Target status column
     * @param int|null $afterId   Task ID to place after (null = end of column)
     * @param int|null $beforeId  Task ID to place before (null = end of column)
     * @param int    $updatedBy   User ID performing the move
     */
    public static function moveToStatus(int $taskId, string $newStatus, ?int $afterId, ?int $beforeId, int $updatedBy): void
    {
        $newPosition = self::calculatePosition($newStatus, $afterId, $beforeId);

        DB::query(
            'UPDATE tasks SET status = ?, position = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
            [$newStatus, $newPosition, $updatedBy, $taskId]
        );
    }

    /**
     * Calculate the target position for inserting a task into a status column.
     */
    private static function calculatePosition(string $status, ?int $afterId, ?int $beforeId): int
    {
        // Both specified: place between them
        if ($afterId !== null && $beforeId !== null) {
            $afterPos  = self::getPosition($afterId);
            $beforePos = self::getPosition($beforeId);
            if ($afterPos !== null && $beforePos !== null) {
                if ($beforePos - $afterPos < 2) {
                    self::renumberColumn($status);
                    $afterPos  = self::getPosition($afterId);
                    $beforePos = self::getPosition($beforeId);
                }
                return (int) floor(($afterPos + $beforePos) / 2);
            }
        }

        // Only afterId: place right after it
        if ($afterId !== null) {
            $afterPos = self::getPosition($afterId);
            if ($afterPos !== null) {
                // Check if there's a next task
                $next = DB::fetch(
                    'SELECT position FROM tasks WHERE status = ? AND position > ? ORDER BY position ASC LIMIT 1',
                    [$status, $afterPos]
                );
                if ($next !== null) {
                    if ((int) $next['position'] - $afterPos < 2) {
                        self::renumberColumn($status);
                        $afterPos = self::getPosition($afterId);
                        $next = DB::fetch(
                            'SELECT position FROM tasks WHERE status = ? AND position > ? ORDER BY position ASC LIMIT 1',
                            [$status, $afterPos]
                        );
                    }
                    return (int) floor(($afterPos + (int) $next['position']) / 2);
                }
                return $afterPos + 1000;
            }
        }

        // Only beforeId: place right before it
        if ($beforeId !== null) {
            $beforePos = self::getPosition($beforeId);
            if ($beforePos !== null) {
                $prev = DB::fetch(
                    'SELECT position FROM tasks WHERE status = ? AND position < ? ORDER BY position DESC LIMIT 1',
                    [$status, $beforePos]
                );
                if ($prev !== null) {
                    if ($beforePos - (int) $prev['position'] < 2) {
                        self::renumberColumn($status);
                        $beforePos = self::getPosition($beforeId);
                        $prev = DB::fetch(
                            'SELECT position FROM tasks WHERE status = ? AND position < ? ORDER BY position DESC LIMIT 1',
                            [$status, $beforePos]
                        );
                    }
                    return (int) floor(((int) $prev['position'] + $beforePos) / 2);
                }
                return max(1, (int) floor($beforePos / 2));
            }
        }

        // Default: append at end
        return self::maxPosition($status) + 1000;
    }

    /**
     * Get position of a specific task.
     */
    private static function getPosition(int $taskId): ?int
    {
        $row = DB::fetch('SELECT position FROM tasks WHERE id = ?', [$taskId]);
        return $row !== null ? (int) $row['position'] : null;
    }

    /**
     * Renumber all tasks in a status column with 1000-step increments.
     */
    public static function renumberColumn(string $status): void
    {
        $tasks = DB::fetchAll(
            'SELECT id FROM tasks WHERE status = ? ORDER BY position ASC, id ASC',
            [$status]
        );
        $pos = 1000;
        foreach ($tasks as $task) {
            DB::query('UPDATE tasks SET position = ? WHERE id = ?', [$pos, (int) $task['id']]);
            $pos += 1000;
        }
    }

    /**
     * Reorder tasks within a status column based on an ordered list of task IDs.
     */
    public static function reorderColumn(string $status, array $taskIds): void
    {
        $pos = 1000;
        foreach ($taskIds as $taskId) {
            DB::query(
                'UPDATE tasks SET position = ? WHERE id = ? AND status = ?',
                [$pos, (int) $taskId, $status]
            );
            $pos += 1000;
        }
    }
}
