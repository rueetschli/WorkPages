<?php
/**
 * ApiResponse - JSON response helper for REST API v1 (AP19).
 *
 * All API responses are JSON with consistent format.
 */
class ApiResponse
{
    /**
     * Send a successful JSON response.
     *
     * @param mixed $data    Response data
     * @param int   $status  HTTP status code
     */
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a paginated list response.
     *
     * @param array       $data       Array of items
     * @param string|null $nextCursor Next cursor value or null
     */
    public static function paginated(array $data, ?string $nextCursor = null): void
    {
        $response = ['data' => $data];
        $response['next_cursor'] = $nextCursor;
        self::json($response);
    }

    /**
     * Send an error response.
     *
     * @param string $code    Machine-readable error code
     * @param string $message Human-readable error message
     * @param int    $status  HTTP status code
     * @param array  $details Optional error details
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        $body = [
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        if (!empty($details)) {
            $body['error']['details'] = $details;
        }

        self::json($body, $status);
    }

    /**
     * 400 Bad Request.
     */
    public static function badRequest(string $message, array $details = []): void
    {
        self::error('bad_request', $message, 400, $details);
    }

    /**
     * 401 Unauthorized.
     */
    public static function unauthorized(string $message = 'Authentifizierung erforderlich.'): void
    {
        self::error('unauthorized', $message, 401);
    }

    /**
     * 403 Forbidden.
     */
    public static function forbidden(string $message = 'Zugriff verweigert.'): void
    {
        self::error('forbidden', $message, 403);
    }

    /**
     * 404 Not Found.
     */
    public static function notFound(string $message = 'Ressource nicht gefunden.'): void
    {
        self::error('not_found', $message, 404);
    }

    /**
     * 409 Conflict.
     */
    public static function conflict(string $message, array $details = []): void
    {
        self::error('conflict', $message, 409, $details);
    }

    /**
     * 422 Unprocessable Entity.
     */
    public static function unprocessable(string $message, array $details = []): void
    {
        self::error('validation_error', $message, 422, $details);
    }

    /**
     * 429 Too Many Requests.
     */
    public static function tooManyRequests(int $retryAfter = 60): void
    {
        header('Retry-After: ' . $retryAfter);
        self::error('rate_limit_exceeded', 'Zu viele Anfragen. Bitte warten.', 429);
    }

    /**
     * 500 Internal Server Error.
     */
    public static function serverError(string $message = 'Interner Serverfehler.'): void
    {
        self::error('internal_error', $message, 500);
    }

    /**
     * 201 Created with data.
     */
    public static function created(mixed $data): void
    {
        self::json($data, 201);
    }

    /**
     * 204 No Content (for DELETE).
     */
    public static function noContent(): void
    {
        http_response_code(204);
        header('Content-Type: application/json; charset=utf-8');
        exit;
    }
}
