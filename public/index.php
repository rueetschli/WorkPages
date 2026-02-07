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
require_once APP_DIR . '/models/User.php';
require_once APP_DIR . '/models/Page.php';
require_once APP_DIR . '/models/Activity.php';
require_once APP_DIR . '/services/Markdown.php';

// ── Configuration ───────────────────────────────────────────────────
$configFile = CONFIG_DIR . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo 'Missing config/config.php. Copy config.php.example and adjust values.';
    exit;
}

$config = require $configFile;

// Show errors in browser only in development
$isDev = ($config['APP_ENV'] ?? 'production') === 'development';
ini_set('display_errors', $isDev ? '1' : '0');

// ── Logger ──────────────────────────────────────────────────────────
Logger::init($config['LOG_FILE'] ?? ROOT_DIR . '/storage/logs/app.log');

// ── Session ─────────────────────────────────────────────────────────
Security::initSession();

// ── Database (lazy) ─────────────────────────────────────────────────
// Store config so DB::connect() can be called on first use by controllers.
DB::setConfig($config);

// ── Make config available to controllers/views ──────────────────────
$GLOBALS['config'] = $config;

// ── Routing ─────────────────────────────────────────────────────────
$route = $_GET['r'] ?? 'home';

// Routes that do NOT require authentication
$publicRoutes = ['login', 'setup'];

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

try {
    $controller = new $controllerClass();
    $controller->$action();
} catch (Throwable $e) {
    Logger::error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    http_response_code(500);
    if ($isDev) {
        echo '<h1>Error</h1><pre>' . Security::esc($e->getMessage()) . '</pre>';
    } else {
        require APP_DIR . '/views/500.php';
    }
}
