<?php
/**
 * Board view - Kanban board with drag & drop (AP6 / AP13 / AP21 / AP22).
 *
 * Variables:
 *   $board          - array: current board row
 *   $boardColumns   - array of board_columns rows, ordered by position
 *   $tasksByColumn  - array keyed by column_id, each containing task rows
 *   $users          - array for owner filter dropdown
 *   $allTags        - array of tag rows for filter dropdown
 *   $filters        - currently active filters from GET
 *   $canEdit        - bool: can move tasks
 *   $canQuickAdd    - bool: can create tasks directly
 *   $canManage      - bool: can manage (edit/delete) this board
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];

// Current filter values for preserving state
$fOwner = $_GET['owner_id'] ?? '';
$fTag   = $_GET['tag'] ?? '';
$fDue   = $_GET['due'] ?? '';
$fQ     = $_GET['q'] ?? '';

// AP22: Available boards for move-to-board
$__availableBoards = [];
if ($canEdit) {
    $__userId     = (int) $_SESSION['user_id'];
    $__globalRole = $_SESSION['user_role'] ?? 'viewer';
    $__availableBoards = Board::allVisibleTo($__userId, $__globalRole);
}
?>

<!-- Board header -->
<div class="board-header">
    <div class="board-header-row">
        <div style="display:flex; align-items:center; gap:var(--sp-3);">
            <a href="<?= $esc($baseUrl) ?>/?r=boards" class="btn btn-secondary btn-sm-pad" title="<?= $esc(t('board.all_boards')) ?>">&larr;</a>
            <h1><?= $esc($board['name']) ?></h1>
            <?php if ($board['team_name'] ?? ''): ?>
                <span class="status-badge"><?= $esc($board['team_name']) ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:var(--sp-2);">
            <!-- AP25: Structure View link -->
            <a href="<?= $esc($baseUrl) ?>/?r=structure&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('structure.tab_structure')) ?></a>
            <!-- AP26: Sprints link -->
            <a href="<?= $esc($baseUrl) ?>/?r=sprints&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('sprint.sprints')) ?></a>
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-secondary btn-sm-pad" onclick="document.getElementById('board-edit-panel').style.display = document.getElementById('board-edit-panel').style.display === 'none' ? 'block' : 'none'"><?= $esc(t('actions.edit')) ?></button>
                <a href="<?= $esc($baseUrl) ?>/?r=board_columns" class="btn btn-secondary btn-sm-pad"><?= $esc(t('board.columns')) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($board['description'] ?? ''): ?>
        <p class="text-muted" style="margin-top:var(--sp-1);"><?= $esc($board['description']) ?></p>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <!-- Board edit/delete panel -->
    <div id="board-edit-panel" style="display:none; margin-top:var(--sp-3); padding:var(--sp-3); background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md);">
        <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_edit" style="margin-bottom:var(--sp-3);">
            <?= Security::csrfField() ?>
            <input type="hidden" name="id" value="<?= $boardId ?>">
            <div class="form-group">
                <label class="form-label"><?= $esc(t('labels.name')) ?></label>
                <input type="text" name="name" class="form-input" value="<?= $esc($board['name']) ?>" required maxlength="150">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $esc(t('labels.description')) ?></label>
                <input type="text" name="description" class="form-input" value="<?= $esc($board['description'] ?? '') ?>" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary btn-sm-pad"><?= $esc(t('actions.save')) ?></button>
        </form>
        <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_delete" onsubmit="return confirm('<?= $esc(t('messages.confirm_delete_board')) ?>');">
            <?= Security::csrfField() ?>
            <input type="hidden" name="id" value="<?= $boardId ?>">
            <button type="submit" class="btn btn-danger btn-sm-pad"><?= $esc(t('board.delete_button')) ?></button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <form class="board-filter-form" method="get" action="<?= $esc($baseUrl) ?>/">
        <input type="hidden" name="r" value="board_view">
        <input type="hidden" name="id" value="<?= $boardId ?>">
        <div class="filter-row">
            <div class="filter-group">
                <label for="bf-owner"><?= $esc(t('labels.owner')) ?></label>
                <select id="bf-owner" name="owner_id" class="form-input form-input-sm">
                    <option value=""><?= $esc(t('labels.all')) ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (int) $fOwner === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= $esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-tag"><?= $esc(t('labels.tags')) ?></label>
                <select id="bf-tag" name="tag" class="form-input form-input-sm">
                    <option value=""><?= $esc(t('labels.all')) ?></option>
                    <?php foreach ($allTags as $tag): ?>
                        <option value="<?= $esc($tag['name']) ?>"
                            <?= $fTag === $tag['name'] ? 'selected' : '' ?>>
                            <?= $esc($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-due"><?= $esc(t('board.filter_due')) ?></label>
                <select id="bf-due" name="due" class="form-input form-input-sm">
                    <option value=""><?= $esc(t('labels.all')) ?></option>
                    <option value="overdue" <?= $fDue === 'overdue' ? 'selected' : '' ?>><?= $esc(t('board.filter_overdue')) ?></option>
                    <option value="today"   <?= $fDue === 'today'   ? 'selected' : '' ?>><?= $esc(t('board.filter_today')) ?></option>
                    <option value="week"    <?= $fDue === 'week'    ? 'selected' : '' ?>><?= $esc(t('board.filter_week')) ?></option>
                    <option value="none"    <?= $fDue === 'none'    ? 'selected' : '' ?>><?= $esc(t('board.filter_no_date')) ?></option>
                </select>
            </div>
            <div class="filter-group">
                <label for="bf-q"><?= $esc(t('board.filter_search')) ?></label>
                <input id="bf-q" type="text" name="q" value="<?= $esc($fQ) ?>"
                       class="form-input form-input-sm" placeholder="<?= $esc(t('placeholders.title_input')) ?>">
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= $esc(t('actions.filter')) ?></button>
                <?php if ($fOwner !== '' || $fTag !== '' || $fDue !== '' || $fQ !== ''): ?>
                    <a href="<?= $esc($baseUrl) ?>/?r=board_view&amp;id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('actions.reset')) ?></a>
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
        $colColor = $col['color'] ?? '';
    ?>
        <button type="button" class="kanban-tab<?= $idx === 0 ? ' active' : '' ?>"
                data-column-id="<?= $colId ?>"
                <?php if ($colColor): ?>style="border-bottom-color: <?= $esc($colColor) ?>;"<?php endif; ?>>
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
        $colCategory = $col['category'] ?? 'active';
        $wipLimit   = $col['wip_limit'];
        $tasks      = $tasksByColumn[$colId] ?? [];
        $count      = count($tasks);
        $wipExceeded = ($wipLimit !== null && $count > (int) $wipLimit);
        $isDoneColumn = ($colCategory === 'done');
    ?>
        <div class="kanban-column<?= $idx === 0 ? ' active' : '' ?> <?= $wipExceeded ? 'kanban-column-wip-exceeded' : '' ?>"
             data-column-id="<?= $colId ?>"
             data-column-category="<?= $esc($colCategory) ?>">
            <!-- AP22: Enhanced column header with color background -->
            <div class="kanban-column-header"
                 <?php if ($colColor): ?>style="background: <?= $esc($colColor) ?>14; border-top: 3px solid <?= $esc($colColor) ?>;"<?php endif; ?>>
                <span class="kanban-column-title">
                    <?php if ($colColor): ?>
                        <span class="kanban-column-dot" style="background: <?= $esc($colColor) ?>;"></span>
                    <?php endif; ?>
                    <strong><?= $esc($colName) ?></strong>
                    <?php if ($isDoneColumn): ?>
                        <span class="text-muted" style="font-size:0.75rem; font-weight:normal;"> <?= $esc(t('board.done')) ?></span>
                    <?php endif; ?>
                </span>
                <span class="kanban-column-count <?= $wipExceeded ? 'kanban-wip-warning' : '' ?>">
                    <?= $count ?><?php if ($wipLimit !== null): ?>/<?= (int) $wipLimit ?><?php endif; ?>
                </span>
            </div>

            <?php if ($canQuickAdd): ?>
            <!-- AP21: Quick Add -->
            <div class="kanban-quick-add">
                <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_quick_add" class="kanban-quick-add-form" data-board-id="<?= $boardId ?>" data-column-id="<?= $colId ?>">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                    <input type="hidden" name="column_id" value="<?= $colId ?>">
                    <input type="text" name="title" class="kanban-quick-add-input" placeholder="<?= $esc(t('placeholders.add_task')) ?>" maxlength="190" autocomplete="off">
                </form>
            </div>
            <?php endif; ?>

            <div class="kanban-column-body" data-column-id="<?= $colId ?>">
                <?php foreach ($tasks as $task):
                    $taskId   = (int) $task['id'];
                    $tags     = $task['tag_list'] ? explode(',', $task['tag_list']) : [];
                    $dueDate  = $task['due_date'] ?? null;
                    $isOverdue = ($dueDate && $dueDate < date('Y-m-d') && !$isDoneColumn);
                ?>
                    <div class="kanban-card<?= $isDoneColumn ? ' kanban-card-done' : '' ?>" draggable="<?= $canEdit ? 'true' : 'false' ?>"
                         data-task-id="<?= $taskId ?>"
                         data-column-id="<?= $colId ?>">
                        <!-- AP22: Column color left border -->
                        <?php if ($colColor): ?>
                        <span class="kanban-card-color-bar" style="background: <?= $esc($colColor) ?>;"></span>
                        <?php endif; ?>
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
                                    <input type="hidden" name="board_id" value="<?= $boardId ?>">
                                    <input type="hidden" name="_filter_owner_id" value="<?= $esc($fOwner) ?>">
                                    <input type="hidden" name="_filter_tag" value="<?= $esc($fTag) ?>">
                                    <input type="hidden" name="_filter_due" value="<?= $esc($fDue) ?>">
                                    <input type="hidden" name="_filter_q" value="<?= $esc($fQ) ?>">
                                    <select name="new_column_id" class="kanban-card-select"
                                            onchange="this.form.submit()">
                                        <?php foreach ($boardColumns as $optCol): ?>
                                            <option value="<?= (int) $optCol['id'] ?>"
                                                <?= (int) $optCol['id'] === $colId ? 'selected' : '' ?>>
                                                <?= $esc($optCol['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php if (count($__availableBoards) > 1): ?>
                                <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_move_task_board" class="inline-form kanban-move-board-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="task_id" value="<?= $taskId ?>">
                                    <select name="target_board_id" class="kanban-card-select kanban-card-select-board"
                                            onchange="if(this.value)this.form.submit()">
                                        <option value=""><?= $esc(t('board.select_board')) ?></option>
                                        <?php foreach ($__availableBoards as $ab): ?>
                                            <?php if ((int) $ab['id'] !== $boardId): ?>
                                            <option value="<?= (int) $ab['id'] ?>"><?= $esc($ab['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php endif; ?>
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
    var boardId    = <?= json_encode($boardId) ?>;
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
        var oldColumnId = dragCard.dataset.columnId;

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

        // AP22: Confetti when task moves to done column
        if (oldColumnId !== newColumnId) {
            var targetCol = columnBody.closest('.kanban-column');
            if (targetCol && targetCol.dataset.columnCategory === 'done') {
                triggerConfetti(dragCard);
            }
        }

        var formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('task_id', taskId);
        formData.append('new_column_id', newColumnId);
        formData.append('board_id', boardId);
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

    // AP22: Detect column dropdown change to done
    board.addEventListener('change', function(e) {
        if (e.target.name !== 'new_column_id') return;
        var newColId = e.target.value;
        var card = e.target.closest('.kanban-card');
        var cols = board.querySelectorAll('.kanban-column');
        for (var i = 0; i < cols.length; i++) {
            if (cols[i].dataset.columnId === newColId && cols[i].dataset.columnCategory === 'done') {
                if (card && card.dataset.columnId !== newColId) {
                    triggerConfetti(card);
                }
                break;
            }
        }
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

    // AP22: Confetti celebration (respects prefers-reduced-motion)
    function triggerConfetti(anchorEl) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var rect = anchorEl.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        var colors = ['#22c55e', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#ec4899'];
        var container = document.createElement('div');
        container.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;overflow:hidden;';
        document.body.appendChild(container);
        for (var i = 0; i < 30; i++) {
            var dot = document.createElement('div');
            var size = 4 + Math.random() * 6;
            var angle = Math.random() * Math.PI * 2;
            var velocity = 60 + Math.random() * 120;
            var dx = Math.cos(angle) * velocity;
            var dy = Math.sin(angle) * velocity - 40;
            dot.style.cssText = 'position:absolute;border-radius:50%;width:' + size + 'px;height:' + size + 'px;left:' + cx + 'px;top:' + cy + 'px;background:' + colors[i % colors.length] + ';opacity:1;';
            dot.dataset.dx = dx;
            dot.dataset.dy = dy;
            dot.dataset.ox = cx;
            dot.dataset.oy = cy;
            container.appendChild(dot);
        }
        var start = performance.now();
        var duration = 800;
        function anim(now) {
            var t = (now - start) / duration;
            if (t > 1) {
                container.remove();
                return;
            }
            var dots = container.children;
            for (var j = 0; j < dots.length; j++) {
                var d = dots[j];
                var x = parseFloat(d.dataset.ox) + parseFloat(d.dataset.dx) * t;
                var y = parseFloat(d.dataset.oy) + parseFloat(d.dataset.dy) * t + 200 * t * t;
                d.style.left = x + 'px';
                d.style.top = y + 'px';
                d.style.opacity = 1 - t;
            }
            requestAnimationFrame(anim);
        }
        requestAnimationFrame(anim);
    }
})();
</script>
<?php endif; ?>

<?php if ($canQuickAdd): ?>
<!-- AP21: Quick Add JavaScript -->
<script>
(function() {
    'use strict';

    var csrfToken = <?= json_encode(Security::csrfToken()) ?>;
    var baseUrl   = <?= json_encode($baseUrl) ?>;

    var forms = document.querySelectorAll('.kanban-quick-add-form');
    for (var i = 0; i < forms.length; i++) {
        forms[i].addEventListener('submit', function(e) {
            e.preventDefault();
            var form    = e.target;
            var input   = form.querySelector('.kanban-quick-add-input');
            var title   = input.value.trim();
            if (!title) return;

            var boardIdVal = form.dataset.boardId;
            var columnId   = form.dataset.columnId;

            var formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('board_id', boardIdVal);
            formData.append('column_id', columnId);
            formData.append('title', title);

            input.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', baseUrl + '/?r=board_quick_add', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                input.disabled = false;
                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.ok) {
                            var columnBody = form.closest('.kanban-column').querySelector('.kanban-column-body');
                            var card = document.createElement('div');
                            card.className = 'kanban-card';
                            card.dataset.taskId = resp.task_id;
                            card.dataset.columnId = columnId;
                            card.innerHTML = '<a href="' + baseUrl + '/?r=task_view&id=' + resp.task_id + '" class="kanban-card-title">' + escHtml(resp.title) + '</a>';
                            if (resp.owner_name) {
                                card.innerHTML += '<div class="kanban-card-meta"><span class="kanban-card-owner" title="' + escHtml(resp.owner_name) + '">' + escHtml(resp.owner_name.substring(0,2)) + '</span></div>';
                            }
                            columnBody.appendChild(card);
                            input.value = '';

                            var countEl = form.closest('.kanban-column').querySelector('.kanban-column-count');
                            if (countEl) {
                                var wipMatch = countEl.textContent.match(/\/(\d+)/);
                                var num = columnBody.querySelectorAll('.kanban-card').length;
                                countEl.textContent = wipMatch ? num + '/' + wipMatch[1] : num;
                            }
                        }
                    } catch(ex) {
                        window.location.reload();
                    }
                } else {
                    window.location.reload();
                }
            };
            xhr.onerror = function() {
                input.disabled = false;
                window.location.reload();
            };
            xhr.send(formData);
        });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
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
