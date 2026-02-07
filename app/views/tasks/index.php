<?php
/**
 * Tasks list view.
 * Variables: $tasks (array), $users (array), $allTags (array),
 *            $tagsByTask (array), $filters (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Security::hasRole(['admin', 'member']);
$currentStatus  = $filters['status'] ?? '';
$currentOwnerId = $filters['owner_id'] ?? '';
$currentTag     = $filters['tag'] ?? '';
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Tasks</h1>
            <p class="subtitle">Aufgaben und operative Arbeit</p>
        </div>
        <?php if ($canEdit): ?>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_create" class="btn btn-primary">+ Neue Aufgabe</a>
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
                <label for="filter-status">Status</label>
                <select id="filter-status" name="status" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach (Task::STATUS_LABELS as $val => $label): ?>
                        <option value="<?= Security::esc($val) ?>"
                            <?= $currentStatus === $val ? 'selected' : '' ?>>
                            <?= Security::esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-owner">Owner</label>
                <select id="filter-owner" name="owner_id" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (string) $currentOwnerId === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-tag">Tag</label>
                <select id="filter-tag" name="tag" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach ($allTags as $tg): ?>
                        <option value="<?= Security::esc($tg['name']) ?>"
                            <?= $currentTag === $tg['name'] ? 'selected' : '' ?>>
                            <?= Security::esc($tg['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad">Filtern</button>
                <?php if ($currentStatus !== '' || $currentOwnerId !== '' || $currentTag !== ''): ?>
                    <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="btn btn-secondary btn-sm-pad">Zuruecksetzen</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($tasks)): ?>
    <div class="section-block">
        <p class="placeholder-text">Keine Aufgaben gefunden.</p>
        <?php if ($canEdit): ?>
        <p style="margin-top: var(--space-md);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_create" class="btn btn-primary">Erste Aufgabe erstellen</a>
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="pages-table-wrap">
        <table class="pages-table tasks-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Owner</th>
                    <th>Faellig</th>
                    <th>Tags</th>
                    <?php if ($canEdit): ?>
                    <th>Aktionen</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $t['id'] ?>" class="page-link">
                            <?= Security::esc($t['title']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="status-badge status-<?= Security::esc($t['status']) ?>">
                            <?= Security::esc(Task::STATUS_LABELS[$t['status']] ?? $t['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($t['owner_name']): ?>
                            <?= Security::esc($t['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['due_date']): ?>
                            <?php
                                $dueTs   = strtotime($t['due_date']);
                                $isOverdue = $t['status'] !== 'done' && $dueTs < strtotime('today');
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
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $t['id'] ?>" class="btn-sm">Bearbeiten</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
