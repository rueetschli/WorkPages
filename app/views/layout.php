<?php
/**
 * Main application shell: header, sidebar navigation, content area.
 * AP23: Work-centered navigation, user dropdown, collapsible page tree.
 *
 * Variables expected:
 *   $pageTitle   - string, used in <title> and header
 *   $contentView - string, path to the view file rendered inside the main area
 */
$baseUrl      = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$currentRoute = $_GET['r'] ?? 'home';
$userName     = Security::esc($_SESSION['user_name'] ?? '');
$userRole     = Security::esc($_SESSION['user_role'] ?? '');

// AP20: System settings for branding
$__sysCompanyName = '';
$__sysLogoUrl     = '';
$__sysMaintenance = false;
$__sysMaintMsg    = '';
$__sysMaintLevel  = 'info';
$__themeCssVars   = '';
try {
    $__sysCompanyName = SystemSettingsService::companyName();
    $__sysLogoUrl     = SystemSettingsService::logoUrl();
    $__sysMaintenance = SystemSettingsService::isMaintenanceActive();
    $__sysMaintMsg    = SystemSettingsService::value('maintenance_message', '');
    $__sysMaintLevel  = SystemSettingsService::value('maintenance_level', 'info');
    $__themeCssVars   = ThemeService::renderCssVariables();
} catch (Throwable $e) {
    // Table may not exist yet
    $__sysCompanyName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
}
$appName = $__sysCompanyName;

// AP20: Version info
$__versionInfo = ['version' => '', 'repo' => 'https://github.com/rueetschli/WorkPages', 'license' => 'MIT'];
$__versionFile = CONFIG_DIR . '/version.php';
if (file_exists($__versionFile)) {
    $__versionInfo = require $__versionFile;
}

// AP16: Load teams for switcher
$__activeTeamId = TeamService::getActiveTeamId();
$__switcherTeams = [];
try {
    if (!empty($_SESSION['user_id'])) {
        $__switcherTeams = TeamService::getTeamsForSwitcher(
            (int) $_SESSION['user_id'],
            $_SESSION['user_role'] ?? 'viewer'
        );
    }
} catch (Throwable $e) {
    // teams table may not exist yet
}

// AP15: Notification bell - unread count
$__notifCount = 0;
try {
    if (!empty($_SESSION['user_id'])) {
        $__notifCount = Notification::countUnread((int) $_SESSION['user_id']);
    }
} catch (Throwable $e) {
    // Table may not exist yet before migration
}

// Flash messages
$flashSuccess = $_SESSION['_flash_success'] ?? null;
$flashError   = $_SESSION['_flash_error'] ?? null;
$flashInfo    = $_SESSION['_flash_info'] ?? null;
unset($_SESSION['_flash_success'], $_SESSION['_flash_error'], $_SESSION['_flash_info']);
?>
<!DOCTYPE html>
<html lang="<?= Security::esc(I18nService::getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::esc($pageTitle) ?> - <?= Security::esc($appName) ?></title>
    <meta name="base-url" content="<?= Security::esc($baseUrl) ?>">
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
    <?= $__themeCssVars ?>
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
        <button type="button" class="mobile-menu-btn" id="mobile-menu-btn" aria-label="<?= Security::esc(t('header.menu')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=home" class="app-logo"><?php if ($__sysLogoUrl !== ''): ?><img src="<?= Security::esc($__sysLogoUrl) ?>" alt="<?= Security::esc($appName) ?>" class="app-logo-img"><?php else: ?><?= Security::esc($appName) ?><?php endif; ?></a>
    </div>
    <div class="header-center">
        <form class="search-form" action="<?= Security::esc($baseUrl) ?>/" method="get">
            <input type="hidden" name="r" value="search">
            <input type="text" name="q" placeholder="<?= Security::esc(t('placeholders.search')) ?>" class="search-input" aria-label="<?= Security::esc(t('header.search')) ?>" value="<?= Security::esc($_GET['q'] ?? '') ?>">
        </form>
    </div>
    <div class="header-right">
        <?php if (!empty($__switcherTeams)): ?>
        <div class="team-switcher-wrap">
            <button type="button" class="team-switcher-btn" id="team-switcher-btn" aria-label="<?= Security::esc(t('teams.switch_team')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                <span class="team-switcher-label"><?php
                    if ($__activeTeamId === null) {
                        echo Security::esc(t('teams.all_teams'));
                    } else {
                        $__activeTeamName = 'Team';
                        foreach ($__switcherTeams as $__st) {
                            if ((int) $__st['id'] === $__activeTeamId) {
                                $__activeTeamName = $__st['name'];
                                break;
                            }
                        }
                        echo Security::esc($__activeTeamName);
                    }
                ?></span>
            </button>
            <div class="team-switcher-dropdown" id="team-switcher-dropdown">
                <div class="team-switcher-dropdown-header"><?= Security::esc(t('teams.switch_team')) ?></div>
                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=team_switch">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="return_to" value="<?= Security::esc('?' . ($_SERVER['QUERY_STRING'] ?? '')) ?>">
                    <button type="submit" name="team_id" value="all" class="team-switcher-item<?= $__activeTeamId === null ? ' team-switcher-active' : '' ?>"><?= Security::esc(t('teams.all_teams')) ?></button>
                    <?php foreach ($__switcherTeams as $__st): ?>
                    <button type="submit" name="team_id" value="<?= (int) $__st['id'] ?>" class="team-switcher-item<?= $__activeTeamId === (int) $__st['id'] ? ' team-switcher-active' : '' ?>"><?= Security::esc($__st['name']) ?></button>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <button type="button" class="mobile-search-btn" id="mobile-search-btn" aria-label="<?= Security::esc(t('header.search')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <div class="notif-bell-wrap">
            <button type="button" class="notif-bell-btn" id="notif-bell-btn" aria-label="<?= Security::esc(t('nav.notifications')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span class="notif-badge" id="notif-badge" <?= $__notifCount === 0 ? 'style="display:none"' : '' ?>><?= $__notifCount > 99 ? '99+' : (int) $__notifCount ?></span>
            </button>
            <div class="notif-dropdown" id="notif-dropdown">
                <div class="notif-dropdown-header">
                    <span><?= Security::esc(t('notifications.title')) ?></span>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=notifications"><?= Security::esc(t('notifications.show_all')) ?></a>
                </div>
                <div id="notif-dropdown-list">
                    <div class="notif-dropdown-empty"><?= Security::esc(t('messages.loading')) ?></div>
                </div>
                <div class="notif-dropdown-footer">
                    <a href="<?= Security::esc($baseUrl) ?>/?r=notifications"><?= Security::esc(t('notifications.all_notifications')) ?></a>
                </div>
            </div>
        </div>
        <button type="button" class="theme-toggle" id="theme-toggle" aria-label="<?= Security::esc(t('header.theme_toggle')) ?>">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
        <!-- AP23: User dropdown menu -->
        <div class="user-menu-wrap">
            <button type="button" class="user-menu-btn" id="user-menu-btn" aria-label="<?= Security::esc(t('header.user_menu')) ?>">
                <span class="user-menu-avatar"><?= mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8') ?></span>
                <span class="user-menu-name"><?= $userName ?></span>
                <svg class="user-menu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="user-menu-dropdown" id="user-menu-dropdown">
                <div class="user-menu-dropdown-header">
                    <span class="user-menu-dropdown-name"><?= $userName ?></span>
                    <span class="user-menu-dropdown-role"><?= $userRole ?></span>
                </div>
                <a href="<?= Security::esc($baseUrl) ?>/?r=settings_notifications" class="user-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    <?= Security::esc(t('nav.settings')) ?>
                </a>
                <a href="<?= Security::esc($baseUrl) ?>/?r=settings_api_keys" class="user-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.78 7.78 5.5 5.5 0 017.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    <?= Security::esc(t('settings.api_keys')) ?>
                </a>
                <?php if (Authz::can(Authz::TASK_CREATE)): ?>
                <a href="<?= Security::esc($baseUrl) ?>/?r=export_tasks_csv" class="user-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= Security::esc(t('actions.export')) ?>
                </a>
                <?php endif; ?>
                <a href="<?= Security::esc($baseUrl) ?>/?r=notifications" class="user-menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                    <?= Security::esc(t('nav.notifications')) ?>
                    <?php if ($__notifCount > 0): ?>
                        <span class="notif-tab-badge" style="margin-left:auto"><?= $__notifCount > 99 ? '99+' : (int) $__notifCount ?></span>
                    <?php endif; ?>
                </a>
                <!-- AP24: Language switcher in user menu -->
                <?php
                    $__availableLangs = I18nService::listAvailableLanguages();
                    $__currentLang = I18nService::getCurrentLanguage();
                    if (count($__availableLangs) > 1):
                ?>
                <div class="user-menu-separator"></div>
                <div class="user-menu-lang">
                    <span class="user-menu-lang-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                        <?= Security::esc(t('labels.language')) ?>
                    </span>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=language_switch" class="user-menu-lang-form">
                        <?= Security::csrfField() ?>
                        <select name="language" class="form-input form-input-sm user-menu-lang-select" onchange="this.form.submit()">
                            <?php foreach ($__availableLangs as $__al): ?>
                                <option value="<?= Security::esc($__al['code']) ?>"
                                    <?= $__al['code'] === $__currentLang ? 'selected' : '' ?>>
                                    <?= Security::esc(I18nService::languageName($__al['code'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>
                <div class="user-menu-separator"></div>
                <a href="<?= Security::esc($baseUrl) ?>/?r=logout" class="user-menu-item user-menu-item-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <?= Security::esc(t('actions.logout')) ?>
                </a>
            </div>
        </div>
    </div>
</header>

<?php if ($__sysMaintenance && $__sysMaintMsg !== ''): ?>
<div class="maintenance-banner maintenance-<?= Security::esc($__sysMaintLevel) ?>">
    <?= Security::esc($__sysMaintMsg) ?>
</div>
<?php endif; ?>

<!-- Body: sidebar + main -->
<div class="app-body">

    <!-- AP23: Work-centered sidebar navigation -->
    <nav class="sidebar" id="sidebar" aria-label="Hauptnavigation">
        <ul class="nav-list">
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=home&amp;no_default=1"
                   class="nav-link <?= $currentRoute === 'home' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <?= Security::esc(t('nav.home')) ?>
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=boards"
                   class="nav-link <?= in_array($currentRoute, ['boards', 'board', 'board_view'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg></span>
                    <?= Security::esc(t('nav.boards')) ?>
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=pages"
                   class="nav-link <?= in_array($currentRoute, ['pages', 'page_view', 'page_create', 'page_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    <?= Security::esc(t('nav.pages')) ?>
                </a>
            </li>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=tasks"
                   class="nav-link <?= in_array($currentRoute, ['tasks', 'task_view', 'task_create', 'task_edit'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
                    <?= Security::esc(t('nav.tasks')) ?>
                </a>
            </li>
            <?php if (Authz::can(Authz::REPORT_VIEW)): ?>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=reports_overview"
                   class="nav-link <?= in_array($currentRoute, ['reports_overview', 'reports_flow', 'reports_aging'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                    <?= Security::esc(t('nav.reports')) ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php
            // AP23: Hierarchical page tree with collapsible nodes
            $__pageTree = [];
            try {
                if (!empty($_SESSION['user_id'])) {
                    $__pageTree = Page::getTreeVisible(
                        (int) $_SESSION['user_id'],
                        $_SESSION['user_role'] ?? 'viewer',
                        $__activeTeamId
                    );
                }
            } catch (Throwable $e) {
                // Fallback for pre-migration
                try {
                    $__pageTree = Page::getTree();
                } catch (Throwable $e2) {
                    $__pageTree = [];
                }
            }
            if (!empty($__pageTree)):
        ?>
        <div class="sidebar-pages">
            <span class="sidebar-label"><?= Security::esc(t('nav.sidebar.pages')) ?></span>
            <div class="page-tree-scroll">
            <?php
            $__currentSlug = $_GET['slug'] ?? '';
            function renderPageTree(array $nodes, string $baseUrl, string $currentSlug, int $depth = 0): void {
                echo '<ul class="page-tree' . ($depth === 0 ? ' page-tree-root' : '') . '">';
                foreach ($nodes as $node) {
                    $isActive = ($currentSlug === $node['slug']);
                    $hasChildren = !empty($node['children']);
                    // Auto-expand if active page or descendant is active
                    $isExpanded = $isActive || ($hasChildren && self_or_descendant_active($node, $currentSlug));
                    echo '<li class="page-tree-item' . ($hasChildren ? ' has-children' : '') . ($isExpanded ? ' expanded' : '') . '">';
                    echo '<div class="page-tree-row" style="padding-left: ' . (4 + $depth * 16) . 'px">';
                    if ($hasChildren) {
                        echo '<button type="button" class="page-tree-toggle" aria-label="' . Security::esc(t('watch.expand_collapse')) . '">'
                           . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'
                           . '</button>';
                    } else {
                        echo '<span class="page-tree-spacer"></span>';
                    }
                    echo '<a href="' . Security::esc($baseUrl) . '/?r=page_view&amp;slug=' . Security::esc($node['slug']) . '"'
                       . ' class="page-tree-link' . ($isActive ? ' page-tree-active' : '') . '">'
                       . Security::esc($node['title'])
                       . '</a>';
                    echo '</div>';
                    if ($hasChildren) {
                        renderPageTree($node['children'], $baseUrl, $currentSlug, $depth + 1);
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }

            function self_or_descendant_active(array $node, string $currentSlug): bool {
                if ($currentSlug === $node['slug']) {
                    return true;
                }
                if (!empty($node['children'])) {
                    foreach ($node['children'] as $child) {
                        if (self_or_descendant_active($child, $currentSlug)) {
                            return true;
                        }
                    }
                }
                return false;
            }

            renderPageTree($__pageTree, $baseUrl, $__currentSlug);
            ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (Authz::can(Authz::ADMIN_USERS_MANAGE)): ?>
        <!-- AP23: Admin navigation block -->
        <div class="sidebar-admin">
            <span class="sidebar-label"><?= Security::esc(t('nav.sidebar.admin')) ?></span>
            <ul class="nav-list">
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users"
                       class="nav-link <?= in_array($currentRoute, ['admin_users', 'admin_user_create', 'admin_user_edit'], true) ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                        <?= Security::esc(t('nav.admin.users')) ?>
                    </a>
                </li>
                <?php if (Authz::can(Authz::TEAM_MANAGE)): ?>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_teams"
                       class="nav-link <?= in_array($currentRoute, ['admin_teams', 'admin_team_edit'], true) ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                        <?= Security::esc(t('nav.admin.teams')) ?>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_system"
                       class="nav-link <?= $currentRoute === 'admin_system' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
                        <?= Security::esc(t('nav.admin.system')) ?>
                    </a>
                </li>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_migrate"
                       class="nav-link <?= $currentRoute === 'admin_migrate' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></span>
                        <?= Security::esc(t('nav.admin.migrations')) ?>
                    </a>
                </li>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_email_queue"
                       class="nav-link <?= $currentRoute === 'admin_email_queue' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <?= Security::esc(t('nav.admin.email_queue')) ?>
                    </a>
                </li>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhooks"
                       class="nav-link <?= in_array($currentRoute, ['admin_webhooks', 'admin_webhook_create', 'admin_webhook_edit', 'admin_webhook_queue'], true) ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg></span>
                        <?= Security::esc(t('nav.admin.webhooks')) ?>
                    </a>
                </li>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_settings"
                       class="nav-link <?= $currentRoute === 'admin_settings' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                        <?= Security::esc(t('nav.admin.branding')) ?>
                    </a>
                </li>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_languages"
                       class="nav-link <?= $currentRoute === 'admin_languages' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg></span>
                        <?= Security::esc(t('nav.admin.languages')) ?>
                    </a>
                </li>
                <!-- AP28: System Health -->
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_health"
                       class="nav-link <?= $currentRoute === 'admin_health' ? 'active' : '' ?>">
                        <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                        <?= Security::esc(t('nav.admin.health')) ?>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="sidebar-footer">
            <span class="sidebar-label"><?= Security::esc(t('nav.sidebar.workspace')) ?></span>
            <span class="workspace-name"><?= Security::esc($appName) ?></span>
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

<!-- AP20: Footer -->
<footer class="app-footer">
    <span>Powered by <a href="<?= Security::esc($__versionInfo['repo'] ?? 'https://github.com/rueetschli/WorkPages') ?>" target="_blank" rel="noopener">WorkPages</a></span>
    <span class="app-footer-sep">&middot;</span>
    <span><?= Security::esc($__versionInfo['license'] ?? 'MIT') ?> License</span>
    <?php if (!empty($__versionInfo['version'])): ?>
    <span class="app-footer-sep">&middot;</span>
    <span>v<?= Security::esc($__versionInfo['version']) ?></span>
    <?php endif; ?>
</footer>

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

    /* -- Team Switcher Toggle -- */
    var teamSwitcherBtn = document.getElementById('team-switcher-btn');
    var teamSwitcherDropdown = document.getElementById('team-switcher-dropdown');
    if (teamSwitcherBtn && teamSwitcherDropdown) {
        teamSwitcherBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            teamSwitcherDropdown.classList.toggle('open');
        });
    }

    /* -- AP23: User Menu Toggle -- */
    var userMenuBtn = document.getElementById('user-menu-btn');
    var userMenuDropdown = document.getElementById('user-menu-dropdown');
    if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('open');
        });
    }

    /* -- Close all dropdowns on outside click -- */
    document.addEventListener('click', function(e) {
        if (teamSwitcherDropdown && !teamSwitcherDropdown.contains(e.target) && e.target !== teamSwitcherBtn) {
            teamSwitcherDropdown.classList.remove('open');
        }
        if (userMenuDropdown && !userMenuDropdown.contains(e.target) && e.target !== userMenuBtn && !userMenuBtn.contains(e.target)) {
            userMenuDropdown.classList.remove('open');
        }
    });

    /* -- AP23: Page Tree Toggle -- */
    var treeToggles = document.querySelectorAll('.page-tree-toggle');
    for (var t = 0; t < treeToggles.length; t++) {
        treeToggles[t].addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var item = this.closest('.page-tree-item');
            if (item) {
                item.classList.toggle('expanded');
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
<script src="<?= Security::esc($baseUrl) ?>/assets/notifications.js"></script>

</body>
</html>
