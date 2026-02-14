<?php
/**
 * Mention model - database operations for the mentions table (AP14).
 *
 * Stores structured @mention references extracted from Markdown text fields.
 * Mentions are synchronised on every save: old mentions are deleted and
 * current ones are re-inserted.
 */
class Mention
{
    /**
     * Synchronise mentions for an entity.
     * Deletes all existing mentions and inserts the current set.
     *
     * @param string $entityType 'page', 'task', or 'comment'
     * @param int    $entityId   ID of the entity
     * @param int[]  $userIds    Array of mentioned user IDs
     * @param int    $createdBy  ID of the user performing the save
     */
    public static function sync(string $entityType, int $entityId, array $userIds, int $createdBy): void
    {
        // Delete existing mentions for this entity
        DB::query(
            'DELETE FROM mentions WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );

        if (empty($userIds)) {
            return;
        }

        // Deduplicate
        $userIds = array_unique(array_map('intval', $userIds));

        foreach ($userIds as $userId) {
            // Verify user exists
            $user = DB::fetch('SELECT id FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                continue;
            }

            DB::query(
                'INSERT INTO mentions (entity_type, entity_id, mentioned_user_id, created_by, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [$entityType, $entityId, $userId, $createdBy]
            );
        }
    }

    /**
     * Get all mentions for an entity.
     *
     * @return array<int, array>
     */
    public static function listFor(string $entityType, int $entityId): array
    {
        return DB::fetchAll(
            'SELECT m.*, u.name AS mentioned_name
             FROM mentions m
             LEFT JOIN users u ON u.id = m.mentioned_user_id
             WHERE m.entity_type = ? AND m.entity_id = ?
             ORDER BY m.created_at ASC',
            [$entityType, $entityId]
        );
    }

    /**
     * Get all mentions where a specific user is mentioned (for future notifications).
     *
     * @return array<int, array>
     */
    public static function listForUser(int $userId, int $limit = 50): array
    {
        return DB::fetchAll(
            'SELECT m.*, u.name AS creator_name
             FROM mentions m
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.mentioned_user_id = ?
             ORDER BY m.created_at DESC
             LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    /**
     * Delete all mentions for an entity (used when entity is deleted).
     */
    public static function deleteFor(string $entityType, int $entityId): void
    {
        DB::query(
            'DELETE FROM mentions WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );
    }
}
