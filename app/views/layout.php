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
    <meta name="base-url" content="<?= Security::esc($baseUrl) ?>">
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
    <script>
    /* Apply saved theme immediately to prevent flash */
    (function() {
        var t = localStorage.getItem('wp-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
</head>
<body>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Header -->
<header class="app-header">
    <div class="header-left">
        <button type="button" class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="app-logo"><?= Security::esc($appName) ?></a>
    </div>
    <div class="header-center">
        <form class="search-form" action="<?= Security::esc($baseUrl) ?>/" method="get">
            <input type="hidden" name="r" value="search">
            <input type="text" name="q" placeholder="Suchen..." class="search-input" aria-label="Suche" value="<?= Security::esc($_GET['q'] ?? '') ?>">
        </form>
    </div>
    <div class="header-right">
        <button type="button" class="mobile-search-btn" id="mobile-search-btn" aria-label="Suche">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Farbschema wechseln">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
        <span class="user-badge"><?= $userName ?></span>
        <span class="user-role-label"><?= $userRole ?></span>
        <a href="<?= Security::esc($baseUrl) ?>/?r=logout" class="logout-link">Abmelden</a>
    </div>
</header>

<!-- Body: sidebar + main -->
<div class="app-body">

    <!-- Sidebar navigation -->
    <nav class="sidebar" id="sidebar" aria-label="Hauptnavigation">
        <ul class="nav-list">
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=home"
                   class="nav-link <?= $currentRoute === 'home' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    Home
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=pages"
                   class="nav-link <?= in_array($currentRoute, ['pages', 'page_view', 'page_create', 'page_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    Pages
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=tasks"
                   class="nav-link <?= in_array($currentRoute, ['tasks', 'task_view', 'task_create', 'task_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
                    Tasks
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=board"
                   class="nav-link <?= $currentRoute === 'board' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg></span>
                    Board
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=search"
                   class="nav-link <?= $currentRoute === 'search' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                    Suche
                </a>
            </li>
            <?php if (Authz::can(Authz::ADMIN_USERS_MANAGE)): ?>
            <li class="nav-separator"></li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users"
                   class="nav-link <?= in_array($currentRoute, ['admin_users', 'admin_user_create', 'admin_user_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                    Benutzer
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_migrate"
                   class="nav-link <?= $currentRoute === 'admin_migrate' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></span>
                    Migrationen
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=admin_system"
                   class="nav-link <?= $currentRoute === 'admin_system' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
                    System
                </a>
            </li>
            <?php endif; ?>
            <?php if (Authz::can(Authz::TASK_CREATE)): ?>
            <li class="nav-separator"></li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=export_tasks_csv"
                   class="nav-link" title="Tasks als CSV exportieren">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
                    Export Tasks
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

<script>
(function() {
    'use strict';

    /* -- Dark Mode Toggle -- */
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', function() {
            var html = document.documentElement;
            var isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.removeItem('wp-theme');
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('wp-theme', 'dark');
            }
        });
    }

    /* -- Mobile Sidebar Toggle -- */
    var menuBtn = document.getElementById('mobile-menu-btn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function() {
            var isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                closeSidebar();
            } else {
                sidebar.classList.add('open');
                if (overlay) overlay.classList.add('active');
                document.body.classList.add('sidebar-open');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* Close sidebar when a nav link is clicked (mobile) */
    if (sidebar) {
        var sidebarLinks = sidebar.querySelectorAll('.nav-link, .page-tree-link');
        for (var j = 0; j < sidebarLinks.length; j++) {
            sidebarLinks[j].addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        }
    }

    /* -- Mobile Search Toggle -- */
    var searchBtn = document.getElementById('mobile-search-btn');
    var appHeader = document.querySelector('.app-header');
    if (searchBtn && appHeader) {
        searchBtn.addEventListener('click', function() {
            appHeader.classList.toggle('search-active');
            if (appHeader.classList.contains('search-active')) {
                var searchInput = appHeader.querySelector('.search-input');
                if (searchInput) searchInput.focus();
            }
        });
    }

    /* -- Auto-dismiss flash alerts after 6 seconds -- */
    var alerts = document.querySelectorAll('.alert-success, .alert-info');
    for (var i = 0; i < alerts.length; i++) {
        (function(el) {
            setTimeout(function() {
                el.style.transition = 'opacity 300ms ease, transform 300ms ease';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(function() { el.remove(); }, 300);
            }, 6000);
        })(alerts[i]);
    }
})();
</script>
<script src="<?= Security::esc($baseUrl) ?>/assets/mentions.js"></script>

</body>
</html>
