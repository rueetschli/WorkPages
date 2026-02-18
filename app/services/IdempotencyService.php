<?php
/**
 * IdempotencyService - Idempotency key handling for POST endpoints (AP19).
 *
 * Ensures that retried POST requests return the same response.
 * Key lifetime: 24 hours.
 */
class IdempotencyService
{
    /** Maximum age of stored idempotency keys in seconds. */
    private const KEY_TTL = 86400; // 24 hours

    /**
     * Check for an existing idempotency response.
     * Returns stored response array or null if not found.
     *
     * @param string $keyPrefix      API key prefix
     * @param string $idempotencyKey Idempotency-Key header value
     * @param string $requestHash    Hash of the request body
     * @return array|null  ['response_code' => int, 'response_body' => string] or null
     */
    public static function find(string $keyPrefix, string $idempotencyKey, string $requestHash): ?array
    {
        $row = DB::fetch(
            'SELECT response_code, response_body, request_hash
             FROM api_idempotency
             WHERE key_prefix = ? AND idempotency_key = ?',
            [$keyPrefix, $idempotencyKey]
        );

        if (!$row) {
            return null;
        }

        // Verify request hash matches (same key, different body = conflict)
        if ($row['request_hash'] !== $requestHash) {
            ApiResponse::conflict(
                'Idempotency-Key wurde bereits mit anderem Request-Body verwendet.'
            );
        }

        return [
            'response_code' => (int) $row['response_code'],
            'response_body' => $row['response_body'],
        ];
    }

    /**
     * Store an idempotency response.
     */
    public static function store(
        string $keyPrefix,
        string $idempotencyKey,
        string $requestHash,
        int $responseCode,
        string $responseBody
    ): void {
        try {
            DB::query(
                'INSERT INTO api_idempotency (key_prefix, idempotency_key, request_hash, response_code, response_body, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE response_code = VALUES(response_code), response_body = VALUES(response_body)',
                [$keyPrefix, $idempotencyKey, $requestHash, $responseCode, $responseBody]
            );
        } catch (\Throwable $e) {
            Logger::error('IdempotencyService::store failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the Idempotency-Key from request headers. Returns null if not set.
     */
    public static function getKey(): ?string
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
        if ($key !== null) {
            $key = trim($key);
            if ($key === '' || mb_strlen($key) > 80) {
                return null;
            }
        }
        return $key;
    }

    /**
     * Compute a hash of the request body for comparison.
     */
    public static function hashRequestBody(string $body): string
    {
        return hash('sha256', $body);
    }

    /**
     * Handle idempotency for a POST request.
     * If a cached response exists, sends it and exits.
     * Otherwise returns the idempotency key for later storage.
     *
     * @return array|null  ['key' => string, 'hash' => string] or null if no idempotency header
     */
    public static function handleRequest(string $keyPrefix, string $requestBody): ?array
    {
        $idempotencyKey = self::getKey();
        if ($idempotencyKey === null) {
            return null;
        }

        $requestHash = self::hashRequestBody($requestBody);
        $cached = self::find($keyPrefix, $idempotencyKey, $requestHash);

        if ($cached !== null) {
            http_response_code($cached['response_code']);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Idempotency-Replayed: true');
            echo $cached['response_body'];
            exit;
        }

        return ['key' => $idempotencyKey, 'hash' => $requestHash];
    }

    /**
     * Clean up expired idempotency entries.
     */
    public static function cleanup(): void
    {
        try {
            DB::query(
                'DELETE FROM api_idempotency WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)',
                [self::KEY_TTL]
            );
        } catch (\Throwable $e) {
            Logger::error('IdempotencyService::cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
