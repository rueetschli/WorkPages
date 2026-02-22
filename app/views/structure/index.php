<?php
/**
 * Structure View – AP25.
 *
 * Variables:
 *   $board         - array: current board row
 *   $tree          - nested task tree (from TaskStructureService::buildTree)
 *   $boardColumns  - array of board_columns rows
 *   $users         - array for owner dropdown
 *   $canEdit       - bool: can user edit structure
 *   $flashSuccess  - string|null
 *   $flashError    - string|null
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];

// AP27: Saved views for structure
$__viewType   = 'structure';
$__contextId  = $boardId;
$__returnTo   = '?' . ($_SERVER['QUERY_STRING'] ?? 'r=structure&board_id=' . $boardId);
$__viewParams = [];
foreach (['owner_id', 'tag', 'status'] as $__fk) {
    if (!empty($_GET[$__fk])) $__viewParams[$__fk] = $_GET[$__fk];
}
$__userViews = [];
try {
    $__userViews = UserView::allForUserByType((int) $_SESSION['user_id'], 'structure');
} catch (Throwable $e) {}

// Helper: render type icon/label
function structTypeLabel(string $type): string
{
    return match ($type) {
        'epic'    => '<span class="struct-type struct-type--epic">' . Security::esc(t('structure.type.epic')) . '</span>',
        'feature' => '<span class="struct-type struct-type--feature">' . Security::esc(t('structure.type.feature')) . '</span>',
        default   => '<span class="struct-type struct-type--task">' . Security::esc(t('structure.type.task')) . '</span>',
    };
}

// Helper: render rollup bar (only for epic/feature)
function structRollup(array $node): string
{
    $type = $node['task_type'] ?? 'task';
    if ($type === 'task') {
        return '';
    }
    $rollup = $node['rollup'] ?? ['total' => 0, 'done' => 0, 'pct' => 0];
    if ($rollup['total'] === 0) {
        return '<span class="struct-rollup struct-rollup--empty">' . Security::esc(t('structure.rollup.no_tasks')) . '</span>';
    }
    $pct  = (int) $rollup['pct'];
    $done  = (int) $rollup['done'];
    $total = (int) $rollup['total'];
    return '<span class="struct-rollup" title="' . $done . '/' . $total . '">'
         . '<span class="struct-rollup-bar"><span class="struct-rollup-fill" style="width:' . $pct . '%"></span></span>'
         . '<span class="struct-rollup-pct">' . $pct . '%</span>'
         . '</span>';
}

// Recursively render tree nodes
function renderStructureNode(array $node, int $depth, int $boardId, array $boardColumns, array $users, bool $canEdit, string $baseUrl): void
{
    $esc     = [Security::class, 'esc'];
    $type    = $node['task_type']          ?? 'task';
    $taskId  = (int) $node['id'];
    $nodeKey = 'struct-node-' . $taskId;
    $hasChildren = !empty($node['children']);
    $collapseClass = $depth < 2 ? 'open' : 'open'; // epics+features open by default

    $indentPx = $depth * 24;
?>
    <tr class="struct-row struct-row--<?= Security::esc($type) ?> struct-depth-<?= $depth ?>"
        data-id="<?= $taskId ?>"
        data-depth="<?= $depth ?>"
        data-type="<?= Security::esc($type) ?>">

        <!-- Checkbox -->
        <td class="struct-td struct-td--check">
            <?php if ($canEdit): ?>
                <input type="checkbox" name="selected_tasks[]" value="<?= $taskId ?>"
                       form="bulk-form" class="struct-check" aria-label="<?= Security::esc(t('structure.select_task')) ?>">
            <?php endif; ?>
        </td>

        <!-- Title with indent + toggle -->
        <td class="struct-td struct-td--title">
            <div class="struct-title-cell" style="padding-left:<?= $indentPx ?>px">
                <?php if ($hasChildren): ?>
                    <button type="button" class="struct-toggle"
                            aria-expanded="true"
                            data-target="<?= Security::esc($nodeKey) ?>"
                            title="<?= Security::esc(t('structure.toggle')) ?>">
                        <span class="struct-toggle-icon">&#9660;</span>
                    </button>
                <?php else: ?>
                    <span class="struct-toggle-placeholder"></span>
                <?php endif; ?>

                <?= structTypeLabel($type) ?>

                <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= $taskId ?>"
                   class="struct-task-link">
                    <?= Security::esc($node['title'] ?? '') ?>
                </a>
            </div>
        </td>

        <!-- Column (status) -->
        <td class="struct-td struct-td--column">
            <span class="status-badge"
                  <?php if (!empty($node['column_color'])): ?>style="border-left:3px solid <?= Security::esc($node['column_color']) ?>"<?php endif; ?>>
                <?= Security::esc($node['column_name'] ?? '') ?>
            </span>
        </td>

        <!-- Owner -->
        <td class="struct-td struct-td--owner">
            <?= $node['owner_name'] ? Security::esc($node['owner_name']) : '<span class="text-muted">&mdash;</span>' ?>
        </td>

        <!-- Due date -->
        <td class="struct-td struct-td--due">
            <?php if ($node['due_date'] ?? null): ?>
                <?php
                    $dueTs    = strtotime($node['due_date']);
                    $overdue  = ($node['column_category'] ?? '') !== 'done' && $dueTs < strtotime('today');
                ?>
                <span class="<?= $overdue ? 'text-overdue' : '' ?>">
                    <?= Security::esc(date('d.m.Y', $dueTs)) ?>
                </span>
            <?php else: ?>
                <span class="text-muted">&mdash;</span>
            <?php endif; ?>
        </td>

        <!-- Tags -->
        <td class="struct-td struct-td--tags">
            <?php if (!empty($node['tag_list'])): ?>
                <div class="tag-list tag-list--compact">
                    <?php foreach (explode(',', $node['tag_list']) as $tagName): ?>
                        <span class="tag-chip tag-chip--sm"><?= Security::esc(trim($tagName)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </td>

        <!-- Rollup -->
        <td class="struct-td struct-td--rollup">
            <?= structRollup($node) ?>
        </td>

        <!-- Actions -->
        <?php if ($canEdit): ?>
        <td class="struct-td struct-td--actions">
            <div class="struct-action-row">
                <!-- Move up -->
                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=structure_move_up" class="inline-form">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="task_id"  value="<?= $taskId ?>">
                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                    <button type="submit" class="btn-icon" title="<?= Security::esc(t('structure.move_up')) ?>">&#8593;</button>
                </form>
                <!-- Move down -->
                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=structure_move_down" class="inline-form">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="task_id"  value="<?= $taskId ?>">
                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                    <button type="submit" class="btn-icon" title="<?= Security::esc(t('structure.move_down')) ?>">&#8595;</button>
                </form>
                <!-- Set type -->
                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=structure_set_type" class="inline-form">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="task_id"  value="<?= $taskId ?>">
                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                    <select name="task_type" class="form-input form-input--inline"
                            onchange="this.form.submit()"
                            aria-label="<?= Security::esc(t('structure.type_label')) ?>">
                        <?php foreach (['epic', 'feature', 'task'] as $tType): ?>
                            <option value="<?= Security::esc($tType) ?>"
                                <?= $type === $tType ? 'selected' : '' ?>>
                                <?= Security::esc(t('structure.type.' . $tType)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <!-- Set parent -->
                <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=structure_set_parent" class="inline-form struct-parent-form">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="task_id"  value="<?= $taskId ?>">
                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                    <?php
                        $currentParentId = $node['parent_task_id'] ?? null;
                        $parentOptions   = Task::eligibleParents($boardId, $type, $taskId);
                    ?>
                    <select name="parent_id" class="form-input form-input--inline"
                            onchange="this.form.submit()"
                            aria-label="<?= Security::esc(t('structure.parent_label')) ?>">
                        <option value=""><?= Security::esc(t('structure.no_parent')) ?></option>
                        <?php foreach ($parentOptions as $po): ?>
                            <option value="<?= (int) $po['id'] ?>"
                                <?= (string) $currentParentId === (string) $po['id'] ? 'selected' : '' ?>>
                                <?= Security::esc($po['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </td>
        <?php endif; ?>
    </tr>

    <?php if ($hasChildren): ?>
        <?php foreach ($node['children'] as $child): ?>
            <?php renderStructureNode($child, $depth + 1, $boardId, $boardColumns, $users, $canEdit, $baseUrl); ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php
}
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= $esc($baseUrl) ?>/?r=boards"><?= $esc(t('nav.boards')) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= $esc($baseUrl) ?>/?r=board_view&id=<?= $boardId ?>"><?= $esc($board['name']) ?></a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= $esc(t('structure.title')) ?></span>
        </li>
    </ol>
</nav>

<!-- Flash messages -->
<?php if ($flashSuccess ?? null): ?>
    <div class="alert alert-success"><?= $esc($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError ?? null): ?>
    <div class="alert alert-error"><?= $esc($flashError) ?></div>
<?php endif; ?>

<!-- Page header with tabs: Board | Structure -->
<div class="page-header">
    <div class="page-header-row">
        <div style="display:flex; align-items:center; gap:var(--sp-3);">
            <a href="<?= $esc($baseUrl) ?>/?r=boards" class="btn btn-secondary btn-sm-pad" title="<?= $esc(t('board.all_boards')) ?>">&larr;</a>
            <h1><?= $esc($board['name']) ?>
                <?php if ($board['team_name'] ?? ''): ?>
                    <span class="status-badge" style="font-size:0.7em;vertical-align:middle;"><?= $esc($board['team_name']) ?></span>
                <?php endif; ?>
            </h1>
        </div>
    </div>

    <!-- Board / Structure tabs -->
    <div class="struct-tabs" style="margin-top:var(--sp-3);">
        <a href="<?= $esc($baseUrl) ?>/?r=board_view&id=<?= $boardId ?>"
           class="struct-tab"><?= $esc(t('structure.tab_board')) ?></a>
        <a href="<?= $esc($baseUrl) ?>/?r=structure&board_id=<?= $boardId ?>"
           class="struct-tab struct-tab--active"><?= $esc(t('structure.tab_structure')) ?></a>
    </div>
</div>

<!-- AP27: Saved Views -->
<?php require APP_DIR . '/views/partials/view_save_form.php'; ?>
<?php require APP_DIR . '/views/partials/view_manage.php'; ?>

<!-- Bulk action bar (visible only when checkboxes selected) -->
<?php if ($canEdit): ?>
<div class="struct-bulk-bar" id="bulk-bar" style="display:none;">
    <form id="bulk-form" method="post" action="<?= $esc($baseUrl) ?>/?r=structure_bulk_action">
        <?= Security::csrfField() ?>
        <input type="hidden" name="board_id" value="<?= $boardId ?>">

        <span class="struct-bulk-label" id="bulk-count-label"></span>

        <!-- Action selector -->
        <select name="bulk_action" id="bulk-action-select" class="form-input form-input--inline">
            <option value=""><?= $esc(t('structure.bulk.choose_action')) ?></option>
            <option value="set_column"><?= $esc(t('structure.bulk.set_column')) ?></option>
            <option value="set_owner"><?= $esc(t('structure.bulk.set_owner')) ?></option>
            <option value="add_tags"><?= $esc(t('structure.bulk.add_tags')) ?></option>
            <option value="remove_tags"><?= $esc(t('structure.bulk.remove_tags')) ?></option>
            <option value="set_sprint"><?= $esc(t('structure.bulk.set_sprint')) ?></option>
        </select>

        <!-- Dynamic sub-fields -->
        <span class="struct-bulk-field" id="bulk-field-set_column" style="display:none;">
            <select name="bulk_column_id" class="form-input form-input--inline">
                <?php foreach ($boardColumns as $col): ?>
                    <option value="<?= (int) $col['id'] ?>"><?= $esc($col['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </span>

        <span class="struct-bulk-field" id="bulk-field-set_owner" style="display:none;">
            <select name="bulk_owner_id" class="form-input form-input--inline">
                <option value=""><?= $esc(t('tasks.owner_none')) ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= $esc($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </span>

        <span class="struct-bulk-field" id="bulk-field-add_tags" style="display:none;">
            <input type="text" name="bulk_tags" class="form-input form-input--inline"
                   placeholder="<?= $esc(t('structure.bulk.tags_placeholder')) ?>">
        </span>

        <span class="struct-bulk-field" id="bulk-field-remove_tags" style="display:none;">
            <input type="text" name="bulk_tags" class="form-input form-input--inline"
                   placeholder="<?= $esc(t('structure.bulk.tags_placeholder')) ?>">
        </span>

        <?php
            $__bulkSprints = [];
            try { $__bulkSprints = Sprint::assignableForBoard($boardId); } catch (Throwable $e) {}
        ?>
        <span class="struct-bulk-field" id="bulk-field-set_sprint" style="display:none;">
            <select name="bulk_sprint_id" class="form-input form-input--inline">
                <option value=""><?= $esc(t('sprint.no_sprint')) ?></option>
                <?php foreach ($__bulkSprints as $__bs): ?>
                    <option value="<?= (int) $__bs['id'] ?>"><?= $esc($__bs['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </span>

        <button type="submit" class="btn btn-primary btn-sm-pad" id="bulk-submit"
                onclick="return document.getElementById('bulk-action-select').value !== ''">
            <?= $esc(t('structure.bulk.apply')) ?>
        </button>
        <button type="button" class="btn btn-secondary btn-sm-pad" id="bulk-cancel">
            <?= $esc(t('actions.cancel')) ?>
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Structure table -->
<div class="struct-container">
    <?php if (empty($tree)): ?>
        <div class="placeholder-text" style="padding:var(--sp-6) 0; text-align:center;">
            <?= $esc(t('structure.empty')) ?>
            <?php if ($canEdit): ?>
                <a href="<?= $esc($baseUrl) ?>/?r=task_create&board_id=<?= $boardId ?>" class="btn btn-primary btn-sm-pad" style="margin-left:var(--sp-2);">
                    <?= $esc(t('tasks.new_task')) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <table class="struct-table" aria-label="<?= $esc(t('structure.title')) ?>">
        <thead>
            <tr>
                <?php if ($canEdit): ?>
                    <th class="struct-th struct-th--check">
                        <input type="checkbox" id="select-all" title="<?= $esc(t('structure.select_all')) ?>">
                    </th>
                <?php endif; ?>
                <th class="struct-th struct-th--title"><?= $esc(t('labels.title')) ?></th>
                <th class="struct-th struct-th--column"><?= $esc(t('labels.column')) ?></th>
                <th class="struct-th struct-th--owner"><?= $esc(t('labels.owner')) ?></th>
                <th class="struct-th struct-th--due"><?= $esc(t('labels.due')) ?></th>
                <th class="struct-th struct-th--tags"><?= $esc(t('labels.tags')) ?></th>
                <th class="struct-th struct-th--rollup"><?= $esc(t('structure.progress')) ?></th>
                <?php if ($canEdit): ?>
                    <th class="struct-th struct-th--actions"><?= $esc(t('labels.actions')) ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="struct-tbody">
            <?php foreach ($tree as $rootNode): ?>
                <?php renderStructureNode($rootNode, 0, $boardId, $boardColumns, $users, $canEdit, $baseUrl); ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Legend -->
<div class="struct-legend" style="margin-top:var(--sp-4); display:flex; gap:var(--sp-4); align-items:center; font-size:0.85em; color:var(--color-text-muted);">
    <span><?= $esc(t('structure.legend')) ?>:</span>
    <span class="struct-type struct-type--epic"><?= $esc(t('structure.type.epic')) ?></span>
    <span class="struct-type struct-type--feature"><?= $esc(t('structure.type.feature')) ?></span>
    <span class="struct-type struct-type--task"><?= $esc(t('structure.type.task')) ?></span>
</div>

<!-- Add task link -->
<?php if ($canEdit): ?>
<div style="margin-top:var(--sp-3);">
    <a href="<?= $esc($baseUrl) ?>/?r=task_create&board_id=<?= $boardId ?>" class="btn btn-secondary">
        + <?= $esc(t('tasks.new_task')) ?>
    </a>
</div>
<?php endif; ?>

<style>
/* ── AP25 Structure View styles ──────────────────────────── */
.struct-tabs        { display:flex; gap:0; border-bottom:2px solid var(--color-border); }
.struct-tab         { padding:var(--sp-2) var(--sp-4); text-decoration:none; color:var(--color-text-muted); border-bottom:2px solid transparent; margin-bottom:-2px; font-weight:500; }
.struct-tab--active { color:var(--color-primary); border-bottom-color:var(--color-primary); }
.struct-tab:hover   { color:var(--color-primary); }

.struct-container   { overflow-x:auto; margin-top:var(--sp-3); }
.struct-table       { width:100%; border-collapse:collapse; font-size:0.9rem; }
.struct-th          { text-align:left; padding:var(--sp-2) var(--sp-3); background:var(--color-surface); border-bottom:2px solid var(--color-border); font-weight:600; white-space:nowrap; }
.struct-td          { padding:var(--sp-2) var(--sp-3); border-bottom:1px solid var(--color-border); vertical-align:middle; }
.struct-row:hover   { background:var(--color-surface); }

.struct-row--epic    > .struct-td--title { font-weight:700; }
.struct-row--feature > .struct-td--title { font-weight:600; }

.struct-title-cell  { display:flex; align-items:center; gap:var(--sp-2); min-width:200px; }
.struct-toggle      { background:none; border:none; cursor:pointer; padding:0; width:1.2rem; text-align:center; color:var(--color-text-muted); font-size:0.75rem; flex-shrink:0; }
.struct-toggle:hover { color:var(--color-primary); }
.struct-toggle-placeholder { display:inline-block; width:1.2rem; flex-shrink:0; }

.struct-task-link   { color:var(--color-text); text-decoration:none; }
.struct-task-link:hover { text-decoration:underline; color:var(--color-primary); }

/* Type badges */
.struct-type        { display:inline-block; padding:2px 7px; border-radius:3px; font-size:0.72em; font-weight:700; letter-spacing:0.03em; flex-shrink:0; text-transform:uppercase; }
.struct-type--epic    { background:#6f42c1; color:#fff; }
.struct-type--feature { background:#0d6efd; color:#fff; }
.struct-type--task    { background:#6c757d; color:#fff; }

/* Rollup */
.struct-rollup      { display:inline-flex; align-items:center; gap:var(--sp-2); white-space:nowrap; }
.struct-rollup-bar  { display:inline-block; width:60px; height:7px; background:var(--color-border); border-radius:4px; overflow:hidden; }
.struct-rollup-fill { display:block; height:100%; background:var(--color-success, #28a745); border-radius:4px; }
.struct-rollup-pct  { font-size:0.8em; color:var(--color-text-muted); min-width:2.5rem; }
.struct-rollup--empty { color:var(--color-text-muted); font-size:0.8em; }

/* Bulk bar */
.struct-bulk-bar    { position:sticky; top:0; z-index:10; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:var(--sp-2) var(--sp-3); display:flex; align-items:center; gap:var(--sp-2); flex-wrap:wrap; margin-bottom:var(--sp-2); }
.struct-bulk-label  { font-weight:600; white-space:nowrap; }

/* Actions */
.struct-action-row  { display:flex; align-items:center; gap:var(--sp-1); }
.struct-parent-form { display:flex; }
.btn-icon           { background:none; border:1px solid var(--color-border); border-radius:4px; cursor:pointer; padding:2px 6px; font-size:1rem; color:var(--color-text-muted); }
.btn-icon:hover     { background:var(--color-border); color:var(--color-text); }
.form-input--inline { height:28px; padding:2px 6px; font-size:0.82rem; border-radius:4px; }

/* Collapsed rows */
.struct-row--hidden { display:none; }

/* Legend */
.struct-legend .struct-type { margin:0; }

/* Depth-specific left border colors */
.struct-row--epic > .struct-td:first-child    { border-left:3px solid #6f42c1; }
.struct-row--feature > .struct-td:first-child { border-left:3px solid #0d6efd; }

/* Compact tags */
.tag-list--compact  { display:flex; flex-wrap:wrap; gap:3px; }
.tag-chip--sm       { font-size:0.72em; padding:1px 5px; }
</style>

<script>
(function () {
    // ── Collapse/expand ───────────────────────────────────────
    document.querySelectorAll('.struct-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target   = btn.getAttribute('data-target');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            var row      = btn.closest('tr');
            var depth    = parseInt(row.getAttribute('data-depth') || '0', 10);
            var id       = parseInt(row.getAttribute('data-id') || '0', 10);

            if (expanded) {
                // Collapse: hide all descendants
                hideDescendants(row);
                btn.setAttribute('aria-expanded', 'false');
                btn.querySelector('.struct-toggle-icon').innerHTML = '&#9658;';
            } else {
                // Expand direct children only (they expand their own children)
                showDirectChildren(row);
                btn.setAttribute('aria-expanded', 'true');
                btn.querySelector('.struct-toggle-icon').innerHTML = '&#9660;';
            }
        });
    });

    function getDepth(row) {
        return parseInt(row.getAttribute('data-depth') || '0', 10);
    }

    function hideDescendants(parentRow) {
        var depth  = getDepth(parentRow);
        var next   = parentRow.nextElementSibling;
        while (next && getDepth(next) > depth) {
            next.classList.add('struct-row--hidden');
            // Also mark toggles as collapsed
            var toggleBtn = next.querySelector('.struct-toggle');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.querySelector('.struct-toggle-icon').innerHTML = '&#9658;';
            }
            next = next.nextElementSibling;
        }
    }

    function showDirectChildren(parentRow) {
        var depth = getDepth(parentRow);
        var next  = parentRow.nextElementSibling;
        while (next && getDepth(next) > depth) {
            if (getDepth(next) === depth + 1) {
                next.classList.remove('struct-row--hidden');
            }
            next = next.nextElementSibling;
        }
    }

    // ── Select all ────────────────────────────────────────────
    var selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.struct-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkBar();
        });
    }

    document.querySelectorAll('.struct-check').forEach(function (cb) {
        cb.addEventListener('change', updateBulkBar);
    });

    function updateBulkBar() {
        var checked = document.querySelectorAll('.struct-check:checked');
        var bar     = document.getElementById('bulk-bar');
        var label   = document.getElementById('bulk-count-label');
        if (bar) {
            bar.style.display = checked.length > 0 ? 'flex' : 'none';
        }
        if (label) {
            label.textContent = checked.length + ' <?= addslashes(t('structure.bulk.selected')) ?>';
        }
    }

    // ── Bulk action sub-fields ────────────────────────────────
    var actionSelect = document.getElementById('bulk-action-select');
    if (actionSelect) {
        actionSelect.addEventListener('change', function () {
            document.querySelectorAll('.struct-bulk-field').forEach(function (f) {
                f.style.display = 'none';
            });
            var val = actionSelect.value;
            if (val) {
                var field = document.getElementById('bulk-field-' + val);
                if (field) field.style.display = 'inline-flex';
            }
        });
    }

    // ── Bulk cancel ───────────────────────────────────────────
    var cancelBtn = document.getElementById('bulk-cancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            document.querySelectorAll('.struct-check').forEach(function (cb) { cb.checked = false; });
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    }

    // ── Persist collapse state in sessionStorage ───────────────
    try {
        document.querySelectorAll('.struct-toggle').forEach(function (btn) {
            var row = btn.closest('tr');
            var id  = row ? row.getAttribute('data-id') : null;
            if (!id) return;
            var key = 'struct-col-' + <?= $boardId ?> + '-' + id;
            var stored = sessionStorage.getItem(key);
            if (stored === 'collapsed') {
                // trigger collapse on load
                btn.click();
            }
            btn.addEventListener('click', function () {
                sessionStorage.setItem(key, btn.getAttribute('aria-expanded') === 'true' ? 'expanded' : 'collapsed');
            });
        });
    } catch (e) {}
}());
</script>
