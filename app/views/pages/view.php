<?php
/**
 * Page detail view (AP22: Information hierarchy refactor).
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
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages"><?= Security::esc(t('pages.title')) ?></a>
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
        <div class="page-actions">
            <?php
                $watchEntityType = 'page';
                $watchEntityId = (int) $page['id'];
                require APP_DIR . '/views/partials/watch_button.php';
            ?>
            <?php if ($canShare): ?>
            <button type="button" class="btn btn-secondary btn-sm-pad btn-responsive" title="<?= Security::esc(t('actions.share')) ?>" onclick="document.getElementById('share-panel').style.display = document.getElementById('share-panel').style.display === 'none' ? 'block' : 'none'"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg><span class="btn-label"><?= Security::esc(t('actions.share')) ?></span></button>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_move&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary btn-sm-pad btn-responsive" title="<?= Security::esc(t('ap30.move_page')) ?>"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg><span class="btn-label"><?= Security::esc(t('ap30.move_page')) ?></span></a>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_copy&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary btn-sm-pad btn-responsive" title="<?= Security::esc(t('ap30.copy_page')) ?>"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg><span class="btn-label"><?= Security::esc(t('ap30.copy_page')) ?></span></a>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-primary btn-responsive" title="<?= Security::esc(t('actions.edit')) ?>"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span class="btn-label"><?= Security::esc(t('actions.edit')) ?></span></a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_delete&slug=<?= Security::esc($page['slug']) ?>"
                  class="inline-form" onsubmit="return confirm(<?= Security::esc(json_encode(t('messages.confirm_delete_page'))) ?>);">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger btn-responsive" title="<?= Security::esc(t('actions.delete')) ?>"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg><span class="btn-label"><?= Security::esc(t('actions.delete')) ?></span></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canShare): ?>
<div id="share-panel" class="share-panel-collapsible" style="display:none;">
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

<!-- AP22: Page content is dominant - no box wrapper, full-width typography -->
<div class="page-content-primary markdown-body">
    <?= $renderedContent ?>
</div>

<!-- AP5: Linked Tasks section -->
<div class="section-block page-tasks-section">
    <div class="page-tasks-header">
        <h2><?= Security::esc(t('nav.tasks')) ?></h2>
        <?php if ($canEdit): ?>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_tasks_add&amp;slug=<?= Security::esc($page['slug']) ?>" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('pages.add_task')) ?></a>
        <?php endif; ?>
    </div>

    <?php if (empty($pageTasks)): ?>
        <p class="placeholder-text"><?= Security::esc(t('messages.no_tasks_linked')) ?></p>
    <?php else: ?>
        <div class="pages-table-wrap responsive-cards">
        <table class="pages-table page-tasks-table">
            <thead>
                <tr>
                    <?php if ($canEdit): ?><th style="width:60px;">Pos.</th><?php endif; ?>
                    <th><?= Security::esc(t('labels.title')) ?></th>
                    <th><?= Security::esc(t('labels.column')) ?></th>
                    <th><?= Security::esc(t('labels.owner')) ?></th>
                    <th><?= Security::esc(t('labels.due_date')) ?></th>
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
                    <td data-label="<?= Security::esc(t('labels.column')) ?>">
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
                    <td data-label="<?= Security::esc(t('labels.owner')) ?>">
                        <?php if ($pt['owner_name']): ?>
                            <?= Security::esc($pt['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('labels.due_date')) ?>">
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
                              class="inline-form" onsubmit="return confirm(<?= Security::esc(json_encode(t('messages.confirm_remove_link'))) ?>);">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="btn-sm btn-remove" title="<?= Security::esc(t('actions.remove_link')) ?>"><?= Security::esc(t('actions.remove_link')) ?></button>
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

<!-- AP17: Attachments -->
<?php
    $entityType = 'page';
    $entityId   = (int) $page['id'];
    require APP_DIR . '/views/partials/attachments.php';
?>

<!-- AP8: Comments -->
<?php
    require APP_DIR . '/views/partials/comments.php';
?>

<!-- AP22: Activity - collapsible, visually subdued -->
<details class="activity-collapsible">
    <summary class="activity-collapsible-summary"><?= Security::esc(t('pages.activity_count', ['count' => count($activities)])) ?></summary>
    <?php require APP_DIR . '/views/partials/activity.php'; ?>
</details>

<div class="page-meta">
    <span class="text-muted">
        Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($page['created_at']))) ?>
        <?php if ($page['updated_at']): ?>
            &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
