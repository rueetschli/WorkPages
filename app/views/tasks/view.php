<?php
/**
 * Task detail view (AP22: Information hierarchy refactor).
 * Variables: $task (array), $tags (array), $renderedContent (string), $users (array), $linkedPages (array),
 *            $comments (array), $activities (array), $flashError (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Authz::can(Authz::TASK_EDIT);

// AP22: Available boards for "move to board" feature
$__taskBoards = [];
if ($canEdit) {
    $userId     = (int) $_SESSION['user_id'];
    $globalRole = $_SESSION['user_role'] ?? 'viewer';
    $__taskBoards = Board::allVisibleTo($userId, $globalRole);
}
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
        <div class="page-actions">
            <?php
                $watchEntityType = 'task';
                $watchEntityId = (int) $task['id'];
                require APP_DIR . '/views/partials/watch_button.php';
            ?>
            <?php if ($canEdit): ?>
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>" class="btn btn-primary">Bearbeiten</a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_delete&amp;id=<?= (int) $task['id'] ?>"
                  class="inline-form" onsubmit="return confirm('Aufgabe wirklich loeschen?');">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger">Loeschen</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="task-detail-grid">
    <!-- AP22: Description is dominant -->
    <div class="task-description-primary">
        <?php if ($renderedContent !== ''): ?>
            <div class="page-content-primary md-content">
                <?= $renderedContent ?>
            </div>
        <?php else: ?>
            <p class="placeholder-text">Keine Beschreibung vorhanden.
                <?php if ($canEdit): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>">Beschreibung hinzufuegen</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Meta sidebar - compact -->
    <div class="task-meta-card">
        <dl class="task-meta-list">
            <dt>Spalte</dt>
            <dd>
                <span class="status-badge"
                      <?php if (!empty($task['column_color'])): ?>style="border-left: 3px solid <?= Security::esc($task['column_color']) ?>;"<?php endif; ?>>
                    <?= Security::esc($task['column_name'] ?? '') ?>
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
                        $isOverdue = ($task['column_slug'] ?? '') !== 'done' && $dueTs < strtotime('today');
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

            <?php
                $__taskBoard = null;
                if (!empty($task['board_id'])) {
                    try { $__taskBoard = Board::findById((int) $task['board_id']); } catch (Throwable $e) {}
                }
            ?>
            <?php if ($__taskBoard): ?>
            <dt>Board</dt>
            <dd>
                <a href="<?= Security::esc($baseUrl) ?>/?r=board_view&amp;id=<?= (int) $__taskBoard['id'] ?>">
                    <?= Security::esc($__taskBoard['name']) ?>
                </a>
            </dd>
            <?php endif; ?>
        </dl>

        <?php if ($canEdit && count($__taskBoards) > 1): ?>
        <div class="task-meta-move">
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_move_task_board" class="inline-form">
                <?= Security::csrfField() ?>
                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                <label class="form-label form-label-sm">In Board verschieben</label>
                <select name="target_board_id" class="form-input form-input-sm" onchange="if(this.value)this.form.submit()">
                    <option value="">Board waehlen...</option>
                    <?php foreach ($__taskBoards as $tb): ?>
                        <?php if ((int) $tb['id'] !== (int) ($task['board_id'] ?? 0)): ?>
                        <option value="<?= (int) $tb['id'] ?>"><?= Security::esc($tb['name']) ?><?= $tb['team_name'] ? ' (' . Security::esc($tb['team_name']) . ')' : '' ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- AP5: Linked Pages -->
<?php if (!empty($linkedPages)): ?>
<div class="section-block section-secondary">
    <h2>Verknuepfte Seiten</h2>
    <ul class="linked-pages-list">
        <?php foreach ($linkedPages as $lp): ?>
            <li>
                <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc($lp['slug']) ?>" class="page-link">
                    <?= Security::esc($lp['title']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- AP17: Attachments -->
<?php
    $entityType = 'task';
    $entityId   = (int) $task['id'];
    require APP_DIR . '/views/partials/attachments.php';
?>

<!-- AP8: Comments -->
<?php
    require APP_DIR . '/views/partials/comments.php';
?>

<!-- AP22: Activity - collapsible, visually subdued -->
<details class="activity-collapsible">
    <summary class="activity-collapsible-summary">Aktivitaet (<?= count($activities) ?>)</summary>
    <?php require APP_DIR . '/views/partials/activity.php'; ?>
</details>

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
