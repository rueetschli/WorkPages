<?php
/**
 * Main application shell: header, sidebar navigation, content area.
 *
 * Variables expected:
 *   $pageTitle   - string, used in <title> and header
 *   $contentView - string, path to the view file rendered inside the main area
 */
$appName      = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
$baseUrl      = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$currentRoute = $_GET['r'] ?? 'home';
$userName     = Security::esc($_SESSION['user_name'] ?? '');
$userRole     = Security::esc($_SESSION['user_role'] ?? '');

// Flash messages
$flashSuccess = $_SESSION['_flash_success'] ?? null;
$flashError   = $_SESSION['_flash_error'] ?? null;
$flashInfo    = $_SESSION['_flash_info'] ?? null;
unset($_SESSION['_flash_success'], $_SESSION['_flash_error'], $_SESSION['_flash_info']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::esc($pageTitle) ?> - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
</head>
<body>

<!-- ── Header ────────────────────────────────────────────────────── -->
<header class="app-header">
    <div class="header-left">
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="app-logo"><?= Security::esc($appName) ?></a>
    </div>
    <div class="header-center">
        <form class="search-form" action="<?= Security::esc($baseUrl) ?>/" method="get">
            <input type="hidden" name="r" value="search">
            <input type="text" name="q" placeholder="Seiten und Aufgaben suchen..." class="search-input" aria-label="Suche" value="<?= Security::esc($_GET['q'] ?? '') ?>">
        </form>
    </div>
    <div class="header-right">
        <span class="user-badge"><?= $userName ?></span>
        <span class="user-role-label"><?= $userRole ?></span>
        <a href="<?= Security::esc($baseUrl) ?>/?r=logout" class="logout-link">Abmelden</a>
    </div>
</header>

<!-- ── Body: sidebar + main ──────────────────────────────────────── -->
<div class="app-body">

    <!-- Sidebar navigation -->
    <nav class="sidebar" aria-label="Hauptnavigation">
        <ul class="nav-list">
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=home"
                   class="nav-link <?= $currentRoute === 'home' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9750;</span> Home
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=pages"
                   class="nav-link <?= in_array($currentRoute, ['pages', 'page_view', 'page_create', 'page_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon">&#9783;</span> Pages
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=tasks"
                   class="nav-link <?= in_array($currentRoute, ['tasks', 'task_view', 'task_create', 'task_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon">&#9745;</span> Tasks
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=board"
                   class="nav-link <?= $currentRoute === 'board' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9638;</span> Board
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=search"
                   class="nav-link <?= $currentRoute === 'search' ? 'active' : '' ?>">
                    <span class="nav-icon">&#8981;</span> Suche
                </a>
            </li>
            <?php if (Authz::can(Authz::ADMIN_USERS_MANAGE)): ?>
            <li class="nav-separator"></li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users"
                   class="nav-link <?= in_array($currentRoute, ['admin_users', 'admin_user_create', 'admin_user_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon">&#9881;</span> Benutzer
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_migrate"
                   class="nav-link <?= $currentRoute === 'admin_migrate' ? 'active' : '' ?>">
                    <span class="nav-icon">&#8634;</span> Migrationen
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_system"
                   class="nav-link <?= $currentRoute === 'admin_system' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9432;</span> System
                </a>
            </li>
            <?php endif; ?>
            <?php if (Authz::can(Authz::TASK_CREATE)): ?>
            <li class="nav-separator"></li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=export_tasks_csv"
                   class="nav-link" title="Tasks als CSV exportieren">
                    <span class="nav-icon">&#8615;</span> Export Tasks
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php
            $__pageTree = Page::getTree();
            if (!empty($__pageTree)):
        ?>
        <div class="sidebar-pages">
            <span class="sidebar-label">Seiten</span>
            <?php
            $__currentSlug = $_GET['slug'] ?? '';
            function renderPageTree(array $nodes, string $baseUrl, string $currentSlug, int $depth = 0): void {
                echo '<ul class="page-tree' . ($depth === 0 ? ' page-tree-root' : '') . '">';
                foreach ($nodes as $node) {
                    $isActive = ($currentSlug === $node['slug']);
                    echo '<li class="page-tree-item">';
                    echo '<a href="' . Security::esc($baseUrl) . '/?r=page_view&amp;slug=' . Security::esc($node['slug']) . '"'
                       . ' class="page-tree-link' . ($isActive ? ' page-tree-active' : '') . '"'
                       . ' style="padding-left: ' . (12 + $depth * 16) . 'px">'
                       . Security::esc($node['title'])
                       . '</a>';
                    if (!empty($node['children'])) {
                        renderPageTree($node['children'], $baseUrl, $currentSlug, $depth + 1);
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            renderPageTree($__pageTree, $baseUrl, $__currentSlug);
            ?>
        </div>
        <?php endif; ?>

        <div class="sidebar-footer">
            <span class="sidebar-label">Workspace</span>
            <span class="workspace-name"><?= Security::esc($GLOBALS['config']['APP_NAME'] ?? 'WorkPages') ?></span>
        </div>
    </nav>

    <!-- Main content area -->
    <main class="main-content <?= $currentRoute === 'board' ? 'main-content-wide' : '' ?>">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= Security::esc($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= Security::esc($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashInfo): ?>
            <div class="alert alert-info"><?= Security::esc($flashInfo) ?></div>
        <?php endif; ?>

        <?php if (isset($contentView) && file_exists($contentView)): ?>
            <?php require $contentView; ?>
        <?php else: ?>
            <p>View not found.</p>
        <?php endif; ?>
    </main>

</div>

</body>
</html>
