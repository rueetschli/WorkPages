<?php
/**
 * Watcher model - database operations for the watchers table (AP15).
 *
 * Manages subscriptions so users receive notifications when
 * pages or tasks they watch are updated.
 */
class Watcher
{
    /**
     * Check if a user is watching an entity.
     */
    public static function isWatching(string $entityType, int $entityId, int $userId): bool
    {
        $row = DB::fetch(
            'SELECT id FROM watchers WHERE entity_type = ? AND entity_id = ? AND user_id = ?',
            [$entityType, $entityId, $userId]
        );
        return $row !== false && $row !== null;
    }

    /**
     * Add a watcher. Returns true if inserted, false if already existed.
     */
    public static function watch(string $entityType, int $entityId, int $userId): bool
    {
        if (self::isWatching($entityType, $entityId, $userId)) {
            return false;
        }

        DB::query(
            'INSERT INTO watchers (entity_type, entity_id, user_id, created_at)
             VALUES (?, ?, ?, NOW())',
            [$entityType, $entityId, $userId]
        );
        return true;
    }

    /**
     * Remove a watcher. Returns true if removed, false if not found.
     */
    public static function unwatch(string $entityType, int $entityId, int $userId): bool
    {
        $stmt = DB::query(
            'DELETE FROM watchers WHERE entity_type = ? AND entity_id = ? AND user_id = ?',
            [$entityType, $entityId, $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all watcher user IDs for an entity.
     *
     * @return int[]
     */
    public static function getWatcherIds(string $entityType, int $entityId): array
    {
        $rows = DB::fetchAll(
            'SELECT user_id FROM watchers WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );
        return array_map(fn($r) => (int) $r['user_id'], $rows);
    }

    /**
     * Get watcher count for an entity.
     */
    public static function countWatchers(string $entityType, int $entityId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM watchers WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Delete all watchers for an entity (used when entity is deleted).
     */
    public static function deleteFor(string $entityType, int $entityId): void
    {
        DB::query(
            'DELETE FROM watchers WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );
    }
}
