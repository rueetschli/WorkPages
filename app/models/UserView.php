<?php
/**
 * UserView model – CRUD for saved views (AP27).
 *
 * Views are personal: every view belongs to exactly one user.
 * The `parameters` column stores filter/display JSON;
 * it is never interpreted inside SQL.
 */
class UserView
{
    /** Allowed view types. */
    const TYPES = ['board', 'structure', 'tasks'];

    // ── Finders ────────────────────────────────────────────────

    /**
     * Find a single view by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM user_views WHERE id = ?', [$id]);
    }

    /**
     * Find a view by ID, scoped to a user (ownership check).
     */
    public static function findByIdForUser(int $id, int $userId): ?array
    {
        return DB::fetch(
            'SELECT * FROM user_views WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    /**
     * Get all views for a user, ordered by type then name.
     */
    public static function allForUser(int $userId): array
    {
        try {
            return DB::fetchAll(
                'SELECT * FROM user_views WHERE user_id = ? ORDER BY view_type ASC, name ASC',
                [$userId]
            );
        } catch (PDOException $e) {
            if (self::isMissingTableError($e)) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get views for a user grouped by type.
     *
     * Returns: ['board' => [...], 'structure' => [...], 'tasks' => [...]]
     */
    public static function allForUserGrouped(int $userId): array
    {
        $rows = self::allForUser($userId);
        $grouped = ['board' => [], 'structure' => [], 'tasks' => []];
        foreach ($rows as $row) {
            $type = $row['view_type'] ?? 'tasks';
            $grouped[$type][] = $row;
        }
        return $grouped;
    }

    /**
     * Get views for a user filtered by type.
     */
    public static function allForUserByType(int $userId, string $type): array
    {
        return DB::fetchAll(
            'SELECT * FROM user_views WHERE user_id = ? AND view_type = ? ORDER BY name ASC',
            [$userId, $type]
        );
    }

    /**
     * Get the default view for a user (if any).
     */
    public static function getDefault(int $userId): ?array
    {
        return DB::fetch(
            'SELECT * FROM user_views WHERE user_id = ? AND is_default = 1 LIMIT 1',
            [$userId]
        );
    }

    // ── Create ─────────────────────────────────────────────────

    /**
     * Create a new view. Returns the new ID.
     *
     * @param array $data Keys: user_id, name, view_type, context_id, parameters, is_default
     */
    public static function create(array $data): int
    {
        $isDefault = !empty($data['is_default']);

        // If marking as default, clear any existing default first
        if ($isDefault) {
            self::clearDefault((int) $data['user_id']);
        }

        $params = $data['parameters'] ?? '{}';
        if (is_array($params)) {
            $params = json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        DB::query(
            'INSERT INTO user_views (user_id, name, view_type, context_id, parameters, is_default, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                (int) $data['user_id'],
                $data['name'],
                $data['view_type'],
                !empty($data['context_id']) ? (int) $data['context_id'] : null,
                $params,
                $isDefault ? 1 : 0,
            ]
        );

        return (int) DB::lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────

    /**
     * Update an existing view.
     *
     * @param int   $id   View ID
     * @param array $data Updatable keys: name, parameters, is_default
     */
    public static function update(int $id, array $data): void
    {
        $sets   = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $sets[]   = 'name = ?';
            $params[] = $data['name'];
        }

        if (array_key_exists('parameters', $data)) {
            $p = $data['parameters'];
            if (is_array($p)) {
                $p = json_encode($p, JSON_UNESCAPED_UNICODE);
            }
            $sets[]   = 'parameters = ?';
            $params[] = $p;
        }

        if (array_key_exists('is_default', $data)) {
            $isDefault = !empty($data['is_default']);
            if ($isDefault) {
                // Need user_id to clear other defaults
                $view = self::findById($id);
                if ($view) {
                    self::clearDefault((int) $view['user_id']);
                }
            }
            $sets[]   = 'is_default = ?';
            $params[] = $isDefault ? 1 : 0;
        }

        if (empty($sets)) {
            return;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $id;

        DB::query(
            'UPDATE user_views SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    // ── Delete ─────────────────────────────────────────────────

    /**
     * Delete a view (ownership must be checked by caller).
     */
    public static function delete(int $id): void
    {
        DB::query('DELETE FROM user_views WHERE id = ?', [$id]);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Clear is_default for all views of a user.
     */
    private static function clearDefault(int $userId): void
    {
        DB::query(
            'UPDATE user_views SET is_default = 0, updated_at = NOW() WHERE user_id = ? AND is_default = 1',
            [$userId]
        );
    }

    /**
     * Decode the parameters JSON from a view row.
     * Returns an associative array.
     */
    public static function decodeParameters(array $view): array
    {
        $raw = $view['parameters'] ?? '{}';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a URL for a saved view based on its type, context and parameters.
     */
    public static function buildUrl(array $view, string $baseUrl): string
    {
        $params = self::decodeParameters($view);
        $type   = $view['view_type'] ?? 'tasks';
        $ctxId  = $view['context_id'] ?? null;

        switch ($type) {
            case 'board':
                $query = ['r' => 'board_view'];
                if ($ctxId) {
                    $query['id'] = (int) $ctxId;
                }
                // Map stored filter keys to GET params
                foreach (['owner_id', 'tag', 'due', 'q'] as $key) {
                    if (!empty($params[$key])) {
                        $query[$key] = $params[$key];
                    }
                }
                break;

            case 'structure':
                $query = ['r' => 'structure'];
                if ($ctxId) {
                    $query['board_id'] = (int) $ctxId;
                }
                foreach (['owner_id', 'tag', 'status'] as $key) {
                    if (!empty($params[$key])) {
                        $query[$key] = $params[$key];
                    }
                }
                break;

            case 'tasks':
            default:
                $query = ['r' => 'tasks'];
                foreach (['column_id', 'owner_id', 'tag', 'board_id', 'sprint_id', 'due'] as $key) {
                    if (!empty($params[$key])) {
                        $query[$key] = $params[$key];
                    }
                }
                break;
        }

        return rtrim($baseUrl, '/') . '/?' . http_build_query($query);
    }

    /**
     * Count views for a user (for sanity limits).
     */
    public static function countForUser(int $userId): int
    {
        try {
            $row = DB::fetch(
                'SELECT COUNT(*) AS cnt FROM user_views WHERE user_id = ?',
                [$userId]
            );
            return (int) ($row['cnt'] ?? 0);
        } catch (PDOException $e) {
            if (self::isMissingTableError($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * Validate view type.
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    private static function isMissingTableError(PDOException $e): bool
    {
        return ($e->getCode() === '42S02')
            || (strpos($e->getMessage(), '1146') !== false && strpos($e->getMessage(), 'user_views') !== false);
    }
}
