<?php
/**
 * Board view - Kanban board with drag & drop (AP6 / AP13).
 *
 * Variables:
 *   $boardColumns   - array of board_columns rows, ordered by position
 *   $tasksByColumn  - array keyed by column_id, each containing task rows
 *   $users          - array for owner filter dropdown
 *   $allTags        - array of tag rows for filter dropdown
 *   $filters        - currently active filters from GET
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Authz::can(Authz::BOARD_MOVE);
$canManage = Authz::can(Authz::BOARD_COLUMNS_MANAGE);

// Current filter values for preserving state
$fOwner = $_GET['owner_id'] ?? '';
$fTag   = $_GET['tag'] ?? '';
$fDue   = $_GET['due'] ?? '';
$fQ     = $_GET['q'] ?? '';
?>

<!-- Board header -->
<div class="board-header">
    <div class="board-header-row">
        <h1>Board</h1>
        <?php if ($canManage): ?>
            <a href="<?= $esc($baseUrl) ?>/?r=board_columns" class="btn btn-secondary">Spalten verwalten</a>
        <?php endif; ?>
    </div>

    <!-- Filter bar -->
    <form class="board-filter-form" method="get" action="<?= $esc($baseUrl) ?>/">
        <input type="hidden" name="r" value="board">
        <div class="filter-row">
            <div class="filter-group">
                <label for="bf-owner">Owner</label>
                <select id="bf-owner" name="owner_id" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (int) $fOwner === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= $esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-tag">Tag</label>
                <select id="bf-tag" name="tag" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <?php foreach ($allTags as $tag): ?>
                        <option value="<?= $esc($tag['name']) ?>"
                            <?= $fTag === $tag['name'] ? 'selected' : '' ?>>
                            <?= $esc($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-due">Faellig</label>
                <select id="bf-due" name="due" class="form-input form-input-sm">
                    <option value="">Alle</option>
                    <option value="overdue" <?= $fDue === 'overdue' ? 'selected' : '' ?>>Ueberfaellig</option>
                    <option value="today"   <?= $fDue === 'today'   ? 'selected' : '' ?>>Heute</option>
                    <option value="week"    <?= $fDue === 'week'    ? 'selected' : '' ?>>Diese Woche</option>
                    <option value="none"    <?= $fDue === 'none'    ? 'selected' : '' ?>>Kein Datum</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-q">Suche</label>
                <input id="bf-q" type="text" name="q" value="<?= $esc($fQ) ?>"
                       class="form-input form-input-sm" placeholder="Titel...">
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad">Filtern</button>
                <?php if ($fOwner !== '' || $fTag !== '' || $fDue !== '' || $fQ !== ''): ?>
                    <a href="<?= $esc($baseUrl) ?>/?r=board" class="btn btn-secondary btn-sm-pad">Zuruecksetzen</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Mobile Kanban Tabs (AP12 / AP13) -->
<div class="kanban-tabs" id="kanban-tabs">
    <?php foreach ($boardColumns as $idx => $col):
        $colId = (int) $col['id'];
        $count = count($tasksByColumn[$colId] ?? []);
    ?>
        <button type="button" class="kanban-tab<?= $idx === 0 ? ' active' : '' ?>"
                data-column-id="<?= $colId ?>">
            <?= $esc($col['name']) ?>
            <span class="kanban-tab-count"><?= $count ?></span>
        </button>
    <?php endforeach; ?>
</div>

<!-- Kanban Board -->
<div class="kanban-board" id="kanban-board">
    <?php foreach ($boardColumns as $idx => $col):
        $colId      = (int) $col['id'];
        $colName    = $col['name'];
        $colSlug    = $col['slug'];
        $colColor   = $col['color'] ?? '';
        $wipLimit   = $col['wip_limit'];
        $tasks      = $tasksByColumn[$colId] ?? [];
        $count      = count($tasks);
        $wipExceeded = ($wipLimit !== null && $count > (int) $wipLimit);
    ?>
        <div class="kanban-column<?= $idx === 0 ? ' active' : '' ?> <?= $wipExceeded ? 'kanban-column-wip-exceeded' : '' ?>"
             data-column-id="<?= $colId ?>">
            <div class="kanban-column-header"
                 <?php if ($colColor): ?>style="border-top: 3px solid <?= $esc($colColor) ?>;"<?php endif; ?>>
                <span class="kanban-column-title">
                    <strong><?= $esc($colName) ?></strong>
                </span>
                <span class="kanban-column-count <?= $wipExceeded ? 'kanban-wip-warning' : '' ?>">
                    <?= $count ?><?php if ($wipLimit !== null): ?>/<?= (int) $wipLimit ?><?php endif; ?>
                </span>
            </div>
            <div class="kanban-column-body" data-column-id="<?= $colId ?>">
                <?php foreach ($tasks as $task):
                    $taskId   = (int) $task['id'];
                    $tags     = $task['tag_list'] ? explode(',', $task['tag_list']) : [];
                    $dueDate  = $task['due_date'] ?? null;
                    $isDoneCol = ($colSlug === 'done');
                    $isOverdue = ($dueDate && $dueDate < date('Y-m-d') && !$isDoneCol);
                ?>
                    <div class="kanban-card" draggable="<?= $canEdit ? 'true' : 'false' ?>"
                         data-task-id="<?= $taskId ?>"
                         data-column-id="<?= $colId ?>">
                        <a href="<?= $esc($baseUrl) ?>/?r=task_view&amp;id=<?= $taskId ?>" class="kanban-card-title">
                            <?= $esc($task['title']) ?>
                        </a>
                        <div class="kanban-card-meta">
                            <?php if ($task['owner_name']): ?>
                                <span class="kanban-card-owner" title="<?= $esc($task['owner_name']) ?>">
                                    <?= $esc(mb_substr($task['owner_name'], 0, 2, 'UTF-8')) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($dueDate): ?>
                                <span class="kanban-card-due <?= $isOverdue ? 'text-overdue' : '' ?>">
                                    <?= $esc(date('d.m.Y', strtotime($dueDate))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tags)): ?>
                            <div class="kanban-card-tags">
                                <?php foreach ($tags as $tagName): ?>
                                    <span class="tag-chip"><?= $esc(trim($tagName)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                            <div class="kanban-card-actions">
                                <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_move" class="status-change-form kanban-move-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="task_id" value="<?= $taskId ?>">
                                    <input type="hidden" name="_filter_owner_id" value="<?= $esc($fOwner) ?>">
                                    <input type="hidden" name="_filter_tag" value="<?= $esc($fTag) ?>">
                                    <input type="hidden" name="_filter_due" value="<?= $esc($fDue) ?>">
                                    <input type="hidden" name="_filter_q" value="<?= $esc($fQ) ?>">
                                    <select name="new_column_id" class="status-select"
                                            onchange="this.form.submit()">
                                        <?php foreach ($boardColumns as $optCol): ?>
                                            <option value="<?= (int) $optCol['id'] ?>"
                                                <?= (int) $optCol['id'] === $colId ? 'selected' : '' ?>>
                                                <?= $esc($optCol['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($canEdit): ?>
<!-- Drag & Drop JavaScript (vanilla, no dependencies) -->
<script>
(function() {
    'use strict';

    var board      = document.getElementById('kanban-board');
    var csrfToken  = <?= json_encode(Security::csrfToken()) ?>;
    var baseUrl    = <?= json_encode($baseUrl) ?>;
    var dragCard   = null;
    var placeholder = null;

    function createPlaceholder() {
        var el = document.createElement('div');
        el.className = 'kanban-card kanban-placeholder';
        return el;
    }

    function getDropTarget(el) {
        while (el && el !== board) {
            if (el.classList.contains('kanban-card') && el !== placeholder) return { type: 'card', el: el };
            if (el.classList.contains('kanban-column-body')) return { type: 'column', el: el };
            el = el.parentElement;
        }
        return null;
    }

    board.addEventListener('dragstart', function(e) {
        var card = e.target.closest('.kanban-card');
        if (!card || card.getAttribute('draggable') !== 'true') return;

        dragCard = card;
        placeholder = createPlaceholder();

        setTimeout(function() {
            dragCard.classList.add('kanban-card-dragging');
        }, 0);

        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.taskId);
    });

    board.addEventListener('dragover', function(e) {
        if (!dragCard) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        var target = getDropTarget(e.target);
        if (!target) return;

        if (target.type === 'card') {
            var rect = target.el.getBoundingClientRect();
            var midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                target.el.parentNode.insertBefore(placeholder, target.el);
            } else {
                target.el.parentNode.insertBefore(placeholder, target.el.nextSibling);
            }
        } else if (target.type === 'column') {
            if (!target.el.contains(placeholder)) {
                target.el.appendChild(placeholder);
            }
        }
    });

    board.addEventListener('dragend', function(e) {
        if (dragCard) {
            dragCard.classList.remove('kanban-card-dragging');
        }
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.removeChild(placeholder);
        }
        dragCard = null;
        placeholder = null;
    });

    board.addEventListener('drop', function(e) {
        e.preventDefault();
        if (!dragCard || !placeholder || !placeholder.parentNode) return;

        var columnBody = placeholder.closest('.kanban-column-body');
        if (!columnBody) return;

        var newColumnId = columnBody.dataset.columnId;
        var taskId      = dragCard.dataset.taskId;

        var prev = placeholder.previousElementSibling;
        var next = placeholder.nextElementSibling;

        while (prev && (prev === dragCard || prev.classList.contains('kanban-placeholder'))) {
            prev = prev.previousElementSibling;
        }
        while (next && (next === dragCard || next.classList.contains('kanban-placeholder'))) {
            next = next.nextElementSibling;
        }

        var afterId  = prev ? prev.dataset.taskId : '';
        var beforeId = next ? next.dataset.taskId : '';

        placeholder.parentNode.insertBefore(dragCard, placeholder);
        placeholder.parentNode.removeChild(placeholder);
        dragCard.classList.remove('kanban-card-dragging');
        dragCard.dataset.columnId = newColumnId;

        var select = dragCard.querySelector('select[name="new_column_id"]');
        if (select) {
            select.value = newColumnId;
        }

        updateColumnCounts();

        var formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('task_id', taskId);
        formData.append('new_column_id', newColumnId);
        if (afterId)  formData.append('after_id', afterId);
        if (beforeId) formData.append('before_id', beforeId);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', baseUrl + '/?r=board_move', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status !== 200) {
                window.location.reload();
            }
        };
        xhr.onerror = function() {
            window.location.reload();
        };
        xhr.send(formData);

        dragCard = null;
        placeholder = null;
    });

    function updateColumnCounts() {
        var cols = board.querySelectorAll('.kanban-column');
        for (var i = 0; i < cols.length; i++) {
            var body  = cols[i].querySelector('.kanban-column-body');
            var count = cols[i].querySelector('.kanban-column-count');
            if (body && count) {
                var cards = body.querySelectorAll('.kanban-card:not(.kanban-placeholder):not(.kanban-card-dragging)');
                var num = cards.length;
                var wipMatch = count.textContent.match(/\/(\d+)/);
                if (wipMatch) {
                    count.textContent = num + '/' + wipMatch[1];
                } else {
                    count.textContent = num;
                }
            }
            var columnId = cols[i].dataset.columnId;
            var tabCount = document.querySelector('.kanban-tab[data-column-id="' + columnId + '"] .kanban-tab-count');
            if (tabCount && body) {
                var tabCards = body.querySelectorAll('.kanban-card:not(.kanban-placeholder):not(.kanban-card-dragging)');
                tabCount.textContent = tabCards.length;
            }
        }
    }
})();
</script>
<?php endif; ?>

<!-- Mobile Tab Switching (AP12 / AP13) -->
<script>
(function() {
    'use strict';

    var tabs = document.getElementById('kanban-tabs');
    var board = document.getElementById('kanban-board');
    if (!tabs || !board) return;

    var savedTab = null;
    try { savedTab = sessionStorage.getItem('wp-board-tab'); } catch(e) {}

    function activateTab(columnId) {
        var allTabs = tabs.querySelectorAll('.kanban-tab');
        for (var i = 0; i < allTabs.length; i++) {
            if (allTabs[i].dataset.columnId === columnId) {
                allTabs[i].classList.add('active');
            } else {
                allTabs[i].classList.remove('active');
            }
        }
        var columns = board.querySelectorAll('.kanban-column');
        for (var i = 0; i < columns.length; i++) {
            if (columns[i].dataset.columnId === columnId) {
                columns[i].classList.add('active');
            } else {
                columns[i].classList.remove('active');
            }
        }
        try { sessionStorage.setItem('wp-board-tab', columnId); } catch(e) {}
    }

    tabs.addEventListener('click', function(e) {
        var tab = e.target.closest('.kanban-tab');
        if (!tab) return;
        activateTab(tab.dataset.columnId);
    });

    if (savedTab && window.innerWidth <= 768) {
        activateTab(savedTab);
    }
})();
</script>
