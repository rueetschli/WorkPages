<?php
/**
 * Work Pages - Front Controller
 *
 * All requests are routed through this file.
 * Route is determined by the GET parameter "r" (e.g. ?r=home).
 */

// Strict error reporting during development
error_reporting(E_ALL);

// ── Paths ───────────────────────────────────────────────────────────
define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', ROOT_DIR . '/app');
define('CONFIG_DIR', ROOT_DIR . '/config');

// ── Autoload services & helpers ─────────────────────────────────────
require_once APP_DIR . '/services/Logger.php';
require_once APP_DIR . '/services/DB.php';
require_once APP_DIR . '/services/Security.php';

// ── Early Logger init (before config, for installer) ────────────────
Logger::init(ROOT_DIR . '/storage/logs/app.log');

// ── Session ─────────────────────────────────────────────────────────
Security::initSession();

// ── Route detection ─────────────────────────────────────────────────
$route = $_GET['r'] ?? 'home';

// ── Installer route (must work without config.php) ──────────────────
if ($route === 'install') {
    require_once APP_DIR . '/controllers/InstallController.php';
    $controller = new InstallController();
    try {
        $controller->index();
    } catch (Throwable $e) {
        Logger::error('Installer error', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);
        http_response_code(500);
        echo '<h1>Installationsfehler</h1><p>Ein Fehler ist aufgetreten. Bitte pruefen Sie das Log unter /storage/logs/app.log</p>';
    }
    exit;
}

// ── Configuration ───────────────────────────────────────────────────
$configFile = CONFIG_DIR . '/config.php';
if (!file_exists($configFile)) {
    // No config? Redirect to installer
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
    $installUrl = $protocol . '://' . $host . $scriptDir . '/?r=install';
    header('Location: ' . $installUrl);
    exit;
}

$config = require $configFile;

// ── Debug / Display errors ──────────────────────────────────────────
$isDebug = !empty($config['DEBUG']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ── Logger re-init with config path ─────────────────────────────────
Logger::init($config['LOG_FILE'] ?? ROOT_DIR . '/storage/logs/app.log');

// ── Global Error Handler ────────────────────────────────────────────
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function (Throwable $e) use ($config, $isDebug) {
    Logger::error('Uncaught exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    // Show details only if DEBUG=true and user is admin
    $showDetails = $isDebug && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    if ($showDetails) {
        $baseUrl = rtrim($config['BASE_URL'] ?? '', '/');
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Fehler</title>';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '/assets/app.css">';
        echo '</head><body class="error-body"><div class="error-container">';
        echo '<h1 class="error-code">500</h1>';
        echo '<p class="error-message">Interner Fehler</p>';
        echo '<pre style="text-align:left;max-width:800px;margin:1rem auto;padding:1rem;background:#f8f8f8;border-radius:8px;overflow:auto;font-size:0.85rem;">';
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n";
        echo htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . "\n\n";
        echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
        echo '<a href="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '/?r=home" class="btn btn-primary">Zurueck zur Startseite</a>';
        echo '</div></body></html>';
    } else {
        require APP_DIR . '/views/500.php';
    }
    exit;
});

// ── Autoload remaining services ─────────────────────────────────────
require_once APP_DIR . '/models/User.php';
require_once APP_DIR . '/models/Page.php';
require_once APP_DIR . '/models/Activity.php';
require_once APP_DIR . '/models/Task.php';
require_once APP_DIR . '/models/PageTask.php';
require_once APP_DIR . '/models/Comment.php';
require_once APP_DIR . '/services/Markdown.php';
require_once APP_DIR . '/services/SearchService.php';
require_once APP_DIR . '/services/ActivityService.php';
require_once APP_DIR . '/services/Authz.php';
require_once APP_DIR . '/models/PageShare.php';
require_once APP_DIR . '/models/BoardColumn.php';
require_once APP_DIR . '/models/Mention.php';
require_once APP_DIR . '/services/TextCommands.php';

// AP15: Notifications, Watchers, Email
require_once APP_DIR . '/models/Notification.php';
require_once APP_DIR . '/models/Watcher.php';
require_once APP_DIR . '/models/NotificationSetting.php';
require_once APP_DIR . '/models/EmailOutbox.php';
require_once APP_DIR . '/services/EventService.php';
require_once APP_DIR . '/services/NotificationEngine.php';
require_once APP_DIR . '/services/WatcherService.php';
require_once APP_DIR . '/services/EmailService.php';
require_once APP_DIR . '/services/DigestService.php';

// AP16: Teams and team-based access control
require_once APP_DIR . '/models/Team.php';
require_once APP_DIR . '/models/TeamUser.php';
require_once APP_DIR . '/services/TeamService.php';

// AP17: File Attachments
require_once APP_DIR . '/models/Attachment.php';
require_once APP_DIR . '/services/AttachmentService.php';

// AP18: Reporting and Flow Metrics
require_once APP_DIR . '/services/TaskFlowService.php';
require_once APP_DIR . '/services/ReportingService.php';
require_once APP_DIR . '/services/ReportCacheService.php';

// AP20: System Settings, Branding, Theme
require_once APP_DIR . '/services/SystemSettingsService.php';
require_once APP_DIR . '/services/ThemeService.php';

// AP21: Multi-Board Support
require_once APP_DIR . '/models/Board.php';
require_once APP_DIR . '/services/BoardService.php';

// AP22: Home Dashboard
require_once APP_DIR . '/services/HomeDashboardService.php';

// AP25: Structure View
require_once APP_DIR . '/services/TaskStructureService.php';

// AP26: Sprints and Sprint Reports
require_once APP_DIR . '/models/Sprint.php';
require_once APP_DIR . '/services/SprintService.php';

// AP27: Saved Views
require_once APP_DIR . '/models/UserView.php';

// AP24: Internationalization (i18n)
require_once APP_DIR . '/services/I18nService.php';

/**
 * Global translation shortcut.
 * Usage in views: <?= t('actions.save') ?>
 */
function t(string $key, array $params = [], ?string $lang = null): string
{
    return I18nService::t($key, $params, $lang);
}

// AP19: API and Integrations
require_once APP_DIR . '/services/ApiResponse.php';
require_once APP_DIR . '/services/ApiAuthService.php';
require_once APP_DIR . '/services/ApiScopeService.php';
require_once APP_DIR . '/services/RateLimitService.php';
require_once APP_DIR . '/services/IdempotencyService.php';
require_once APP_DIR . '/services/WebhookService.php';
require_once APP_DIR . '/services/WebhookDeliveryService.php';
require_once APP_DIR . '/services/ApiRouter.php';

// ── Database (lazy) ─────────────────────────────────────────────────
DB::setConfig($config);

// ── Make config available to controllers/views ──────────────────────
$GLOBALS['config'] = $config;

// ── AP19: REST API v1 routing ────────────────────────────────────────
// Intercept /api/v1/* requests before normal UI routing.
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
$pathInfo = parse_url($requestUri, PHP_URL_PATH);
// Strip script directory prefix to get the relative path
if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($pathInfo, $scriptDir)) {
    $pathInfo = substr($pathInfo, strlen($scriptDir));
}

if (preg_match('#^/api/v1/(.+)$#', $pathInfo, $apiMatches)) {
    try {
        ApiRouter::handle($apiMatches[1]);
    } catch (Throwable $e) {
        Logger::error('API unhandled exception', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);
        ApiResponse::serverError('Interner Serverfehler.');
    }
    exit;
}

// ── Routing ─────────────────────────────────────────────────────────

// Routes that do NOT require authentication
$publicRoutes = ['login', 'setup', 'share', 'language_switch'];

// Allowed routes mapped to controller files and methods
$routes = [
    'home'        => ['controller' => 'HomeController', 'action' => 'index'],
    'login'       => ['controller' => 'AuthController', 'action' => 'login'],
    'logout'      => ['controller' => 'AuthController', 'action' => 'logout'],
    'setup'       => ['controller' => 'AuthController', 'action' => 'setup'],
    'pages'       => ['controller' => 'PageController', 'action' => 'index'],
    'page_view'   => ['controller' => 'PageController', 'action' => 'view'],
    'page_create' => ['controller' => 'PageController', 'action' => 'create'],
    'page_edit'   => ['controller' => 'PageController', 'action' => 'edit'],
    'page_delete' => ['controller' => 'PageController', 'action' => 'delete'],
    'tasks'       => ['controller' => 'TaskController', 'action' => 'index'],
    'task_view'   => ['controller' => 'TaskController', 'action' => 'view'],
    'task_create' => ['controller' => 'TaskController', 'action' => 'create'],
    'task_store'  => ['controller' => 'TaskController', 'action' => 'create'],
    'task_edit'   => ['controller' => 'TaskController', 'action' => 'edit'],
    'task_update' => ['controller' => 'TaskController', 'action' => 'edit'],
    'task_delete'        => ['controller' => 'TaskController', 'action' => 'delete'],
    'task_update_status' => ['controller' => 'TaskController', 'action' => 'updateStatus'],
    'page_tasks_add'     => ['controller' => 'PageController', 'action' => 'tasksAdd'],
    'page_tasks_remove'  => ['controller' => 'PageController', 'action' => 'tasksRemove'],
    'page_tasks_reorder' => ['controller' => 'PageController', 'action' => 'tasksReorder'],
    'board'                    => ['controller' => 'BoardController', 'action' => 'index'],
    'board_view'               => ['controller' => 'BoardController', 'action' => 'index'],
    'board_move'               => ['controller' => 'BoardController', 'action' => 'move'],
    'board_reorder'            => ['controller' => 'BoardController', 'action' => 'reorder'],
    'board_quick_add'          => ['controller' => 'BoardController', 'action' => 'quickAdd'],
    'board_move_task_board'    => ['controller' => 'BoardController', 'action' => 'moveToBoard'],

    // AP21: Multi-Board management
    'boards'                   => ['controller' => 'BoardsController', 'action' => 'index'],
    'board_create'             => ['controller' => 'BoardsController', 'action' => 'create'],
    'board_edit'               => ['controller' => 'BoardsController', 'action' => 'edit'],
    'board_delete'             => ['controller' => 'BoardsController', 'action' => 'delete'],

    // AP13: Board column management
    'board_columns'            => ['controller' => 'BoardController', 'action' => 'columns'],
    'board_column_create'      => ['controller' => 'BoardController', 'action' => 'columnCreate'],
    'board_column_update'      => ['controller' => 'BoardController', 'action' => 'columnUpdate'],
    'board_column_delete'      => ['controller' => 'BoardController', 'action' => 'columnDelete'],
    'board_column_move_up'     => ['controller' => 'BoardController', 'action' => 'columnMoveUp'],
    'board_column_move_down'   => ['controller' => 'BoardController', 'action' => 'columnMoveDown'],
    'board_column_set_default' => ['controller' => 'BoardController', 'action' => 'columnSetDefault'],
    'search'             => ['controller' => 'SearchController', 'action' => 'index'],
    'comment_create'     => ['controller' => 'CommentController', 'action' => 'create'],
    'comment_delete'     => ['controller' => 'CommentController', 'action' => 'delete'],

    // AP9: Admin user management
    'admin_users'        => ['controller' => 'AdminController', 'action' => 'users'],
    'admin_user_create'  => ['controller' => 'AdminController', 'action' => 'userCreate'],
    'admin_user_store'   => ['controller' => 'AdminController', 'action' => 'userCreate'],
    'admin_user_edit'    => ['controller' => 'AdminController', 'action' => 'userEdit'],
    'admin_user_update'  => ['controller' => 'AdminController', 'action' => 'userEdit'],
    'admin_user_disable' => ['controller' => 'AdminController', 'action' => 'userDisable'],

    // AP9: Page sharing
    'share'              => ['controller' => 'ShareController', 'action' => 'view'],
    'share_create'       => ['controller' => 'ShareController', 'action' => 'create'],
    'share_revoke'       => ['controller' => 'ShareController', 'action' => 'revoke'],

    // AP10: Migrations, System Info, Exports
    'admin_migrate'      => ['controller' => 'AdminController', 'action' => 'migrate'],
    'admin_system'       => ['controller' => 'AdminController', 'action' => 'system'],
    'export_tasks_csv'   => ['controller' => 'ExportController', 'action' => 'tasksCsv'],
    'export_page_md'     => ['controller' => 'ExportController', 'action' => 'pageMd'],

    // AP14: Smart Text Commands - API endpoints for autocomplete
    'api_users'          => ['controller' => 'ApiController', 'action' => 'users'],
    'api_tags'           => ['controller' => 'ApiController', 'action' => 'tags'],

    // AP15: Notifications
    'notifications'            => ['controller' => 'NotificationsController', 'action' => 'index'],
    'notification_read'        => ['controller' => 'NotificationsController', 'action' => 'markRead'],
    'notifications_read_all'   => ['controller' => 'NotificationsController', 'action' => 'markAllRead'],
    'api_notifications_unread' => ['controller' => 'ApiNotificationsController', 'action' => 'unreadCount'],
    'api_notifications_latest' => ['controller' => 'ApiNotificationsController', 'action' => 'latest'],

    // AP15: Watchers
    'watch_toggle'             => ['controller' => 'WatchController', 'action' => 'toggle'],

    // AP15: Notification Settings
    'settings_notifications'   => ['controller' => 'NotificationSettingsController', 'action' => 'index'],

    // AP15: Admin Email Queue
    'admin_email_queue'        => ['controller' => 'AdminEmailController', 'action' => 'queue'],
    'admin_email_send'         => ['controller' => 'AdminEmailController', 'action' => 'send'],
    'admin_email_retry'        => ['controller' => 'AdminEmailController', 'action' => 'retry'],
    'admin_email_digest_daily' => ['controller' => 'AdminEmailController', 'action' => 'digestDaily'],
    'admin_email_digest_weekly'=> ['controller' => 'AdminEmailController', 'action' => 'digestWeekly'],
    'admin_email_test'         => ['controller' => 'AdminEmailController', 'action' => 'testEmail'],

    // AP16: Teams
    'admin_teams'              => ['controller' => 'TeamAdminController', 'action' => 'index'],
    'admin_team_create'        => ['controller' => 'TeamAdminController', 'action' => 'create'],
    'admin_team_edit'          => ['controller' => 'TeamAdminController', 'action' => 'edit'],
    'admin_team_delete'        => ['controller' => 'TeamAdminController', 'action' => 'delete'],
    'team_switch'              => ['controller' => 'TeamAdminController', 'action' => 'switchTeam'],

    // AP17: File Attachments
    'attachment_upload'        => ['controller' => 'AttachmentController', 'action' => 'upload'],
    'attachment_download'      => ['controller' => 'AttachmentController', 'action' => 'download'],
    'attachment_delete'        => ['controller' => 'AttachmentController', 'action' => 'delete'],

    // AP18: Reports and Flow Metrics
    'reports_overview'         => ['controller' => 'ReportsController', 'action' => 'overview'],
    'reports_flow'             => ['controller' => 'ReportsController', 'action' => 'flow'],
    'reports_aging'            => ['controller' => 'ReportsController', 'action' => 'aging'],
    'reports_export_csv'       => ['controller' => 'ReportsController', 'action' => 'exportCsv'],

    // AP19: API Key Management (UI)
    'settings_api_keys'        => ['controller' => 'ApiKeysController', 'action' => 'index'],
    'settings_api_key_create'  => ['controller' => 'ApiKeysController', 'action' => 'create'],
    'settings_api_key_revoke'  => ['controller' => 'ApiKeysController', 'action' => 'revoke'],

    // AP19: Webhooks Admin (UI)
    'admin_webhooks'           => ['controller' => 'WebhooksAdminController', 'action' => 'index'],
    'admin_webhook_create'     => ['controller' => 'WebhooksAdminController', 'action' => 'create'],
    'admin_webhook_edit'       => ['controller' => 'WebhooksAdminController', 'action' => 'edit'],
    'admin_webhook_delete'     => ['controller' => 'WebhooksAdminController', 'action' => 'delete'],
    'admin_webhook_regen_secret' => ['controller' => 'WebhooksAdminController', 'action' => 'regenerateSecret'],

    // AP19: Webhook Queue Admin (UI)
    'admin_webhook_queue'      => ['controller' => 'WebhookQueueAdminController', 'action' => 'index'],
    'admin_webhook_queue_send' => ['controller' => 'WebhookQueueAdminController', 'action' => 'send'],
    'admin_webhook_queue_retry'=> ['controller' => 'WebhookQueueAdminController', 'action' => 'retry'],

    // AP20: System Settings and Branding
    'admin_settings'           => ['controller' => 'AdminSettingsController', 'action' => 'index'],

    // AP24: i18n - Language switching and admin
    'language_switch'          => ['controller' => 'LanguageController', 'action' => 'switchLang'],
    'admin_languages'          => ['controller' => 'AdminLanguagesController', 'action' => 'index'],

    // AP25: Structure View
    'structure'                => ['controller' => 'StructureController', 'action' => 'index'],
    'structure_set_parent'     => ['controller' => 'StructureController', 'action' => 'setParent'],
    'structure_set_type'       => ['controller' => 'StructureController', 'action' => 'setType'],
    'structure_move_up'        => ['controller' => 'StructureController', 'action' => 'moveUp'],
    'structure_move_down'      => ['controller' => 'StructureController', 'action' => 'moveDown'],
    'structure_bulk_action'    => ['controller' => 'StructureController', 'action' => 'bulkAction'],

    // AP26: Sprints, Burndown, Velocity
    'sprints'                  => ['controller' => 'SprintController', 'action' => 'index'],
    'sprint_create'            => ['controller' => 'SprintController', 'action' => 'create'],
    'sprint_activate'          => ['controller' => 'SprintController', 'action' => 'activate'],
    'sprint_close'             => ['controller' => 'SprintController', 'action' => 'close'],
    'sprint_delete'            => ['controller' => 'SprintController', 'action' => 'delete'],
    'sprint_assign_task'       => ['controller' => 'SprintController', 'action' => 'assignTask'],
    'sprint_unassign_task'     => ['controller' => 'SprintController', 'action' => 'unassignTask'],
    'sprint_burndown'          => ['controller' => 'SprintController', 'action' => 'burndown'],
    'sprint_velocity'          => ['controller' => 'SprintController', 'action' => 'velocity'],

    // AP27: Saved Views
    'view_save'                => ['controller' => 'UserViewController', 'action' => 'save'],
    'view_update'              => ['controller' => 'UserViewController', 'action' => 'update'],
    'view_delete'              => ['controller' => 'UserViewController', 'action' => 'delete'],
    'view_set_default'         => ['controller' => 'UserViewController', 'action' => 'setDefault'],

    // AP28: System Health & Diagnostics
    'admin_health'              => ['controller' => 'AdminHealthController', 'action' => 'index'],
    'admin_health_mail_send'    => ['controller' => 'AdminHealthController', 'action' => 'mailSend'],
    'admin_health_mail_test'    => ['controller' => 'AdminHealthController', 'action' => 'mailTest'],
    'admin_health_webhook_send' => ['controller' => 'AdminHealthController', 'action' => 'webhookSend'],
];

if (!isset($routes[$route])) {
    http_response_code(404);
    require APP_DIR . '/views/404.php';
    exit;
}

// Enforce authentication on protected routes
if (!in_array($route, $publicRoutes, true)) {
    Security::requireLogin();
}

$entry = $routes[$route];
$controllerFile = APP_DIR . '/controllers/' . $entry['controller'] . '.php';

if (!file_exists($controllerFile)) {
    Logger::error('Controller file not found', ['file' => $controllerFile]);
    http_response_code(500);
    require APP_DIR . '/views/500.php';
    exit;
}

require_once $controllerFile;

$controllerClass = $entry['controller'];
$action = $entry['action'];

$controller = new $controllerClass();
$controller->$action();
