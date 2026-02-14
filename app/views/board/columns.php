<?php
/**
 * Board Columns Management view (AP13).
 * Variables: $columns (array), $taskCounts (array)
 */
$baseUrl   = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canManage = Authz::can(Authz::BOARD_COLUMNS_MANAGE);
$totalCols = count($columns);
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Board-Spalten verwalten</h1>
            <p class="subtitle">Spalten des Kanban-Boards konfigurieren</p>
        </div>
        <div class="page-actions">
            <a href="<?= Security::esc($baseUrl) ?>/?r=board" class="btn btn-secondary">Zurueck zum Board</a>
        </div>
    </div>
</div>

<!-- Add new column -->
<?php if ($canManage): ?>
<div class="section-block">
    <h2>Neue Spalte hinzufuegen</h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_create">
        <?= Security::csrfField() ?>
        <div class="filter-row" style="align-items: flex-end;">
            <div class="filter-group">
                <label for="col-name">Name <span class="required">*</span></label>
                <input type="text" id="col-name" name="name" class="form-input form-input-sm"
                       required maxlength="100" placeholder="z.B. Testing">
            </div>
            <div class="filter-group">
                <label for="col-color">Farbe</label>
                <input type="color" id="col-color" name="color" class="form-input form-input-sm"
                       value="#3b82f6" style="width: 60px; padding: 2px;">
            </div>
            <div class="filter-group">
                <label for="col-wip">WIP-Limit</label>
                <input type="number" id="col-wip" name="wip_limit" class="form-input form-input-sm"
                       min="1" max="999" placeholder="(kein Limit)" style="width: 100px;">
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad">Spalte erstellen</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Existing columns -->
<div class="section-block">
    <h2>Aktuelle Spalten (<?= $totalCols ?>)</h2>

    <?php if (empty($columns)): ?>
        <p class="placeholder-text">Keine Spalten vorhanden.</p>
    <?php else: ?>
        <div class="pages-table-wrap responsive-cards">
            <table class="pages-table">
                <thead>
                    <tr>
                        <th>Reihenfolge</th>
                        <th>Name</th>
                        <th>Farbe</th>
                        <th>WIP-Limit</th>
                        <th>Tasks</th>
                        <th>Standard</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $i => $col):
                        $colId      = (int) $col['id'];
                        $colName    = $col['name'];
                        $colColor   = $col['color'] ?? '';
                        $colWip     = $col['wip_limit'];
                        $isDefault  = (int) $col['is_default'] === 1;
                        $count      = $taskCounts[$colId] ?? 0;
                        $isFirst    = ($i === 0);
                        $isLast     = ($i === $totalCols - 1);
                    ?>
                    <tr>
                        <!-- Reorder -->
                        <td class="reorder-cell" data-label="Position">
                            <?php if (!$isFirst && $canManage): ?>
                                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_move_up" class="inline-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $colId ?>">
                                    <button type="submit" class="btn-reorder" title="Nach oben">&uarr;</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$isLast && $canManage): ?>
                                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_move_down" class="inline-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $colId ?>">
                                    <button type="submit" class="btn-reorder" title="Nach unten">&darr;</button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <!-- Name (editable form) -->
                        <td class="card-cell-title" data-label="Name">
                            <strong><?= Security::esc($colName) ?></strong>
                        </td>

                        <!-- Color -->
                        <td data-label="Farbe">
                            <?php if ($colColor): ?>
                                <span class="column-color-swatch" style="background: <?= Security::esc($colColor) ?>;"></span>
                            <?php else: ?>
                                <span class="text-muted">--</span>
                            <?php endif; ?>
                        </td>

                        <!-- WIP Limit -->
                        <td data-label="WIP-Limit">
                            <?php if ($colWip !== null): ?>
                                <?= (int) $colWip ?>
                            <?php else: ?>
                                <span class="text-muted">--</span>
                            <?php endif; ?>
                        </td>

                        <!-- Task count -->
                        <td data-label="Tasks">
                            <span class="kanban-column-count"><?= $count ?></span>
                        </td>

                        <!-- Default -->
                        <td data-label="Standard">
                            <?php if ($isDefault): ?>
                                <span class="status-badge status-ready">Standard</span>
                            <?php elseif ($canManage): ?>
                                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_set_default" class="inline-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $colId ?>">
                                    <button type="submit" class="btn-sm">Als Standard setzen</button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="card-cell-actions" data-label="">
                            <?php if ($canManage): ?>
                                <button type="button" class="btn-sm"
                                        onclick="toggleEditRow(<?= $colId ?>)">Bearbeiten</button>

                                <?php if ($totalCols > 1): ?>
                                    <button type="button" class="btn-sm btn-remove"
                                            onclick="toggleDeleteRow(<?= $colId ?>)">Loeschen</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Inline edit row (hidden by default) -->
                    <?php if ($canManage): ?>
                    <tr id="edit-row-<?= $colId ?>" style="display: none;" class="column-edit-row">
                        <td colspan="7">
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_update" class="column-inline-form">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $colId ?>">
                                <div class="filter-row" style="align-items: flex-end;">
                                    <div class="filter-group">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-input form-input-sm"
                                               value="<?= Security::esc($colName) ?>" required maxlength="100">
                                    </div>
                                    <div class="filter-group">
                                        <label>Farbe</label>
                                        <input type="color" name="color" class="form-input form-input-sm"
                                               value="<?= Security::esc($colColor ?: '#3b82f6') ?>"
                                               style="width: 60px; padding: 2px;">
                                    </div>
                                    <div class="filter-group">
                                        <label>WIP-Limit</label>
                                        <input type="number" name="wip_limit" class="form-input form-input-sm"
                                               min="1" max="999" style="width: 100px;"
                                               value="<?= $colWip !== null ? (int) $colWip : '' ?>"
                                               placeholder="(kein)">
                                    </div>
                                    <div class="filter-group filter-actions">
                                        <button type="submit" class="btn btn-primary btn-sm-pad">Speichern</button>
                                        <button type="button" class="btn btn-secondary btn-sm-pad"
                                                onclick="toggleEditRow(<?= $colId ?>)">Abbrechen</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>

                    <!-- Inline delete confirmation (hidden by default) -->
                    <?php if ($totalCols > 1): ?>
                    <tr id="delete-row-<?= $colId ?>" style="display: none;" class="column-delete-row">
                        <td colspan="7">
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=board_column_delete" class="column-inline-form">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $colId ?>">
                                <div class="filter-row" style="align-items: flex-end;">
                                    <div class="filter-group" style="flex: 1;">
                                        <label>Tasks verschieben nach <span class="required">*</span></label>
                                        <select name="target_column_id" class="form-input form-input-sm" required>
                                            <?php foreach ($columns as $other):
                                                if ((int) $other['id'] === $colId) continue;
                                            ?>
                                                <option value="<?= (int) $other['id'] ?>">
                                                    <?= Security::esc($other['name']) ?>
                                                    (<?= $taskCounts[(int) $other['id']] ?? 0 ?> Tasks)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($count > 0): ?>
                                            <span class="form-hint" style="color: var(--color-warning);">
                                                <?= $count ?> Task(s) in dieser Spalte werden verschoben.
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="filter-group filter-actions">
                                        <button type="submit" class="btn btn-danger btn-sm-pad"
                                                onclick="return confirm('Spalte &quot;<?= Security::esc($colName) ?>&quot; wirklich loeschen?')">
                                            Loeschen
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm-pad"
                                                onclick="toggleDeleteRow(<?= $colId ?>)">Abbrechen</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleEditRow(id) {
    var row = document.getElementById('edit-row-' + id);
    if (row) {
        row.style.display = row.style.display === 'none' ? '' : 'none';
    }
    // Hide delete row if open
    var delRow = document.getElementById('delete-row-' + id);
    if (delRow) delRow.style.display = 'none';
}

function toggleDeleteRow(id) {
    var row = document.getElementById('delete-row-' + id);
    if (row) {
        row.style.display = row.style.display === 'none' ? '' : 'none';
    }
    // Hide edit row if open
    var editRow = document.getElementById('edit-row-' + id);
    if (editRow) editRow.style.display = 'none';
}
</script>
