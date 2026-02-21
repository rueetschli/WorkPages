<?php
/**
 * Tasks list view (AP13: uses board_columns instead of fixed status).
 * Variables: $tasks (array), $users (array), $allTags (array),
 *            $tagsByTask (array), $filters (array), $boardColumns (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Authz::can(Authz::TASK_CREATE);
$currentColumnId = $filters['column_id'] ?? '';
$currentOwnerId  = $filters['owner_id'] ?? '';
$currentTag      = $filters['tag'] ?? '';
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1><?= Security::esc(t('tasks.title')) ?></h1>
            <p class="subtitle"><?= Security::esc(t('tasks.subtitle')) ?></p>
        </div>
        <?php if ($canEdit): ?>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_create" class="btn btn-primary"><?= Security::esc(t('tasks.new_task')) ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="section-block task-filters">
    <form method="get" action="<?= Security::esc($baseUrl) ?>/" class="filter-form">
        <input type="hidden" name="r" value="tasks">

        <div class="filter-row">
            <div class="filter-group">
                <label for="filter-column"><?= Security::esc(t('labels.column')) ?></label>
                <select id="filter-column" name="column_id" class="form-input form-input-sm">
                    <option value=""><?= Security::esc(t('labels.all')) ?></option>
                    <?php foreach ($boardColumns as $col): ?>
                        <option value="<?= (int) $col['id'] ?>"
                            <?= (string) $currentColumnId === (string) $col['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($col['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-owner"><?= Security::esc(t('labels.owner')) ?></label>
                <select id="filter-owner" name="owner_id" class="form-input form-input-sm">
                    <option value=""><?= Security::esc(t('labels.all')) ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (string) $currentOwnerId === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-tag"><?= Security::esc(t('labels.tags')) ?></label>
                <select id="filter-tag" name="tag" class="form-input form-input-sm">
                    <option value=""><?= Security::esc(t('labels.all')) ?></option>
                    <?php foreach ($allTags as $tg): ?>
                        <option value="<?= Security::esc($tg['name']) ?>"
                            <?= $currentTag === $tg['name'] ? 'selected' : '' ?>>
                            <?= Security::esc($tg['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.filter')) ?></button>
                <?php if ($currentColumnId !== '' || $currentOwnerId !== '' || $currentTag !== ''): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="btn btn-secondary btn-sm-pad"><?= Security::esc(t('actions.reset')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($tasks)): ?>
    <div class="section-block">
        <p class="placeholder-text"><?= Security::esc(t('messages.no_tasks')) ?></p>
        <?php if ($canEdit): ?>
        <p style="margin-top: var(--sp-4);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_create" class="btn btn-primary"><?= Security::esc(t('tasks.create_first')) ?></a>
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table tasks-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('tasks.th_title')) ?></th>
                    <th><?= Security::esc(t('tasks.th_column')) ?></th>
                    <th><?= Security::esc(t('tasks.th_owner')) ?></th>
                    <th><?= Security::esc(t('tasks.th_due')) ?></th>
                    <th><?= Security::esc(t('tasks.th_tags')) ?></th>
                    <?php if ($canEdit): ?>
                    <th><?= Security::esc(t('labels.actions')) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td class="card-cell-title">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $t['id'] ?>" class="page-link">
                            <?= Security::esc($t['title']) ?>
                        </a>
                    </td>
                    <td data-label="<?= Security::esc(t('tasks.th_column')) ?>">
                        <span class="status-badge">
                            <?= Security::esc($t['column_name'] ?? '') ?>
                        </span>
                    </td>
                    <td data-label="<?= Security::esc(t('tasks.th_owner')) ?>">
                        <?php if ($t['owner_name']): ?>
                            <?= Security::esc($t['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('tasks.th_due')) ?>">
                        <?php if ($t['due_date']): ?>
                            <?php
                                $dueTs   = strtotime($t['due_date']);
                                $isOverdue = ($t['column_slug'] ?? '') !== 'done' && $dueTs < strtotime('today');
                            ?>
                            <span class="<?= $isOverdue ? 'text-overdue' : '' ?>">
                                <?= Security::esc(date('d.m.Y', $dueTs)) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('tasks.th_tags')) ?>">
                        <?php
                            $taskTags = $tagsByTask[(int) $t['id']] ?? [];
                            if (!empty($taskTags)):
                                foreach ($taskTags as $tg):
                        ?>
                            <span class="tag-chip"><?= Security::esc($tg['name']) ?></span>
                        <?php
                                endforeach;
                            else:
                        ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                    <td class="card-cell-actions">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $t['id'] ?>" class="btn-sm"><?= Security::esc(t('actions.edit')) ?></a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
