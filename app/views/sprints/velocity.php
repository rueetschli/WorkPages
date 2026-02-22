<?php
/**
 * Velocity Chart view (AP26).
 *
 * Variables:
 *   $board        - current board
 *   $velocityData - ['sprints' => [...], 'average' => float]
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];

$sprints = $velocityData['sprints'];
$average = $velocityData['average'];
?>

<div class="content-header">
    <div style="display:flex; align-items:center; gap:var(--sp-3);">
        <a href="<?= $esc($baseUrl) ?>/?r=sprints&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad">&larr;</a>
        <h1><?= $esc(t('sprint.velocity')) ?> &mdash; <?= $esc($board['name']) ?></h1>
    </div>
</div>

<?php if (empty($sprints)): ?>
<div class="empty-state">
    <p><?= $esc(t('sprint.no_velocity_data')) ?></p>
</div>
<?php else: ?>

<!-- Summary -->
<div class="card" style="margin-bottom:var(--sp-4);">
    <div class="card-body">
        <div style="display:flex; gap:var(--sp-4); flex-wrap:wrap;">
            <div><span class="text-muted"><?= $esc(t('sprint.sprints_count')) ?>:</span> <?= count($sprints) ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.average_velocity')) ?>:</span> <strong><?= $esc($average) ?></strong> <?= $esc(t('sprint.tasks_per_sprint')) ?></div>
        </div>
    </div>
</div>

<!-- Velocity Chart (Canvas) -->
<div class="card" style="margin-bottom:var(--sp-4);">
    <div class="card-body">
        <div style="position:relative; width:100%; max-width:800px; margin:0 auto;">
            <canvas id="velocity-chart" width="800" height="400" style="width:100%; height:auto;"></canvas>
        </div>
        <div style="display:flex; gap:var(--sp-4); justify-content:center; margin-top:var(--sp-3);">
            <div style="display:flex; align-items:center; gap:var(--sp-1);">
                <span style="display:inline-block; width:16px; height:16px; background:var(--color-primary, #3b82f6); border-radius:2px;"></span>
                <span class="text-muted" style="font-size:0.85rem;"><?= $esc(t('sprint.completed_tasks')) ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:var(--sp-1);">
                <span style="display:inline-block; width:20px; height:2px; background:#f59e0b;"></span>
                <span class="text-muted" style="font-size:0.85rem;"><?= $esc(t('sprint.average_velocity')) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $esc(t('sprint.sprint_name')) ?></th>
                <th><?= $esc(t('sprint.start_date')) ?></th>
                <th><?= $esc(t('sprint.end_date')) ?></th>
                <th><?= $esc(t('sprint.completed_tasks')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sprints as $s): ?>
            <tr>
                <td>
                    <a href="<?= $esc($baseUrl) ?>/?r=sprint_burndown&amp;id=<?= (int) $s['id'] ?>">
                        <?= $esc($s['name']) ?>
                    </a>
                </td>
                <td><?= $esc($s['start_date']) ?></td>
                <td><?= $esc($s['end_date']) ?></td>
                <td><strong><?= (int) $s['completed'] ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    'use strict';

    var canvas = document.getElementById('velocity-chart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    var sprintNames = <?= json_encode(array_column($sprints, 'name')) ?>;
    var completed   = <?= json_encode(array_map('intval', array_column($sprints, 'completed'))) ?>;
    var avg         = <?= json_encode($average) ?>;

    // High DPI support
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    var W = rect.width;
    var H = rect.height;

    var pad = { top: 20, right: 20, bottom: 80, left: 50 };
    var cW = W - pad.left - pad.right;
    var cH = H - pad.top - pad.bottom;

    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#94a3b8' : '#64748b';
    var gridColor = isDark ? '#334155' : '#e2e8f0';
    var barColor = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#3b82f6';
    var avgColor = '#f59e0b';

    var maxVal = 0;
    for (var i = 0; i < completed.length; i++) {
        if (completed[i] > maxVal) maxVal = completed[i];
    }
    maxVal = Math.max(maxVal, Math.ceil(avg), 1);
    maxVal = Math.ceil(maxVal * 1.2);

    var n = sprintNames.length;
    var barWidth = Math.min(60, cW / n * 0.6);
    var gap = (cW - barWidth * n) / (n + 1);

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

    // Bars
    for (var i = 0; i < n; i++) {
        var x = pad.left + gap + i * (barWidth + gap);
        var barH = (completed[i] / maxVal) * cH;
        var y = pad.top + cH - barH;

        ctx.fillStyle = barColor;
        ctx.beginPath();
        // Rounded top corners
        var r = Math.min(4, barWidth / 2);
        ctx.moveTo(x, y + r);
        ctx.arcTo(x, y, x + r, y, r);
        ctx.arcTo(x + barWidth, y, x + barWidth, y + r, r);
        ctx.lineTo(x + barWidth, pad.top + cH);
        ctx.lineTo(x, pad.top + cH);
        ctx.closePath();
        ctx.fill();

        // Value label on top
        ctx.fillStyle = textColor;
        ctx.font = 'bold 12px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(completed[i].toString(), x + barWidth / 2, y - 6);

        // Sprint name label
        ctx.fillStyle = textColor;
        ctx.font = '10px system-ui, sans-serif';
        ctx.save();
        ctx.translate(x + barWidth / 2, H - pad.bottom + 10);
        ctx.rotate(-Math.PI / 4);
        ctx.textAlign = 'right';
        var label = sprintNames[i].length > 15 ? sprintNames[i].substring(0, 14) + '...' : sprintNames[i];
        ctx.fillText(label, 0, 0);
        ctx.restore();
    }

    // Average line
    if (avg > 0) {
        var avgY = yPos(avg);
        ctx.strokeStyle = avgColor;
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 4]);
        ctx.beginPath();
        ctx.moveTo(pad.left, avgY);
        ctx.lineTo(W - pad.right, avgY);
        ctx.stroke();
        ctx.setLineDash([]);

        ctx.fillStyle = avgColor;
        ctx.font = '11px system-ui, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText('<?= $esc(t('sprint.avg_short')) ?> ' + avg, W - pad.right + 4, avgY + 4);
    }

    // Y-axis label
    ctx.save();
    ctx.translate(14, pad.top + cH / 2);
    ctx.rotate(-Math.PI / 2);
    ctx.fillStyle = textColor;
    ctx.textAlign = 'center';
    ctx.font = '12px system-ui, sans-serif';
    ctx.fillText('<?= $esc(t('sprint.completed_tasks')) ?>', 0, 0);
    ctx.restore();
})();
</script>
<?php endif; ?>
