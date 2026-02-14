<?php
/**
 * BoardColumn model - database operations for the board_columns table (AP13).
 */
class BoardColumn
{
    /**
     * Get all columns ordered by position.
     */
    public static function allOrdered(): array
    {
        return DB::fetchAll(
            'SELECT * FROM board_columns ORDER BY position ASC, id ASC'
        );
    }

    /**
     * Find a column by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM board_columns WHERE id = ?', [$id]);
    }

    /**
     * Find a column by slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        return DB::fetch('SELECT * FROM board_columns WHERE slug = ?', [$slug]);
    }

    /**
     * Get the default column (is_default = 1).
     * Falls back to the column with the lowest position.
     */
    public static function getDefault(): ?array
    {
        $col = DB::fetch('SELECT * FROM board_columns WHERE is_default = 1 LIMIT 1');
        if ($col) {
            return $col;
        }
        return DB::fetch('SELECT * FROM board_columns ORDER BY position ASC LIMIT 1');
    }

    /**
     * Get the default column ID.
     */
    public static function getDefaultId(): int
    {
        $col = self::getDefault();
        return $col ? (int) $col['id'] : 0;
    }

    /**
     * Count total columns.
     */
    public static function count(): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM board_columns');
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count tasks in a given column.
     */
    public static function taskCount(int $columnId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM tasks WHERE column_id = ?', [$columnId]);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Create a new column. Returns the new column ID.
     */
    public static function create(array $data): int
    {
        $slug = self::generateSlug($data['name']);

        DB::query(
            'INSERT INTO board_columns (name, slug, position, color, wip_limit, is_default, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())',
            [
                $data['name'],
                $slug,
                (int) ($data['position'] ?? self::nextPosition()),
                !empty($data['color']) ? $data['color'] : null,
                !empty($data['wip_limit']) ? (int) $data['wip_limit'] : null,
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Update a column.
     */
    public static function update(int $id, array $data): void
    {
        $col = self::findById($id);
        if (!$col) {
            return;
        }

        $sets   = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[]   = 'name = ?';
            $params[] = $data['name'];
            $sets[]   = 'slug = ?';
            $params[] = self::generateSlug($data['name']);
        }

        if (array_key_exists('color', $data)) {
            $sets[]   = 'color = ?';
            $params[] = !empty($data['color']) ? $data['color'] : null;
        }

        if (array_key_exists('wip_limit', $data)) {
            $sets[]   = 'wip_limit = ?';
            $params[] = !empty($data['wip_limit']) ? (int) $data['wip_limit'] : null;
        }

        if (empty($sets)) {
            return;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $id;

        DB::query(
            'UPDATE board_columns SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Delete a column and move its tasks to a target column.
     */
    public static function delete(int $id, int $targetColumnId): void
    {
        // Move all tasks to target column
        DB::query(
            'UPDATE tasks SET column_id = ? WHERE column_id = ?',
            [$targetColumnId, $id]
        );

        // If the deleted column was default, make target the new default
        $col = self::findById($id);
        if ($col && (int) $col['is_default'] === 1) {
            DB::query('UPDATE board_columns SET is_default = 1 WHERE id = ?', [$targetColumnId]);
        }

        DB::query('DELETE FROM board_columns WHERE id = ?', [$id]);
    }

    /**
     * Move a column up (swap with previous).
     */
    public static function moveUp(int $id): void
    {
        $col = self::findById($id);
        if (!$col) {
            return;
        }

        $prev = DB::fetch(
            'SELECT * FROM board_columns WHERE position < ? ORDER BY position DESC LIMIT 1',
            [(int) $col['position']]
        );

        if ($prev) {
            DB::query('UPDATE board_columns SET position = ? WHERE id = ?', [(int) $prev['position'], $id]);
            DB::query('UPDATE board_columns SET position = ? WHERE id = ?', [(int) $col['position'], (int) $prev['id']]);
        }
    }

    /**
     * Move a column down (swap with next).
     */
    public static function moveDown(int $id): void
    {
        $col = self::findById($id);
        if (!$col) {
            return;
        }

        $next = DB::fetch(
            'SELECT * FROM board_columns WHERE position > ? ORDER BY position ASC LIMIT 1',
            [(int) $col['position']]
        );

        if ($next) {
            DB::query('UPDATE board_columns SET position = ? WHERE id = ?', [(int) $next['position'], $id]);
            DB::query('UPDATE board_columns SET position = ? WHERE id = ?', [(int) $col['position'], (int) $next['id']]);
        }
    }

    /**
     * Set a column as the default (for new tasks).
     */
    public static function setDefault(int $id): void
    {
        DB::query('UPDATE board_columns SET is_default = 0 WHERE is_default = 1');
        DB::query('UPDATE board_columns SET is_default = 1 WHERE id = ?', [$id]);
    }

    /**
     * Get next position value (max + 1000).
     */
    public static function nextPosition(): int
    {
        $row = DB::fetch('SELECT MAX(position) AS max_pos FROM board_columns');
        return (int) ($row['max_pos'] ?? 0) + 1000;
    }

    /**
     * Generate a URL-safe slug from a column name.
     */
    private static function generateSlug(string $name): string
    {
        $slug = mb_strtolower(trim($name), 'UTF-8');
        // Replace umlauts
        $slug = str_replace(
            ['ae', 'oe', 'ue', 'ss'],
            ['ae', 'oe', 'ue', 'ss'],
            $slug
        );
        // Replace non-alphanumeric with hyphens
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'column';
    }

    /**
     * Get all columns as id => name map (for dropdowns).
     */
    public static function allForDropdown(): array
    {
        $columns = self::allOrdered();
        $map = [];
        foreach ($columns as $col) {
            $map[(int) $col['id']] = $col['name'];
        }
        return $map;
    }
}
