<?php
/**
 * Activity model - database operations for the activity log table.
 */
class Activity
{
    /**
     * Log an activity entry.
     *
     * @param string   $entityType  e.g. 'page'
     * @param int      $entityId    ID of the entity
     * @param string   $action      e.g. 'created', 'updated', 'deleted'
     * @param int      $createdBy   User ID who performed the action
     * @param array|null $meta      Optional metadata (stored as JSON)
     */
    public static function log(string $entityType, int $entityId, string $action, int $createdBy, ?array $meta = null): void
    {
        try {
            DB::query(
                'INSERT INTO activity (entity_type, entity_id, action, meta_json, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $entityType,
                    $entityId,
                    $action,
                    $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    $createdBy,
                ]
            );
        } catch (Throwable $e) {
            Logger::error('Failed to log activity', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'action'      => $action,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch recent activity for a given entity.
     */
    public static function forEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return DB::fetchAll(
            'SELECT a.*, u.name AS user_name
             FROM activity a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }
}
