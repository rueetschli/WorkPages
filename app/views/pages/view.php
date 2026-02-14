<?php
/**
 * Page detail view.
 * Variables: $page (array), $breadcrumb (array), $renderedContent (string),
 *            $pageTasks (array), $pageTaskTags (array), $users (array),
 *            $comments (array), $activities (array), $flashError (string|null)
 */
$baseUrl  = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit  = Authz::can(Authz::PAGE_EDIT);
$canShare = Authz::can(Authz::SHARE_CREATE);
$activeShare = null;
if ($canShare) {
    $activeShare = PageShare::findActiveForPage((int) $page['id']);
}
?>

<!-- Breadcrumb -->
<?php if (!empty($breadcrumb)): ?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages">Pages</a>
        </li>
        <?php foreach ($breadcrumb as $i => $crumb): ?>
        <li class="breadcrumb-item <?= $i === count($breadcrumb) - 1 ? 'breadcrumb-current' : '' ?>">
            <?php if ($i < count($breadcrumb) - 1): ?>
                <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($crumb['slug']) ?>">
                    <?= Security::esc($crumb['title']) ?>
                </a>
            <?php else: ?>
                <span><?= Security::esc($crumb['title']) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= Security::esc($page['title']) ?></h1>
        <?php if ($canEdit): ?>
        <div class="page-actions">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-primary">Bearbeiten</a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_delete&slug=<?= Security::esc($page['slug']) ?>"
                  class="inline-form" onsubmit="return confirm('Seite wirklich loeschen?');">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger">Loeschen</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canShare): ?>
<div class="section-block share-section">
    <h3>Teilen</h3>
    <?php if ($activeShare): ?>
        <?php
            $shareUrl = $baseUrl . '/?r=share&page_token=' . urlencode($activeShare['token']);
        ?>
        <div class="share-link-box">
            <input type="text" class="form-input" value="<?= Security::esc($shareUrl) ?>" readonly onclick="this.select();">
            <?php if ($activeShare['expires_at']): ?>
                <small class="form-hint">Gueltig bis: <?= Security::esc(date('d.m.Y', strtotime($activeShare['expires_at']))) ?></small>
            <?php else: ?>
                <small class="form-hint">Kein Ablaufdatum</small>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=share_revoke" class="inline-form" style="margin-top: var(--sp-2);">
            <?= Security::csrfField() ?>
            <input type="hidden" name="share_id" value="<?= (int) $activeShare['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm-pad" onclick="return confirm('Share-Link wirklich widerrufen?');">Link widerrufen</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=share_create" class="share-create-form">
            <?= Security::csrfField() ?>
            <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="share-expires">Ablaufdatum (optional)</label>
                    <input type="date" id="share-expires" name="expires_at" class="form-input form-input-sm">
                </div>
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm-pad">Share-Link erstellen</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="page-content md-content">
    <?= $renderedContent ?>
</div>

<!-- AP5: Linked Tasks section -->
<div class="section-block page-tasks-section">
    <div class="page-tasks-header">
        <h2>Tasks</h2>
        <?php if ($canEdit): ?>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_tasks_add&amp;slug=<?= Security::esc($page['slug']) ?>" class="btn btn-primary btn-sm-pad">Task hinzufuegen</a>
        <?php endif; ?>
    </div>

    <?php if (empty($pageTasks)): ?>
        <p class="placeholder-text">Keine Aufgaben mit dieser Seite verknuepft.</p>
    <?php else: ?>
        <div class="pages-table-wrap responsive-cards">
        <table class="pages-table page-tasks-table">
            <thead>
                <tr>
                    <?php if ($canEdit): ?><th style="width:60px;">Pos.</th><?php endif; ?>
                    <th>Titel</th>
                    <th>Spalte</th>
                    <th>Owner</th>
                    <th>Faellig</th>
                    <th>Tags</th>
                    <?php if ($canEdit): ?><th style="width:80px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pageTasks as $idx => $pt): ?>
                <tr>
                    <?php if ($canEdit): ?>
                    <td class="reorder-cell">
                        <?php if ($idx > 0): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_tasks_reorder&amp;slug=<?= Security::esc($page['slug']) ?>&amp;task_id=<?= (int) $pt['id'] ?>&amp;dir=up" class="inline-form">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-reorder" title="Nach oben">&#9650;</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($idx < count($pageTasks) - 1): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_tasks_reorder&amp;slug=<?= Security::esc($page['slug']) ?>&amp;task_id=<?= (int) $pt['id'] ?>&amp;dir=down" class="inline-form">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-reorder" title="Nach unten">&#9660;</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="card-cell-title">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $pt['id'] ?>" class="page-link">
                            <?= Security::esc($pt['title']) ?>
                        </a>
                    </td>
                    <td data-label="Spalte">
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_update_status" class="inline-form status-change-form">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="task_id" value="<?= (int) $pt['id'] ?>">
                            <input type="hidden" name="return_slug" value="<?= Security::esc($page['slug']) ?>">
                            <select name="column_id" class="form-input form-input-sm status-select" onchange="this.form.submit()">
                                <?php foreach ($boardColumns as $col): ?>
                                    <option value="<?= (int) $col['id'] ?>" <?= (int) $pt['column_id'] === (int) $col['id'] ? 'selected' : '' ?>><?= Security::esc($col['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="status-badge">
                            <?= Security::esc($pt['column_name'] ?? '') ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Owner">
                        <?php if ($pt['owner_name']): ?>
                            <?= Security::esc($pt['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Faellig">
                        <?php if ($pt['due_date']): ?>
                            <?php
                                $dueTs = strtotime($pt['due_date']);
                                $isOverdue = ($pt['column_slug'] ?? '') !== 'done' && $dueTs < strtotime('today');
                            ?>
                            <span class="<?= $isOverdue ? 'text-overdue' : '' ?>">
                                <?= Security::esc(date('d.m.Y', $dueTs)) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tags">
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
                    <?php if ($canEdit): ?>
                    <td class="card-cell-actions">
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_tasks_remove&amp;slug=<?= Security::esc($page['slug']) ?>&amp;task_id=<?= (int) $pt['id'] ?>"
                              class="inline-form" onsubmit="return confirm('Verknuepfung wirklich entfernen? Die Aufgabe wird nicht geloescht.');">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-sm btn-remove" title="Verknuepfung entfernen">Entfernen</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- AP8: Comments -->
<?php
    $entityType = 'page';
    $entityId   = (int) $page['id'];
    require APP_DIR . '/views/partials/comments.php';
?>

<!-- AP8: Activity Log -->
<?php require APP_DIR . '/views/partials/activity.php'; ?>

<div class="page-meta">
    <span class="text-muted">
        Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($page['created_at']))) ?>
        <?php if ($page['updated_at']): ?>
            &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
