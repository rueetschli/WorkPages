<?php
/**
 * Comment model - database operations for the comments table.
 */
class Comment
{
    /** Maximum length for comment body in characters. */
    const MAX_BODY_LENGTH = 10000;

    /**
     * Create a new comment.
     *
     * @return int  The new comment ID
     */
    public static function create(string $entityType, int $entityId, string $bodyMd, int $createdBy): int
    {
        DB::query(
            'INSERT INTO comments (entity_type, entity_id, body_md, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$entityType, $entityId, $bodyMd, $createdBy]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Fetch all non-deleted comments for a given entity, oldest first.
     *
     * @return array<int, array>
     */
    public static function listFor(string $entityType, int $entityId, int $limit = 100): array
    {
        return DB::fetchAll(
            'SELECT c.*, u.name AS author_name
             FROM comments c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.entity_type = ? AND c.entity_id = ? AND c.deleted_at IS NULL
             ORDER BY c.created_at ASC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }

    /**
     * Find a single comment by ID (non-deleted).
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT c.*, u.name AS author_name
             FROM comments c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = ? AND c.deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Soft-delete a comment (set deleted_at).
     */
    public static function softDelete(int $commentId, int $userId): bool
    {
        $comment = self::findById($commentId);
        if (!$comment) {
            return false;
        }

        DB::query(
            'UPDATE comments SET deleted_at = NOW() WHERE id = ?',
            [$commentId]
        );

        return true;
    }

    /**
     * Validate comment body. Returns error message or null.
     */
    public static function validateBody(string $bodyMd): ?string
    {
        $bodyMd = trim($bodyMd);

        if ($bodyMd === '') {
            return 'Kommentar darf nicht leer sein.';
        }

        if (mb_strlen($bodyMd, 'UTF-8') > self::MAX_BODY_LENGTH) {
            return 'Kommentar darf maximal ' . self::MAX_BODY_LENGTH . ' Zeichen lang sein.';
        }

        return null;
    }
}
