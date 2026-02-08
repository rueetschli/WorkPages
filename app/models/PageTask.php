<?php
/**
 * PageTask model - manages the many-to-many relation between pages and tasks.
 */
class PageTask
{
    /**
     * Get all tasks linked to a page, with optional filters.
     *
     * Supported filters: status, owner_id, due_date, tag
     *
     * @return array Tasks ordered by sort_order ASC
     */
    public static function getTasks(int $pageId, array $filters = []): array
    {
        $where  = ['pt.page_id = ?'];
        $params = [$pageId];
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

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT t.*, u.name AS owner_name, c.name AS creator_name, pt.sort_order
                FROM page_tasks pt
                INNER JOIN tasks t ON t.id = pt.task_id
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN users c ON c.id = t.created_by
                {$join}
                {$whereSql}
                ORDER BY pt.sort_order ASC, t.created_at DESC";

        return DB::fetchAll($sql, $params);
    }

    /**
     * Link a task to a page. The new task gets the highest sort_order + 1.
     */
    public static function addTask(int $pageId, int $taskId, int $createdBy): bool
    {
        $existing = DB::fetch(
            'SELECT 1 FROM page_tasks WHERE page_id = ? AND task_id = ?',
            [$pageId, $taskId]
        );

        if ($existing) {
            return false;
        }

        $maxRow = DB::fetch(
            'SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM page_tasks WHERE page_id = ?',
            [$pageId]
        );
        $nextSort = (int) $maxRow['max_sort'] + 1;

        DB::query(
            'INSERT INTO page_tasks (page_id, task_id, sort_order, created_by, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$pageId, $taskId, $nextSort, $createdBy]
        );

        return true;
    }

    /**
     * Remove the relation between a page and a task. The task itself is not deleted.
     */
    public static function removeTask(int $pageId, int $taskId): void
    {
        DB::query(
            'DELETE FROM page_tasks WHERE page_id = ? AND task_id = ?',
            [$pageId, $taskId]
        );
    }

    /**
     * Move a task up or down within a page's task list.
     *
     * @param string $direction 'up' or 'down'
     */
    public static function reorderTask(int $pageId, int $taskId, string $direction): void
    {
        $tasks = DB::fetchAll(
            'SELECT task_id, sort_order FROM page_tasks WHERE page_id = ? ORDER BY sort_order ASC',
            [$pageId]
        );

        $index = null;
        foreach ($tasks as $i => $row) {
            if ((int) $row['task_id'] === $taskId) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        $swapIndex = null;
        if ($direction === 'up' && $index > 0) {
            $swapIndex = $index - 1;
        } elseif ($direction === 'down' && $index < count($tasks) - 1) {
            $swapIndex = $index + 1;
        }

        if ($swapIndex === null) {
            return;
        }

        $sortA = (int) $tasks[$index]['sort_order'];
        $sortB = (int) $tasks[$swapIndex]['sort_order'];

        // If both have the same sort_order, assign distinct values
        if ($sortA === $sortB) {
            $sortB = $sortA + ($direction === 'up' ? -1 : 1);
        }

        DB::query(
            'UPDATE page_tasks SET sort_order = ? WHERE page_id = ? AND task_id = ?',
            [$sortB, $pageId, $taskId]
        );
        DB::query(
            'UPDATE page_tasks SET sort_order = ? WHERE page_id = ? AND task_id = ?',
            [$sortA, $pageId, (int) $tasks[$swapIndex]['task_id']]
        );
    }

    /**
     * Get all pages linked to a task.
     */
    public static function getPages(int $taskId): array
    {
        return DB::fetchAll(
            'SELECT p.id, p.title, p.slug
             FROM page_tasks pt
             INNER JOIN pages p ON p.id = pt.page_id AND p.deleted_at IS NULL
             WHERE pt.task_id = ?
             ORDER BY p.title ASC',
            [$taskId]
        );
    }

    /**
     * Search tasks by title for the add-dialog. Excludes tasks already linked to the given page.
     *
     * @return array Up to 10 matching tasks
     */
    public static function searchAvailableTasks(int $pageId, string $query): array
    {
        $query = '%' . $query . '%';

        return DB::fetchAll(
            'SELECT t.id, t.title, t.status, u.name AS owner_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.owner_id
             WHERE t.title LIKE ?
               AND t.id NOT IN (SELECT task_id FROM page_tasks WHERE page_id = ?)
             ORDER BY t.updated_at DESC, t.created_at DESC
             LIMIT 10',
            [$query, $pageId]
        );
    }
}
