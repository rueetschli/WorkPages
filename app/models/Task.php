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
}
