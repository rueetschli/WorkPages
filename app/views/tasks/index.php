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
                <label for="filter-column">Spalte</label>
                <select id="filter-column" name="column_id" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach ($boardColumns as $col): ?>
                        <option value="<?= (int) $col['id'] ?>"
                            <?= (string) $currentColumnId === (string) $col['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($col['name']) ?>
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
                <?php if ($currentColumnId !== '' || $currentOwnerId !== '' || $currentTag !== ''): ?>
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
        <p style="margin-top: var(--sp-4);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_create" class="btn btn-primary">Erste Aufgabe erstellen</a>
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table tasks-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Spalte</th>
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
                    <td class="card-cell-title">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $t['id'] ?>" class="page-link">
                            <?= Security::esc($t['title']) ?>
                        </a>
                    </td>
                    <td data-label="Spalte">
                        <span class="status-badge">
                            <?= Security::esc($t['column_name'] ?? '') ?>
                        </span>
                    </td>
                    <td data-label="Owner">
                        <?php if ($t['owner_name']): ?>
                            <?= Security::esc($t['owner_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Faellig">
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
                    <td data-label="Tags">
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
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_edit&amp;id=<?= (int) $t['id'] ?>" class="btn-sm">Bearbeiten</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
