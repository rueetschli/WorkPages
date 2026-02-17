<?php
/**
 * Attachment model - database operations for the attachments table (AP17).
 *
 * Handles file attachment metadata for pages and tasks.
 * Actual file storage is managed by AttachmentService.
 */
class Attachment
{
    /**
     * Find an attachment by ID (non-deleted).
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch(
            'SELECT a.*, u.name AS uploader_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.id = ? AND a.deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Find an attachment by ID including soft-deleted.
     */
    public static function findByIdWithDeleted(int $id): ?array
    {
        return DB::fetch(
            'SELECT a.*, u.name AS uploader_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.id = ?',
            [$id]
        );
    }

    /**
     * List all non-deleted attachments for an entity, newest first.
     *
     * @return array<int, array>
     */
    public static function listFor(string $entityType, int $entityId, int $limit = 100): array
    {
        return DB::fetchAll(
            'SELECT a.*, u.name AS uploader_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.entity_type = ? AND a.entity_id = ? AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }

    /**
     * Count non-deleted attachments for an entity.
     */
    public static function countFor(string $entityType, int $entityId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS cnt FROM attachments
             WHERE entity_type = ? AND entity_id = ? AND deleted_at IS NULL',
            [$entityType, $entityId]
        );
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Create a new attachment record.
     *
     * @return int The new attachment ID
     */
    public static function create(array $data): int
    {
        DB::query(
            'INSERT INTO attachments
                (entity_type, entity_id, team_id, original_name, stored_name,
                 stored_path, mime_type, file_ext, file_size, checksum_sha256,
                 uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['entity_type'],
                $data['entity_id'],
                $data['team_id'] ?? null,
                $data['original_name'],
                $data['stored_name'],
                $data['stored_path'],
                $data['mime_type'],
                $data['file_ext'],
                $data['file_size'],
                $data['checksum_sha256'] ?? null,
                $data['uploaded_by'],
            ]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Soft-delete an attachment (set deleted_at).
     */
    public static function softDelete(int $id): void
    {
        DB::query(
            'UPDATE attachments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Format file size to human-readable string.
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1073741824, 1) . ' GB';
    }

    /**
     * Check if a MIME type is an image type suitable for preview.
     */
    public static function isPreviewable(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
    }
}
