<?php
/**
 * RateLimitService - DB-based rate limiting for API (AP19).
 *
 * Shared hosting compatible (no Redis). Uses api_rate_limits table.
 * Default: 300 requests per 5 minutes per API key.
 */
class RateLimitService
{
    /** Default requests per window. */
    private const DEFAULT_LIMIT = 300;

    /** Default window in seconds (5 minutes). */
    private const DEFAULT_WINDOW = 300;

    /**
     * Check rate limit for an API key.
     * Returns true if allowed, false if limit exceeded.
     *
     * @param string $keyPrefix  The 8-char key prefix
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public static function check(string $keyPrefix): array
    {
        $limit = (int) ($GLOBALS['config']['API_RATE_LIMIT'] ?? self::DEFAULT_LIMIT);
        $window = (int) ($GLOBALS['config']['API_RATE_WINDOW'] ?? self::DEFAULT_WINDOW);

        $windowStart = self::currentWindowStart($window);

        // Upsert: increment counter or create new entry
        try {
            DB::query(
                'INSERT INTO api_rate_limits (key_prefix, window_start, window_seconds, request_count)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE request_count = request_count + 1',
                [$keyPrefix, $windowStart, $window]
            );

            $row = DB::fetch(
                'SELECT request_count FROM api_rate_limits
                 WHERE key_prefix = ? AND window_start = ? AND window_seconds = ?',
                [$keyPrefix, $windowStart, $window]
            );

            $count = (int) ($row['request_count'] ?? 1);
            $remaining = max(0, $limit - $count);

            if ($count > $limit) {
                $windowEnd = strtotime($windowStart) + $window;
                $retryAfter = max(1, $windowEnd - time());
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
            }

            return ['allowed' => true, 'remaining' => $remaining, 'retry_after' => 0];
        } catch (\Throwable $e) {
            Logger::error('RateLimitService::check failed', ['error' => $e->getMessage()]);
            // Fail open: allow request if rate limit check fails
            return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
        }
    }

    /**
     * Set rate limit headers on the response.
     */
    public static function setHeaders(string $keyPrefix): void
    {
        $limit = (int) ($GLOBALS['config']['API_RATE_LIMIT'] ?? self::DEFAULT_LIMIT);
        $window = (int) ($GLOBALS['config']['API_RATE_WINDOW'] ?? self::DEFAULT_WINDOW);
        $windowStart = self::currentWindowStart($window);

        $row = DB::fetch(
            'SELECT request_count FROM api_rate_limits
             WHERE key_prefix = ? AND window_start = ? AND window_seconds = ?',
            [$keyPrefix, $windowStart, $window]
        );

        $count = (int) ($row['request_count'] ?? 0);
        $remaining = max(0, $limit - $count);

        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Window: ' . $window);
    }

    /**
     * Clean up old rate limit entries (call periodically).
     */
    public static function cleanup(): void
    {
        try {
            DB::query(
                'DELETE FROM api_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
        } catch (\Throwable $e) {
            Logger::error('RateLimitService::cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate the current window start time.
     */
    private static function currentWindowStart(int $windowSeconds): string
    {
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        return date('Y-m-d H:i:s', $windowStart);
    }
}
