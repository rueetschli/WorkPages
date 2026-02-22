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
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks"><?= Security::esc(t('tasks.title')) ?></a>
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
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>" class="btn btn-primary"><?= Security::esc(t('actions.edit')) ?></a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_delete&amp;id=<?= (int) $task['id'] ?>"
                  class="inline-form" onsubmit="return confirm('<?= Security::esc(t('messages.confirm_delete_task')) ?>');">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger"><?= Security::esc(t('actions.delete')) ?></button>
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
            <p class="placeholder-text"><?= Security::esc(t('messages.no_description')) ?>
                <?php if ($canEdit): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $task['id'] ?>"><?= Security::esc(t('tasks.add_description')) ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Meta sidebar - compact -->
    <div class="task-meta-card">
        <dl class="task-meta-list">
            <dt><?= Security::esc(t('labels.column')) ?></dt>
            <dd>
                <span class="status-badge"
                      <?php if (!empty($task['column_color'])): ?>style="border-left: 3px solid <?= Security::esc($task['column_color']) ?>;"<?php endif; ?>>
                    <?= Security::esc($task['column_name'] ?? '') ?>
                </span>
            </dd>

            <dt><?= Security::esc(t('labels.owner')) ?></dt>
            <dd>
                <?php if ($task['owner_name']): ?>
                    <?= Security::esc($task['owner_name']) ?>
                <?php else: ?>
                    <span class="text-muted"><?= Security::esc(t('tasks.not_assigned')) ?></span>
                <?php endif; ?>
            </dd>

            <dt><?= Security::esc(t('labels.due')) ?></dt>
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

            <dt><?= Security::esc(t('labels.tags')) ?></dt>
            <dd>
                <?php if (!empty($tags)): ?>
                    <div class="tag-list">
                        <?php foreach ($tags as $tg): ?>
                            <span class="tag-chip"><?= Security::esc($tg['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span class="text-muted"><?= Security::esc(t('tasks.no_tags')) ?></span>
                <?php endif; ?>
            </dd>

            <?php
                $__taskBoard = null;
                if (!empty($task['board_id'])) {
                    try { $__taskBoard = Board::findById((int) $task['board_id']); } catch (Throwable $e) {}
                }
            ?>
            <?php if ($__taskBoard): ?>
            <dt><?= Security::esc(t('labels.board')) ?></dt>
            <dd>
                <a href="<?= Security::esc($baseUrl) ?>/?r=board_view&amp;id=<?= (int) $__taskBoard['id'] ?>">
                    <?= Security::esc($__taskBoard['name']) ?>
                </a>
                &nbsp;<a href="<?= Security::esc($baseUrl) ?>/?r=structure&amp;board_id=<?= (int) $__taskBoard['id'] ?>" class="text-muted" style="font-size:0.85em;"><?= Security::esc(t('structure.tab_structure')) ?></a>
            </dd>
            <?php endif; ?>

            <?php
                // AP26: Sprint assignment
                $__taskSprint = null;
                $__assignableSprints = [];
                if (!empty($task['sprint_id'])) {
                    try { $__taskSprint = Sprint::findById((int) $task['sprint_id']); } catch (Throwable $e) {}
                }
                if (!empty($task['board_id'])) {
                    try { $__assignableSprints = Sprint::assignableForBoard((int) $task['board_id']); } catch (Throwable $e) {}
                }
            ?>
            <dt><?= Security::esc(t('sprint.sprint')) ?></dt>
            <dd>
                <?php if ($__taskSprint): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=sprint_burndown&amp;id=<?= (int) $__taskSprint['id'] ?>">
                        <?= Security::esc($__taskSprint['name']) ?>
                    </a>
                    <?php if (Authz::can(Authz::SPRINT_ASSIGN)): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=sprint_unassign_task" class="inline-form" style="display:inline;">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= Security::esc('?r=task_view&id=' . (int) $task['id']) ?>">
                        <button type="submit" class="btn-link text-muted" style="font-size:0.85em;" title="<?= Security::esc(t('sprint.remove_from_sprint')) ?>">&times;</button>
                    </form>
                    <?php endif; ?>
                <?php elseif (Authz::can(Authz::SPRINT_ASSIGN) && !empty($__assignableSprints)): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=sprint_assign_task" class="inline-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= Security::esc('?r=task_view&id=' . (int) $task['id']) ?>">
                        <select name="sprint_id" class="form-input form-input-sm" onchange="if(this.value)this.form.submit()">
                            <option value=""><?= Security::esc(t('sprint.select_sprint')) ?></option>
                            <?php foreach ($__assignableSprints as $__as): ?>
                                <option value="<?= (int) $__as['id'] ?>"><?= Security::esc($__as['name']) ?> (<?= Security::esc(t('sprint.status.' . $__as['status'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php else: ?>
                    <span class="text-muted">&mdash;</span>
                <?php endif; ?>
            </dd>

            <?php
                // AP25: Task type and parent
                $__taskType    = $task['task_type'] ?? 'task';
                $__parentTask  = null;
                if (!empty($task['parent_task_id'])) {
                    try { $__parentTask = Task::findById((int) $task['parent_task_id']); } catch (Throwable $e) {}
                }
            ?>
            <dt><?= Security::esc(t('structure.type_label')) ?></dt>
            <dd>
                <?php
                    $typeLabels = [
                        'epic'    => t('structure.type.epic'),
                        'feature' => t('structure.type.feature'),
                        'task'    => t('structure.type.task'),
                    ];
                    $typeBadgeClass = [
                        'epic'    => 'struct-type struct-type--epic',
                        'feature' => 'struct-type struct-type--feature',
                        'task'    => 'struct-type struct-type--task',
                    ];
                ?>
                <span class="<?= Security::esc($typeBadgeClass[$__taskType] ?? 'struct-type struct-type--task') ?>">
                    <?= Security::esc($typeLabels[$__taskType] ?? $__taskType) ?>
                </span>
            </dd>

            <?php if ($__parentTask): ?>
            <dt><?= Security::esc(t('structure.parent_label')) ?></dt>
            <dd>
                <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $__parentTask['id'] ?>">
                    <?= Security::esc($__parentTask['title']) ?>
                </a>
            </dd>
            <?php endif; ?>
        </dl>
        <style>
        .struct-type{display:inline-block;padding:2px 7px;border-radius:3px;font-size:.72em;font-weight:700;letter-spacing:.03em;text-transform:uppercase;}
        .struct-type--epic{background:#6f42c1;color:#fff;}
        .struct-type--feature{background:#0d6efd;color:#fff;}
        .struct-type--task{background:#6c757d;color:#fff;}
        </style>

        <?php if ($canEdit && count($__taskBoards) > 1): ?>
        <div class="task-meta-move">
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_move_task_board" class="inline-form">
                <?= Security::csrfField() ?>
                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                <label class="form-label form-label-sm"><?= Security::esc(t('tasks.move_to_board')) ?></label>
                <select name="target_board_id" class="form-input form-input-sm" onchange="if(this.value)this.form.submit()">
                    <option value=""><?= Security::esc(t('tasks.select_board')) ?></option>
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
    <h2><?= Security::esc(t('tasks.linked_pages')) ?></h2>
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
    <summary class="activity-collapsible-summary"><?= Security::esc(t('tasks.activity')) ?> (<?= count($activities) ?>)</summary>
    <?php require APP_DIR . '/views/partials/activity.php'; ?>
</details>

<div class="page-meta">
    <span class="text-muted">
        <?= Security::esc(t('labels.created_at')) ?> <?= Security::esc(date('d.m.Y H:i', strtotime($task['created_at']))) ?>
        <?php if ($task['creator_name']): ?>
            <?= Security::esc(t('labels.by')) ?> <?= Security::esc($task['creator_name']) ?>
        <?php endif; ?>
        <?php if ($task['updated_at']): ?>
            &middot; <?= Security::esc(t('labels.updated_at')) ?> <?= Security::esc(date('d.m.Y H:i', strtotime($task['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
