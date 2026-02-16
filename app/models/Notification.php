<?php
/**
 * Notification model - database operations for the notifications table (AP15).
 *
 * In-app notifications with read/unread state, priority, and deep links.
 */
class Notification
{
    /**
     * Create a notification. Returns the new ID.
     */
    public static function create(array $data): int
    {
        DB::query(
            'INSERT INTO notifications
                (user_id, type, priority, entity_type, entity_id, actor_user_id,
                 title, body, url, is_read, is_emailed, dedupe_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, NOW())',
            [
                $data['user_id'],
                $data['type'],
                $data['priority'] ?? 3,
                $data['entity_type'],
                $data['entity_id'],
                $data['actor_user_id'],
                $data['title'],
                $data['body'] ?? null,
                $data['url'],
                $data['dedupe_key'] ?? null,
            ]
        );
        return DB::lastInsertId();
    }

    /**
     * Check if a notification with the given dedupe_key already exists for a user.
     */
    public static function existsByDedupeKey(int $userId, string $dedupeKey): bool
    {
        $row = DB::fetch(
            'SELECT id FROM notifications WHERE user_id = ? AND dedupe_key = ?',
            [$userId, $dedupeKey]
        );
        return $row !== false && $row !== null;
    }

    /**
     * Find a notification by ID.
     */
    public static function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM notifications WHERE id = ?', [$id]);
        return $row ?: null;
    }

    /**
     * List notifications for a user, optionally filtered by read status.
     *
     * @param int       $userId
     * @param bool|null $unreadOnly  null = all, true = unread only
     * @param int       $limit
     * @param int       $offset
     * @return array
     */
    public static function listForUser(int $userId, ?bool $unreadOnly = null, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT n.*, u.name AS actor_name
                FROM notifications n
                LEFT JOIN users u ON u.id = n.actor_user_id
                WHERE n.user_id = ?';
        $params = [$userId];

        if ($unreadOnly === true) {
            $sql .= ' AND n.is_read = 0';
        }

        $sql .= ' ORDER BY n.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return DB::fetchAll($sql, $params);
    }

    /**
     * Count unread notifications for a user.
     */
    public static function countUnread(int $userId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Mark a single notification as read.
     */
    public static function markRead(int $id, int $userId): bool
    {
        $stmt = DB::query(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ? AND is_read = 0',
            [$id, $userId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllRead(int $userId): int
    {
        $stmt = DB::query(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
        return $stmt->rowCount();
    }

    /**
     * Get un-emailed notifications for a user (for digest).
     *
     * @return array
     */
    public static function getUnemailed(int $userId, int $limit = 200): array
    {
        return DB::fetchAll(
            'SELECT n.*, u.name AS actor_name
             FROM notifications n
             LEFT JOIN users u ON u.id = n.actor_user_id
             WHERE n.user_id = ? AND n.is_emailed = 0
             ORDER BY n.created_at ASC
             LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    /**
     * Mark notifications as emailed.
     *
     * @param int[] $ids
     */
    public static function markEmailed(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::query(
            'UPDATE notifications SET is_emailed = 1 WHERE id IN (' . $placeholders . ')',
            array_map('intval', $ids)
        );
    }

    /**
     * Delete old read notifications (cleanup).
     */
    public static function deleteOldRead(int $daysOld = 90): int
    {
        $stmt = DB::query(
            'DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$daysOld]
        );
        return $stmt->rowCount();
    }
}
