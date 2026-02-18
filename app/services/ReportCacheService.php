<?php
/**
 * ReportCacheService - DB-based caching for computed report data (AP18).
 *
 * Uses the report_cache table with TTL-based expiry.
 * No external dependencies (Redis, Memcached).
 */
class ReportCacheService
{
    /** Default TTL in seconds (5 minutes). */
    private const DEFAULT_TTL = 300;

    /**
     * Get cached data for a key, or null if expired/missing.
     *
     * @param string $key Cache key
     * @return array|null Decoded payload or null
     */
    public static function get(string $key): ?array
    {
        try {
            $row = DB::fetch(
                'SELECT payload_json FROM report_cache WHERE cache_key = ? AND expires_at > NOW()',
                [$key]
            );
            if ($row) {
                $decoded = json_decode($row['payload_json'], true);
                return is_array($decoded) ? $decoded : null;
            }
        } catch (Throwable $e) {
            Logger::error('ReportCacheService::get failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Store data in cache.
     *
     * @param string $key     Cache key
     * @param array  $data    Data to cache
     * @param int    $ttl     TTL in seconds
     */
    public static function set(string $key, array $data, int $ttl = self::DEFAULT_TTL): void
    {
        try {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            DB::query(
                'INSERT INTO report_cache (cache_key, payload_json, generated_at, expires_at)
                 VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json),
                                         generated_at = NOW(),
                                         expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)',
                [$key, $payload, $ttl, $ttl]
            );
        } catch (Throwable $e) {
            Logger::error('ReportCacheService::set failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Build a cache key from report type and filter parameters.
     */
    public static function buildKey(string $reportType, array $filters): string
    {
        ksort($filters);
        return 'report:' . $reportType . ':' . md5(json_encode($filters));
    }

    /**
     * Purge expired entries. Called opportunistically.
     */
    public static function cleanup(): void
    {
        try {
            DB::query('DELETE FROM report_cache WHERE expires_at < NOW()');
        } catch (Throwable $e) {
            // Silently ignore
        }
    }
}
