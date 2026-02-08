<?php
/**
 * Task detail view.
 * Variables: $task (array), $tags (array), $renderedContent (string), $users (array), $linkedPages (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Security::hasRole(['admin', 'member']);
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks">Tasks</a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc($task['title']) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= Security::esc($task['title']) ?></h1>
        <?php if ($canEdit): ?>
        <div class="page-actions">
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>" class="btn btn-primary">Bearbeiten</a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_delete&amp;id=<?= (int) $task['id'] ?>"
                  class="inline-form" onsubmit="return confirm('Aufgabe wirklich loeschen?');">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger">Loeschen</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="task-detail-grid">
    <!-- Meta sidebar -->
    <div class="task-meta-card section-block">
        <dl class="task-meta-list">
            <dt>Status</dt>
            <dd>
                <span class="status-badge status-<?= Security::esc($task['status']) ?>">
                    <?= Security::esc(Task::STATUS_LABELS[$task['status']] ?? $task['status']) ?>
                </span>
            </dd>

            <dt>Owner</dt>
            <dd>
                <?php if ($task['owner_name']): ?>
                    <?= Security::esc($task['owner_name']) ?>
                <?php else: ?>
                    <span class="text-muted">Nicht zugewiesen</span>
                <?php endif; ?>
            </dd>

            <dt>Faellig</dt>
            <dd>
                <?php if ($task['due_date']): ?>
                    <?php
                        $dueTs   = strtotime($task['due_date']);
                        $isOverdue = $task['status'] !== 'done' && $dueTs < strtotime('today');
                    ?>
                    <span class="<?= $isOverdue ? 'text-overdue' : '' ?>">
                        <?= Security::esc(date('d.m.Y', $dueTs)) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">&mdash;</span>
                <?php endif; ?>
            </dd>

            <dt>Tags</dt>
            <dd>
                <?php if (!empty($tags)): ?>
                    <div class="tag-list">
                        <?php foreach ($tags as $tg): ?>
                            <span class="tag-chip"><?= Security::esc($tg['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span class="text-muted">Keine Tags</span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <!-- Description -->
    <div class="task-description">
        <?php if ($renderedContent !== ''): ?>
            <div class="page-content md-content">
                <?= $renderedContent ?>
            </div>
        <?php else: ?>
            <div class="section-block">
                <p class="placeholder-text">Keine Beschreibung vorhanden.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- AP5: Linked Pages -->
<div class="section-block">
    <h2>Verknuepfte Seiten</h2>
    <?php if (!empty($linkedPages)): ?>
        <ul class="linked-pages-list">
            <?php foreach ($linkedPages as $lp): ?>
                <li>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc($lp['slug']) ?>" class="page-link">
                        <?= Security::esc($lp['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="placeholder-text">Nicht mit Seiten verknuepft.</p>
    <?php endif; ?>
</div>

<div class="page-meta">
    <span class="text-muted">
        Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($task['created_at']))) ?>
        <?php if ($task['creator_name']): ?>
            von <?= Security::esc($task['creator_name']) ?>
        <?php endif; ?>
        <?php if ($task['updated_at']): ?>
            &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($task['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
