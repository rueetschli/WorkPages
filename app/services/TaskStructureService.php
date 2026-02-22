<?php
/**
 * TaskStructureService - AP25: Structure View logic.
 *
 * Responsibilities:
 *  - Validate parent-child relationships (type rules, team/board match, cycles)
 *  - Build a nested tree from a flat task array
 *  - Compute rollups (total/done counts, progress %) for epics and features
 *  - Provide reorder helpers
 */
class TaskStructureService
{
    /** Valid task types */
    public const TYPES = ['epic', 'feature', 'task'];

    /** Allowed parent type per child type */
    private const PARENT_RULE = [
        'epic'    => null,      // epics have no parent
        'feature' => 'epic',
        'task'    => 'feature',
    ];

    // ── Validation ──────────────────────────────────────────────────

    /**
     * Validate setting a parent on a task.
     *
     * @param array      $child      Full task row of the child
     * @param array|null $parent     Full task row of the parent, or null to remove parent
     * @return string|null  Error message (i18n key), or null on success
     */
    public static function validateParent(array $child, ?array $parent): ?string
    {
        // Removing parent is always allowed
        if ($parent === null) {
            return null;
        }

        $childType  = $child['task_type']  ?? 'task';
        $parentType = $parent['task_type'] ?? 'task';

        // Epics must not have a parent
        if ($childType === 'epic') {
            return 'structure.error.epic_no_parent';
        }

        // Check type rule
        $expectedParentType = self::PARENT_RULE[$childType] ?? null;
        if ($expectedParentType === null || $parentType !== $expectedParentType) {
            return 'structure.error.invalid_parent_type';
        }

        // Same board
        if ((int) $child['board_id'] !== (int) $parent['board_id']) {
            return 'structure.error.different_board';
        }

        // Same team (both null or both same value)
        $childTeam  = $child['team_id']  ?? null;
        $parentTeam = $parent['team_id'] ?? null;
        if ((string) $childTeam !== (string) $parentTeam) {
            return 'structure.error.different_team';
        }

        // No cycles
        if (self::wouldCreateCycle((int) $child['id'], (int) $parent['id'])) {
            return 'structure.error.cycle';
        }

        return null;
    }

    /**
     * Validate changing the type of an existing task.
     *
     * @param array  $task     Full task row
     * @param string $newType  Proposed new type
     * @return string|null  Error message (i18n key), or null on success
     */
    public static function validateTypeChange(array $task, string $newType): ?string
    {
        if (!in_array($newType, self::TYPES, true)) {
            return 'structure.error.invalid_type';
        }

        $currentType = $task['task_type'] ?? 'task';
        if ($currentType === $newType) {
            return null;
        }

        $children = Task::directChildren((int) $task['id']);

        // If task has children, new type must allow children
        if (!empty($children) && $newType === 'task') {
            return 'structure.error.task_cannot_have_children';
        }

        // Validate children still valid under new type
        if (!empty($children)) {
            $expectedChildType = match ($newType) {
                'epic'    => 'feature',
                'feature' => 'task',
                default   => null,
            };
            foreach ($children as $child) {
                if ($expectedChildType === null || ($child['task_type'] ?? '') !== $expectedChildType) {
                    return 'structure.error.children_type_mismatch';
                }
            }
        }

        // If new type requires a parent, check existing parent is compatible
        $parentId = $task['parent_task_id'] ?? null;
        if ($parentId !== null) {
            $parent = DB::fetch('SELECT id, task_type FROM tasks WHERE id = ?', [(int) $parentId]);
            if ($parent) {
                $expectedParentType = self::PARENT_RULE[$newType] ?? null;
                if ($expectedParentType === null || ($parent['task_type'] ?? '') !== $expectedParentType) {
                    return 'structure.error.invalid_parent_type';
                }
            }
        } elseif ($newType !== 'epic') {
            // Non-epic without parent is allowed (orphan feature/task)
        }

        return null;
    }

    // ── Cycle detection ─────────────────────────────────────────────

    /**
     * Check whether assigning $proposedParentId as parent of $taskId would create a cycle.
     * Walks up the ancestor chain of $proposedParentId.
     */
    public static function wouldCreateCycle(int $taskId, int $proposedParentId): bool
    {
        if ($taskId === $proposedParentId) {
            return true;
        }

        // Walk up ancestors of $proposedParentId; if we reach $taskId, it's a cycle
        $visited = [];
        $current = $proposedParentId;
        while ($current !== null) {
            if (isset($visited[$current])) {
                break; // already-existing cycle, stop
            }
            $visited[$current] = true;

            if ($current === $taskId) {
                return true;
            }

            $row = DB::fetch('SELECT parent_task_id FROM tasks WHERE id = ?', [$current]);
            if (!$row || $row['parent_task_id'] === null) {
                break;
            }
            $current = (int) $row['parent_task_id'];
        }

        return false;
    }

    // ── Tree building ────────────────────────────────────────────────

    /**
     * Build a nested tree from a flat task array.
     *
     * Input: flat array of task rows (from Task::allForStructure())
     * Output: array of root nodes, each with a 'children' key containing
     *         feature nodes, each of which has a 'children' key for tasks.
     *
     * Also computes 'rollup' on each epic/feature node:
     *   ['total' => int, 'done' => int, 'pct' => int]
     */
    public static function buildTree(array $flatTasks): array
    {
        // Index by id
        $byId = [];
        foreach ($flatTasks as $t) {
            $byId[(int) $t['id']] = $t;
            $byId[(int) $t['id']]['children'] = [];
        }

        $roots = [];

        // Attach children
        foreach ($byId as $id => $task) {
            $parentId = $task['parent_task_id'] !== null ? (int) $task['parent_task_id'] : null;
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }

        // Sort each level by structure_position
        usort($roots, fn($a, $b) => (int) $a['structure_position'] <=> (int) $b['structure_position']);
        foreach ($byId as &$node) {
            usort($node['children'], fn($a, $b) => (int) $a['structure_position'] <=> (int) $b['structure_position']);
        }
        unset($node);

        // Compute rollups bottom-up
        foreach ($roots as &$root) {
            self::computeRollup($root, $byId);
        }
        unset($root);

        return $roots;
    }

    /**
     * Recursively compute rollup for a node.
     * Returns ['total' => int, 'done' => int].
     */
    private static function computeRollup(array &$node, array &$byId): array
    {
        $type = $node['task_type'] ?? 'task';

        if ($type === 'task') {
            $isDone = ($node['column_category'] ?? '') === 'done';
            $rollup = ['total' => 1, 'done' => $isDone ? 1 : 0];
            $node['rollup'] = $rollup;
            return $rollup;
        }

        $total = 0;
        $done  = 0;

        foreach ($node['children'] as &$child) {
            $childRollup = self::computeRollup($child, $byId);
            $total += $childRollup['total'];
            $done  += $childRollup['done'];
        }
        unset($child);

        $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
        $node['rollup'] = ['total' => $total, 'done' => $done, 'pct' => $pct];
        return ['total' => $total, 'done' => $done];
    }

    // ── Reordering ───────────────────────────────────────────────────

    /**
     * Move a task up within its sibling group.
     */
    public static function moveUp(int $taskId, int $boardId, int $updatedBy): void
    {
        $task = DB::fetch('SELECT id, parent_task_id, structure_position FROM tasks WHERE id = ? AND board_id = ?', [$taskId, $boardId]);
        if (!$task) {
            return;
        }

        $parentId = $task['parent_task_id'];
        $pos      = (int) $task['structure_position'];

        // Find the sibling directly above
        if ($parentId === null) {
            $prev = DB::fetch(
                'SELECT id, structure_position FROM tasks WHERE board_id = ? AND parent_task_id IS NULL AND structure_position < ? ORDER BY structure_position DESC LIMIT 1',
                [$boardId, $pos]
            );
        } else {
            $prev = DB::fetch(
                'SELECT id, structure_position FROM tasks WHERE board_id = ? AND parent_task_id = ? AND structure_position < ? ORDER BY structure_position DESC LIMIT 1',
                [$boardId, $parentId, $pos]
            );
        }

        if (!$prev) {
            return;
        }

        // Swap positions
        $prevPos = (int) $prev['structure_position'];
        DB::query('UPDATE tasks SET structure_position = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$prevPos, $updatedBy, $taskId]);
        DB::query('UPDATE tasks SET structure_position = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$pos, $updatedBy, (int) $prev['id']]);
    }

    /**
     * Move a task down within its sibling group.
     */
    public static function moveDown(int $taskId, int $boardId, int $updatedBy): void
    {
        $task = DB::fetch('SELECT id, parent_task_id, structure_position FROM tasks WHERE id = ? AND board_id = ?', [$taskId, $boardId]);
        if (!$task) {
            return;
        }

        $parentId = $task['parent_task_id'];
        $pos      = (int) $task['structure_position'];

        if ($parentId === null) {
            $next = DB::fetch(
                'SELECT id, structure_position FROM tasks WHERE board_id = ? AND parent_task_id IS NULL AND structure_position > ? ORDER BY structure_position ASC LIMIT 1',
                [$boardId, $pos]
            );
        } else {
            $next = DB::fetch(
                'SELECT id, structure_position FROM tasks WHERE board_id = ? AND parent_task_id = ? AND structure_position > ? ORDER BY structure_position ASC LIMIT 1',
                [$boardId, $parentId, $pos]
            );
        }

        if (!$next) {
            return;
        }

        $nextPos = (int) $next['structure_position'];
        DB::query('UPDATE tasks SET structure_position = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$nextPos, $updatedBy, $taskId]);
        DB::query('UPDATE tasks SET structure_position = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$pos, $updatedBy, (int) $next['id']]);
    }
}
