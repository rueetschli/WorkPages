<?php
/**
 * ApiRouter - Routes /api/v1/* requests to appropriate controllers (AP19).
 *
 * Handles:
 *   - Bearer token authentication
 *   - Rate limiting
 *   - CORS (optional, disabled by default)
 *   - Routing to API controllers
 *   - Audit logging
 */
class ApiRouter
{
    /** @var float Request start time for duration tracking. */
    private static float $startTime;

    /**
     * Handle an API request. Called from index.php when path starts with /api/v1/.
     *
     * @param string $path  The API path after /api/v1/ (e.g. 'tasks', 'tasks/123')
     */
    public static function handle(string $path): void
    {
        self::$startTime = microtime(true);

        // Set JSON content type header early
        header('Content-Type: application/json; charset=utf-8');

        // CORS handling
        self::handleCors();

        // OPTIONS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Authenticate
        $apiKey = ApiAuthService::authenticate();
        if ($apiKey === null) {
            ApiResponse::unauthorized('Ungueltiger oder fehlender API-Key.');
        }

        // Set API key context
        ApiScopeService::setApiKey($apiKey);

        // Populate session-like context for TeamService compatibility
        $_SESSION['user_id'] = (int) $apiKey['user_id'];
        $_SESSION['user_role'] = $apiKey['user_role'];
        $_SESSION['user_name'] = $apiKey['user_name'];

        // Rate limiting
        $keyPrefix = $apiKey['key_prefix'];
        $rateResult = RateLimitService::check($keyPrefix);
        RateLimitService::setHeaders($keyPrefix);

        if (!$rateResult['allowed']) {
            ApiResponse::tooManyRequests($rateResult['retry_after']);
        }

        // Periodic cleanup (1% chance per request)
        if (random_int(1, 100) === 1) {
            RateLimitService::cleanup();
            IdempotencyService::cleanup();
        }

        // Route the request
        $method = $_SERVER['REQUEST_METHOD'];
        self::route($path, $method);
    }

    /**
     * Route to the appropriate controller action.
     */
    private static function route(string $path, string $method): void
    {
        // Parse path segments
        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        $resource = $segments[0] ?? '';
        $id = isset($segments[1]) ? $segments[1] : null;
        $subResource = $segments[2] ?? null;

        // Load the appropriate controller
        switch ($resource) {
            case 'tasks':
                require_once APP_DIR . '/controllers/ApiV1TasksController.php';
                $controller = new ApiV1TasksController();
                self::dispatchCrud($controller, $method, $id);
                break;

            case 'pages':
                require_once APP_DIR . '/controllers/ApiV1PagesController.php';
                $controller = new ApiV1PagesController();
                if ($id !== null && $subResource === 'tasks') {
                    $controller->linkedTasks((int) $id);
                } else {
                    self::dispatchCrud($controller, $method, $id);
                }
                break;

            case 'comments':
                require_once APP_DIR . '/controllers/ApiV1CommentsController.php';
                $controller = new ApiV1CommentsController();
                self::dispatchCrud($controller, $method, $id);
                break;

            case 'attachments':
                require_once APP_DIR . '/controllers/ApiV1AttachmentsController.php';
                $controller = new ApiV1AttachmentsController();
                if ($id !== null && $subResource === 'download') {
                    $controller->download((int) $id);
                } else {
                    self::dispatchCrud($controller, $method, $id);
                }
                break;

            case 'board_columns':
                require_once APP_DIR . '/controllers/ApiV1BoardColumnsController.php';
                $controller = new ApiV1BoardColumnsController();
                if ($method === 'GET') {
                    if ($id !== null) {
                        $controller->show((int) $id);
                    } else {
                        $controller->index();
                    }
                } else {
                    ApiResponse::error('method_not_allowed', 'Methode nicht erlaubt.', 405);
                }
                break;

            case 'reports':
                require_once APP_DIR . '/controllers/ApiV1ReportsController.php';
                $controller = new ApiV1ReportsController();
                $reportType = $id; // e.g. 'flow', 'aging'
                if ($method === 'GET' && $reportType !== null) {
                    $controller->show($reportType);
                } else {
                    ApiResponse::notFound('Report-Typ nicht angegeben.');
                }
                break;

            default:
                ApiResponse::notFound('Unbekannte API-Ressource: ' . $resource);
        }

        // Log the request after response is sent
        self::logRequest();
    }

    /**
     * Dispatch CRUD operations based on HTTP method and ID.
     */
    private static function dispatchCrud(object $controller, string $method, ?string $id): void
    {
        switch ($method) {
            case 'GET':
                if ($id !== null) {
                    $controller->show((int) $id);
                } else {
                    $controller->index();
                }
                break;

            case 'POST':
                $controller->create();
                break;

            case 'PATCH':
                if ($id === null) {
                    ApiResponse::badRequest('ID ist erforderlich fuer PATCH.');
                }
                $controller->update((int) $id);
                break;

            case 'DELETE':
                if ($id === null) {
                    ApiResponse::badRequest('ID ist erforderlich fuer DELETE.');
                }
                $controller->delete((int) $id);
                break;

            default:
                ApiResponse::error('method_not_allowed', 'Methode nicht erlaubt.', 405);
        }
    }

    /**
     * Handle CORS headers (disabled by default, configurable).
     */
    private static function handleCors(): void
    {
        $allowedOrigins = $GLOBALS['config']['API_CORS_ORIGINS'] ?? null;

        if ($allowedOrigins === null) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') {
            return;
        }

        if ($allowedOrigins === '*' || in_array($origin, (array) $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Idempotency-Key');
            header('Access-Control-Max-Age: 86400');
        }
    }

    /**
     * Log the API request for audit.
     */
    private static function logRequest(): void
    {
        try {
            $duration = (int) ((microtime(true) - self::$startTime) * 1000);
            $keyPrefix = ApiScopeService::getKeyPrefix();
            $userId = ApiScopeService::getUserId();
            $statusCode = http_response_code() ?: 200;

            DB::query(
                'INSERT INTO api_audit_log (key_prefix, user_id, method, route, status_code, duration_ms, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $keyPrefix,
                    $userId,
                    $_SERVER['REQUEST_METHOD'],
                    $_SERVER['REQUEST_URI'] ?? '',
                    $statusCode,
                    $duration,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('API audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the raw JSON body of the request.
     */
    public static function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            ApiResponse::badRequest('Ungueltiges JSON im Request-Body.');
        }

        return $data;
    }

    /**
     * Get the raw body string for idempotency hashing.
     */
    public static function getRawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Parse pagination parameters from query string.
     * Returns [limit, cursor].
     */
    public static function parsePagination(int $maxLimit = 100): array
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $limit = max(1, min($limit, $maxLimit));
        $cursor = $_GET['cursor'] ?? null;

        return [$limit, $cursor];
    }
}
