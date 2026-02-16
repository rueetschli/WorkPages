<?php
/**
 * EmailOutbox model - email queue for shared-hosting friendly delivery (AP15).
 *
 * Emails are written to the outbox and processed via admin route or cron.
 */
class EmailOutbox
{
    /**
     * Enqueue an email.
     */
    public static function enqueue(array $data): int
    {
        DB::query(
            'INSERT INTO email_outbox
                (user_id, notification_id, to_email, subject, body_html, body_text,
                 status, attempts, send_after, created_at)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', 0, ?, NOW())',
            [
                $data['user_id'],
                $data['notification_id'] ?? null,
                $data['to_email'],
                $data['subject'],
                $data['body_html'],
                $data['body_text'],
                $data['send_after'] ?? date('Y-m-d H:i:s'),
            ]
        );
        return DB::lastInsertId();
    }

    /**
     * Find by ID.
     */
    public static function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM email_outbox WHERE id = ?', [$id]);
        return $row ?: null;
    }

    /**
     * Get pending emails ready to send.
     */
    public static function getPending(int $limit = 10): array
    {
        return DB::fetchAll(
            'SELECT * FROM email_outbox
             WHERE status = \'pending\' AND send_after <= NOW()
             ORDER BY send_after ASC, id ASC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * Get failed emails.
     */
    public static function getFailed(int $limit = 50): array
    {
        return DB::fetchAll(
            'SELECT * FROM email_outbox
             WHERE status = \'failed\'
             ORDER BY created_at DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * List all outbox entries for admin view.
     */
    public static function listAll(int $limit = 100): array
    {
        return DB::fetchAll(
            'SELECT e.*, u.name AS user_name
             FROM email_outbox e
             LEFT JOIN users u ON u.id = e.user_id
             ORDER BY e.created_at DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * Mark an email as sent.
     */
    public static function markSent(int $id): void
    {
        DB::query(
            'UPDATE email_outbox SET status = \'sent\', sent_at = NOW(), attempts = attempts + 1
             WHERE id = ?',
            [$id]
        );
    }

    /**
     * Mark an email as failed.
     */
    public static function markFailed(int $id, string $error): void
    {
        DB::query(
            'UPDATE email_outbox SET status = \'failed\', last_error = ?, attempts = attempts + 1
             WHERE id = ?',
            [mb_substr($error, 0, 500, 'UTF-8'), $id]
        );
    }

    /**
     * Reset a failed email to pending for retry.
     */
    public static function retry(int $id): bool
    {
        $stmt = DB::query(
            'UPDATE email_outbox SET status = \'pending\', last_error = NULL, send_after = NOW()
             WHERE id = ? AND status = \'failed\'',
            [$id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Count pending emails.
     */
    public static function countPending(): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM email_outbox WHERE status = \'pending\'');
        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Count failed emails.
     */
    public static function countFailed(): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS cnt FROM email_outbox WHERE status = \'failed\'');
        return $row ? (int) $row['cnt'] : 0;
    }
}
