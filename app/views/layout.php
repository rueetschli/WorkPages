<?php
/**
 * Main application shell: header, sidebar navigation, content area.
 *
 * Variables expected:
 *   $pageTitle   - string, used in <title> and header
 *   $contentView - string, path to the view file rendered inside the main area
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$currentRoute = $_GET['r'] ?? 'home';
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
            <input type="text" name="q" placeholder="Search pages and tasks..." class="search-input" aria-label="Search">
        </form>
    </div>
    <div class="header-right">
        <span class="user-badge">Demo User</span>
    </div>
</header>

<!-- ── Body: sidebar + main ──────────────────────────────────────── -->
<div class="app-body">

    <!-- Sidebar navigation -->
    <nav class="sidebar" aria-label="Main navigation">
        <ul class="nav-list">
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=home"
                   class="nav-link <?= $currentRoute === 'home' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9750;</span> Home
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=pages"
                   class="nav-link <?= $currentRoute === 'pages' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9783;</span> Pages
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
                    <span class="nav-icon">&#8981;</span> Search
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=settings"
                   class="nav-link <?= $currentRoute === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9881;</span> Settings
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <span class="sidebar-label">Workspace</span>
            <span class="workspace-name">Demo</span>
        </div>
    </nav>

    <!-- Main content area -->
    <main class="main-content">
        <?php if (isset($contentView) && file_exists($contentView)): ?>
            <?php require $contentView; ?>
        <?php else: ?>
            <p>View not found.</p>
        <?php endif; ?>
    </main>

</div>

</body>
</html>
