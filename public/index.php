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

// ── Database (lazy) ─────────────────────────────────────────────────
DB::setConfig($config);

// ── Make config available to controllers/views ──────────────────────
$GLOBALS['config'] = $config;

// ── Routing ─────────────────────────────────────────────────────────

// Routes that do NOT require authentication
$publicRoutes = ['login', 'setup', 'share'];

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
    'board'              => ['controller' => 'BoardController', 'action' => 'index'],
    'board_move'         => ['controller' => 'BoardController', 'action' => 'move'],
    'board_reorder'      => ['controller' => 'BoardController', 'action' => 'reorder'],
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
