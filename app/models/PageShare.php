<?php
/**
 * PageShare model - database operations for the page_shares table (AP9).
 *
 * Manages token-based read-only sharing links for individual pages.
 */
class PageShare
{
    /**
     * Generate a cryptographically secure token.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create a new share link for a page. Returns the new share ID.
     */
    public static function create(int $pageId, int $createdBy, ?string $expiresAt = null): int
    {
        $token = self::generateToken();

        DB::query(
            'INSERT INTO page_shares (page_id, token, permission, created_by, created_at, expires_at)
             VALUES (?, ?, ?, ?, NOW(), ?)',
            [$pageId, $token, 'view', $createdBy, $expiresAt]
        );

        return (int) DB::lastInsertId();
    }

    /**
     * Find a share by its token. Returns null if not found.
     */
    public static function findByToken(string $token): ?array
    {
        return DB::fetch('SELECT * FROM page_shares WHERE token = ?', [$token]);
    }

    /**
     * Find the active (non-revoked, non-expired) share for a page.
     * Returns the most recently created active share, or null.
     */
    public static function findActiveForPage(int $pageId): ?array
    {
        return DB::fetch(
            'SELECT * FROM page_shares
             WHERE page_id = ? AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created_at DESC
             LIMIT 1',
            [$pageId]
        );
    }

    /**
     * Check whether a token is valid (exists, not revoked, not expired).
     */
    public static function isTokenValid(string $token): bool
    {
        $share = self::findByToken($token);
        if (!$share) {
            return false;
        }
        if ($share['revoked_at'] !== null) {
            return false;
        }
        if ($share['expires_at'] !== null && strtotime($share['expires_at']) <= time()) {
            return false;
        }
        return true;
    }

    /**
     * Revoke a share by its ID.
     */
    public static function revoke(int $id): void
    {
        DB::query('UPDATE page_shares SET revoked_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Revoke all active shares for a page.
     */
    public static function revokeAllForPage(int $pageId): void
    {
        DB::query(
            'UPDATE page_shares SET revoked_at = NOW() WHERE page_id = ? AND revoked_at IS NULL',
            [$pageId]
        );
    }

    /**
     * Find a share by ID.
     */
    public static function findById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM page_shares WHERE id = ?', [$id]);
    }
}
