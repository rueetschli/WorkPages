<?php
/**
 * Page detail view.
 * Variables: $page (array), $breadcrumb (array), $renderedContent (string),
 *            $pageTasks (array), $pageTaskTags (array), $users (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Security::hasRole(['admin', 'member']);
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
        <div class="pages-table-wrap">
        <table class="pages-table page-tasks-table">
            <thead>
                <tr>
                    <?php if ($canEdit): ?><th style="width:60px;">Pos.</th><?php endif; ?>
                    <th>Titel</th>
                    <th>Status</th>
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
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $pt['id'] ?>" class="page-link">
                            <?= Security::esc($pt['title']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($canEdit): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_update_status" class="inline-form status-change-form">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="task_id" value="<?= (int) $pt['id'] ?>">
                            <input type="hidden" name="return_slug" value="<?= Security::esc($page['slug']) ?>">
                            <select name="status" class="form-input form-input-sm status-select status-<?= Security::esc($pt['status']) ?>" onchange="this.form.submit()">
                                <?php foreach (Task::STATUS_LABELS as $val => $label): ?>
                                    <option value="<?= Security::esc($val) ?>" <?= $pt['status'] === $val ? 'selected' : '' ?>><?= Security::esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="status-badge status-<?= Security::esc($pt['status']) ?>">
                            <?= Security::esc(Task::STATUS_LABELS[$pt['status']] ?? $pt['status']) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pt['owner_name']): ?>
                            <?= Security::esc($pt['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pt['due_date']): ?>
                            <?php
                                $dueTs = strtotime($pt['due_date']);
                                $isOverdue = $pt['status'] !== 'done' && $dueTs < strtotime('today');
                            ?>
                            <span class="<?= $isOverdue ? 'text-overdue' : '' ?>">
                                <?= Security::esc(date('d.m.Y', $dueTs)) ?>
                            </span>
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
                    <?php if ($canEdit): ?>
                    <td>
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

<div class="page-meta">
    <span class="text-muted">
        Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($page['created_at']))) ?>
        <?php if ($page['updated_at']): ?>
            &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
