<?php
/**
 * Burndown Chart view (AP26).
 *
 * Variables:
 *   $sprint       - sprint row
 *   $board        - board row
 *   $burndownData - ['dates' => [], 'ideal' => [], 'actual' => [], 'commitment' => int]
 *   $tasks        - sprint tasks with column info
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];
$sprintId = (int) $sprint['id'];

$dates      = $burndownData['dates'];
$ideal      = $burndownData['ideal'];
$actual     = $burndownData['actual'];
$commitment = $burndownData['commitment'];
?>

<div class="content-header">
    <div style="display:flex; align-items:center; gap:var(--sp-3);">
        <a href="<?= $esc($baseUrl) ?>/?r=sprints&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad">&larr;</a>
        <h1><?= $esc(t('sprint.burndown')) ?> &mdash; <?= $esc($sprint['name']) ?></h1>
    </div>
</div>

<!-- Sprint Summary -->
<div class="card" style="margin-bottom:var(--sp-4);">
    <div class="card-body">
        <div style="display:flex; gap:var(--sp-4); flex-wrap:wrap;">
            <div><span class="text-muted"><?= $esc(t('labels.status')) ?>:</span>
                <span class="status-badge"><?= $esc(t('sprint.status.' . $sprint['status'])) ?></span>
            </div>
            <div><span class="text-muted"><?= $esc(t('sprint.start_date')) ?>:</span> <?= $esc($sprint['start_date']) ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.end_date')) ?>:</span> <?= $esc($sprint['end_date']) ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.commitment')) ?>:</span> <?= $commitment ?> <?= $esc(t('sprint.tasks_unit')) ?></div>
        </div>
    </div>
</div>

<?php if (empty($dates)): ?>
<div class="empty-state">
    <p><?= $esc(t('sprint.no_data')) ?></p>
</div>
<?php else: ?>
<!-- Burndown Chart (Canvas) -->
<div class="card" style="margin-bottom:var(--sp-4);">
    <div class="card-body">
        <div style="position:relative; width:100%; max-width:900px; margin:0 auto;">
            <canvas id="burndown-chart" width="900" height="400" style="width:100%; height:auto;"></canvas>
        </div>
        <div style="display:flex; gap:var(--sp-4); justify-content:center; margin-top:var(--sp-3);">
            <div style="display:flex; align-items:center; gap:var(--sp-1);">
                <span style="display:inline-block; width:20px; height:3px; background:#94a3b8;"></span>
                <span class="text-muted" style="font-size:0.85rem;"><?= $esc(t('sprint.ideal_line')) ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:var(--sp-1);">
                <span style="display:inline-block; width:20px; height:3px; background:var(--color-primary, #3b82f6);"></span>
                <span class="text-muted" style="font-size:0.85rem;"><?= $esc(t('sprint.actual_line')) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sprint Tasks -->
<?php if (!empty($tasks)): ?>
<h2 style="margin-bottom:var(--sp-3);"><?= $esc(t('sprint.sprint_tasks')) ?></h2>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $esc(t('labels.title')) ?></th>
                <th><?= $esc(t('labels.column')) ?></th>
                <th><?= $esc(t('labels.owner')) ?></th>
                <th><?= $esc(t('labels.status')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $task): ?>
            <tr>
                <td>
                    <a href="<?= $esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $task['id'] ?>">
                        <?= $esc($task['title']) ?>
                    </a>
                </td>
                <td><?= $esc($task['column_name'] ?? '-') ?></td>
                <td><?= $esc($task['owner_name'] ?? t('tasks.not_assigned')) ?></td>
                <td>
                    <?php if (($task['column_category'] ?? '') === 'done'): ?>
                        <span class="status-badge" style="background:var(--color-success); color:#fff;"><?= $esc(t('board.done')) ?></span>
                    <?php else: ?>
                        <span class="status-badge"><?= $esc($task['column_name'] ?? '-') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($dates)): ?>
<script>
(function() {
    'use strict';

    var canvas = document.getElementById('burndown-chart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    var dates  = <?= json_encode($dates) ?>;
    var ideal  = <?= json_encode($ideal) ?>;
    var actual = <?= json_encode($actual) ?>;

    // High DPI support
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    var W = rect.width;
    var H = rect.height;

    // Chart dimensions
    var pad = { top: 20, right: 20, bottom: 60, left: 50 };
    var cW = W - pad.left - pad.right;
    var cH = H - pad.top - pad.bottom;

    // Detect dark mode
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#94a3b8' : '#64748b';
    var gridColor = isDark ? '#334155' : '#e2e8f0';
    var idealColor = '#94a3b8';
    var actualColor = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#3b82f6';

    // Find max value
    var maxVal = 0;
    for (var i = 0; i < ideal.length; i++) {
        if (ideal[i] > maxVal) maxVal = ideal[i];
    }
    for (var i = 0; i < actual.length; i++) {
        if (actual[i] !== null && actual[i] > maxVal) maxVal = actual[i];
    }
    maxVal = Math.max(maxVal, 1);
    // Round up to nice number
    maxVal = Math.ceil(maxVal * 1.1);

    function xPos(idx) { return pad.left + (cW / Math.max(dates.length - 1, 1)) * idx; }
    function yPos(val) { return pad.top + cH - (val / maxVal) * cH; }

    // Grid lines
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    var ySteps = 5;
    for (var s = 0; s <= ySteps; s++) {
        var yVal = (maxVal / ySteps) * s;
        var y = yPos(yVal);
        ctx.beginPath();
        ctx.moveTo(pad.left, y);
        ctx.lineTo(W - pad.right, y);
        ctx.stroke();

        ctx.fillStyle = textColor;
        ctx.font = '11px system-ui, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(yVal).toString(), pad.left - 8, y + 4);
    }

    // X-axis labels
    ctx.fillStyle = textColor;
    ctx.textAlign = 'center';
    ctx.font = '10px system-ui, sans-serif';
    var labelSkip = Math.max(1, Math.floor(dates.length / 12));
    for (var i = 0; i < dates.length; i++) {
        if (i % labelSkip === 0 || i === dates.length - 1) {
            var x = xPos(i);
            var parts = dates[i].split('-');
            ctx.save();
            ctx.translate(x, H - pad.bottom + 14);
            ctx.rotate(-Math.PI / 4);
            ctx.fillText(parts[2] + '.' + parts[1], 0, 0);
            ctx.restore();
        }
    }

    // Y-axis label
    ctx.save();
    ctx.translate(14, pad.top + cH / 2);
    ctx.rotate(-Math.PI / 2);
    ctx.fillStyle = textColor;
    ctx.textAlign = 'center';
    ctx.font = '12px system-ui, sans-serif';
    ctx.fillText('<?= $esc(t('sprint.remaining_tasks')) ?>', 0, 0);
    ctx.restore();

    // Ideal line (dashed)
    ctx.strokeStyle = idealColor;
    ctx.lineWidth = 2;
    ctx.setLineDash([6, 4]);
    ctx.beginPath();
    for (var i = 0; i < ideal.length; i++) {
        var x = xPos(i);
        var y = yPos(ideal[i]);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    }
    ctx.stroke();
    ctx.setLineDash([]);

    // Actual line (solid)
    ctx.strokeStyle = actualColor;
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    var started = false;
    for (var i = 0; i < actual.length; i++) {
        if (actual[i] === null) continue;
        var x = xPos(i);
        var y = yPos(actual[i]);
        if (!started) {
            ctx.moveTo(x, y);
            started = true;
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();

    // Data points on actual line
    ctx.fillStyle = actualColor;
    for (var i = 0; i < actual.length; i++) {
        if (actual[i] === null) continue;
        var x = xPos(i);
        var y = yPos(actual[i]);
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fill();
    }

    // Today marker
    var today = new Date().toISOString().slice(0, 10);
    var todayIdx = dates.indexOf(today);
    if (todayIdx >= 0) {
        var tx = xPos(todayIdx);
        ctx.strokeStyle = isDark ? '#f59e0b' : '#d97706';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 3]);
        ctx.beginPath();
        ctx.moveTo(tx, pad.top);
        ctx.lineTo(tx, pad.top + cH);
        ctx.stroke();
        ctx.setLineDash([]);

        ctx.fillStyle = isDark ? '#f59e0b' : '#d97706';
        ctx.font = '10px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('<?= $esc(t('sprint.today')) ?>', tx, pad.top - 5);
    }
})();
</script>
<?php endif; ?>
