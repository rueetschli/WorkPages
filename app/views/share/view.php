<?php
/**
 * Shared page view - minimal layout without navigation, search, or edit options.
 *
 * Variables: $page (array), $renderedContent (string),
 *            $pageTasks (array), $pageTaskTags (array), $pageTitle (string)
 */
$appName = $GLOBALS['config']['APP_NAME'] ?? 'Work Pages';
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::esc($pageTitle) ?> - <?= Security::esc($appName) ?></title>
    <link rel="stylesheet" href="<?= Security::esc($baseUrl) ?>/assets/app.css">
    <script>(function(){var t=localStorage.getItem('wp-theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
</head>
<body>

<header class="app-header">
    <div class="header-left">
        <span class="app-logo"><?= Security::esc($appName) ?></span>
    </div>
    <div class="header-right">
        <span class="user-role-label">Geteilte Seite (nur Lesen)</span>
    </div>
</header>

<div class="app-body">
    <main class="main-content" style="margin-left: 0; max-width: 900px; margin: 0 auto; padding: var(--sp-8);">

        <div class="page-header">
            <h1><?= Security::esc($page['title']) ?></h1>
        </div>

        <div class="page-content md-content">
            <?= $renderedContent ?>
        </div>

        <?php if (!empty($pageTasks)): ?>
        <div class="section-block page-tasks-section">
            <h2>Tasks</h2>
            <div class="pages-table-wrap">
                <table class="pages-table page-tasks-table">
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Status</th>
                            <th>Faellig</th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageTasks as $pt): ?>
                        <tr>
                            <td><?= Security::esc($pt['title']) ?></td>
                            <td>
                                <span class="status-badge status-<?= Security::esc($pt['status']) ?>">
                                    <?= Security::esc(Task::STATUS_LABELS[$pt['status']] ?? $pt['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($pt['due_date']): ?>
                                    <?= Security::esc(date('d.m.Y', strtotime($pt['due_date']))) ?>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $tTags = $pageTaskTags[(int) $pt['id']] ?? [];
                                    if (!empty($tTags)):
                                ?>
                                    <div class="tag-list">
                                        <?php foreach ($tTags as $tg): ?>
                                            <span class="tag-chip"><?= Security::esc($tg['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-meta">
            <span class="text-muted">
                Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($page['created_at']))) ?>
                <?php if ($page['updated_at']): ?>
                    &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
                <?php endif; ?>
            </span>
        </div>

    </main>
</div>

</body>
</html>
