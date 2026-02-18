<?php
/**
 * TaskFlowService - Manages started_at / done_at timestamps for flow metrics (AP18).
 *
 * Rules:
 *   - started_at: Set when task first enters an "active" category column.
 *                  Only set once (remains even if task moves back to backlog).
 *   - done_at:    Set when task enters a "done" category column.
 *                  Cleared if task moves back out of done (to preserve metric accuracy).
 *
 * This service must be called from every code path that changes a task's column:
 *   - BoardController::move()
 *   - TaskController::edit()
 *   - TaskController::updateStatus()
 */
class TaskFlowService
{
    /**
     * Update flow timestamps after a task column change.
     *
     * @param int $taskId       The task ID
     * @param int $oldColumnId  Previous column ID
     * @param int $newColumnId  New column ID
     */
    public static function onColumnChange(int $taskId, int $oldColumnId, int $newColumnId): void
    {
        if ($oldColumnId === $newColumnId) {
            return;
        }

        $newColumn = self::getColumnWithCategory($newColumnId);
        if (!$newColumn) {
            return;
        }

        $newCategory = $newColumn['category'] ?? 'active';
        $task = DB::fetch('SELECT started_at, done_at FROM tasks WHERE id = ?', [$taskId]);
        if (!$task) {
            return;
        }

        $sets   = [];
        $params = [];

        // Set started_at when entering active for the first time
        if ($newCategory === 'active' && $task['started_at'] === null) {
            $sets[]   = 'started_at = NOW()';
        }

        // Set done_at when entering done (only if not already set)
        if ($newCategory === 'done') {
            if ($task['started_at'] === null) {
                $sets[] = 'started_at = NOW()';
            }
            if ($task['done_at'] === null) {
                $sets[] = 'done_at = NOW()';
            }
        }

        // Clear done_at when leaving done category
        if ($newCategory !== 'done' && $task['done_at'] !== null) {
            $oldColumn = self::getColumnWithCategory($oldColumnId);
            $oldCategory = $oldColumn['category'] ?? 'active';
            if ($oldCategory === 'done') {
                $sets[]   = 'done_at = NULL';
            }
        }

        if (!empty($sets)) {
            $params[] = $taskId;
            DB::query(
                'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?',
                $params
            );
        }
    }

    /**
     * Set flow timestamps when a task is first created, based on the initial column category.
     *
     * @param int $taskId   The newly created task ID
     * @param int $columnId The column the task was created in
     */
    public static function onTaskCreated(int $taskId, int $columnId): void
    {
        $column = self::getColumnWithCategory($columnId);
        if (!$column) {
            return;
        }

        $category = $column['category'] ?? 'active';

        if ($category === 'active') {
            DB::query('UPDATE tasks SET started_at = NOW() WHERE id = ? AND started_at IS NULL', [$taskId]);
        } elseif ($category === 'done') {
            DB::query('UPDATE tasks SET started_at = NOW(), done_at = NOW() WHERE id = ?', [$taskId]);
        }
    }

    /**
     * Get a board column with its category field.
     */
    private static function getColumnWithCategory(int $columnId): ?array
    {
        return DB::fetch('SELECT id, category FROM board_columns WHERE id = ?', [$columnId]);
    }
}
