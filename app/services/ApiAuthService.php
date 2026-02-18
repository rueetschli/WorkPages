<?php
/**
 * ApiAuthService - API Key authentication for REST API v1 (AP19).
 *
 * Handles:
 *   - API Key creation (hash + prefix)
 *   - Bearer token authentication
 *   - Key listing, revocation
 *   - last_used_at tracking
 */
class ApiAuthService
{
    /** Prefix for all API keys: wp_ + 8 random hex chars. */
    private const KEY_PREFIX_LENGTH = 8;

    /** Full key format: wp_{prefix}_{32 random hex chars} = 44 chars total. */
    private const KEY_RANDOM_LENGTH = 32;

    /**
     * Create a new API key for a user.
     * Returns the full plaintext key (shown only once) and the DB record ID.
     *
     * @return array{id: int, key: string, key_prefix: string}
     */
    public static function createKey(int $userId, string $name, string $scopes): array
    {
        $prefix = bin2hex(random_bytes(self::KEY_PREFIX_LENGTH / 2));
        $random = bin2hex(random_bytes(self::KEY_RANDOM_LENGTH / 2));
        $plaintextKey = 'wp_' . $prefix . '_' . $random;
        $keyHash = hash('sha256', $plaintextKey);

        DB::query(
            'INSERT INTO api_keys (user_id, name, key_prefix, key_hash, scopes, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, $name, $prefix, $keyHash, $scopes]
        );

        $id = (int) DB::lastInsertId();

        return [
            'id'         => $id,
            'key'        => $plaintextKey,
            'key_prefix' => $prefix,
        ];
    }

    /**
     * Authenticate a request using the Authorization: Bearer header.
     * Returns the API key row (with user data) or null if invalid.
     *
     * @return array|null  API key row with user_id, scopes, key_prefix, etc.
     */
    public static function authenticate(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($header === '') {
            // Fallback: some servers strip Authorization header
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);

        if ($token === '' || strlen($token) < 10) {
            return null;
        }

        $keyHash = hash('sha256', $token);

        $apiKey = DB::fetch(
            'SELECT ak.*, u.name AS user_name, u.role AS user_role, u.is_active AS user_is_active
             FROM api_keys ak
             INNER JOIN users u ON u.id = ak.user_id
             WHERE ak.key_hash = ? AND ak.revoked_at IS NULL
             LIMIT 1',
            [$keyHash]
        );

        if (!$apiKey) {
            return null;
        }

        // Check user is active
        if ((int) ($apiKey['user_is_active'] ?? 1) === 0) {
            return null;
        }

        // Update last_used_at (fire and forget)
        try {
            DB::query(
                'UPDATE api_keys SET last_used_at = NOW() WHERE id = ?',
                [(int) $apiKey['id']]
            );
        } catch (\Throwable $e) {
            // Non-critical
        }

        return $apiKey;
    }

    /**
     * List all API keys for a user (without hash).
     */
    public static function listForUser(int $userId): array
    {
        return DB::fetchAll(
            'SELECT id, user_id, name, key_prefix, scopes, last_used_at, created_at, revoked_at
             FROM api_keys
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );
    }

    /**
     * Revoke an API key (set revoked_at).
     */
    public static function revoke(int $keyId, int $userId): bool
    {
        $key = DB::fetch(
            'SELECT id FROM api_keys WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [$keyId, $userId]
        );

        if (!$key) {
            return false;
        }

        DB::query(
            'UPDATE api_keys SET revoked_at = NOW() WHERE id = ?',
            [$keyId]
        );

        return true;
    }

    /**
     * Find a key by ID for a specific user.
     */
    public static function findByIdForUser(int $keyId, int $userId): ?array
    {
        return DB::fetch(
            'SELECT id, user_id, name, key_prefix, scopes, last_used_at, created_at, revoked_at
             FROM api_keys
             WHERE id = ? AND user_id = ?',
            [$keyId, $userId]
        );
    }

    /**
     * Get all available scopes with descriptions.
     */
    public static function availableScopes(): array
    {
        return [
            'tasks:read'        => 'Tasks lesen',
            'tasks:write'       => 'Tasks erstellen und bearbeiten',
            'pages:read'        => 'Pages lesen',
            'pages:write'       => 'Pages erstellen und bearbeiten',
            'comments:read'     => 'Kommentare lesen',
            'comments:write'    => 'Kommentare erstellen',
            'attachments:read'  => 'Anhaenge lesen und herunterladen',
            'attachments:write' => 'Anhaenge hochladen und loeschen',
            'webhooks:manage'   => 'Webhooks verwalten',
            'reports:read'      => 'Reports lesen',
        ];
    }

    /**
     * Validate a comma-separated scopes string.
     * Returns cleaned scopes string or null if invalid.
     */
    public static function validateScopes(string $scopesInput): ?string
    {
        $available = array_keys(self::availableScopes());
        $requested = array_filter(array_map('trim', explode(',', $scopesInput)));

        if (empty($requested)) {
            return null;
        }

        foreach ($requested as $scope) {
            if (!in_array($scope, $available, true)) {
                return null;
            }
        }

        return implode(',', $requested);
    }
}
