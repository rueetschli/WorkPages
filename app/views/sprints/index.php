<?php
/**
 * Sprint overview for a board (AP26).
 *
 * Variables:
 *   $board            - current board
 *   $activeSprint     - active sprint or null
 *   $plannedSprints   - array of planned sprints
 *   $closedSprints    - array of closed sprints
 *   $sprintTaskCounts - array keyed by sprint_id with total/remaining/completed
 *   $canManage        - bool: can create/activate/close sprints
 *   $canAssign        - bool: can assign tasks to sprints
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];
?>

<div class="content-header">
    <div style="display:flex; align-items:center; gap:var(--sp-3);">
        <a href="<?= $esc($baseUrl) ?>/?r=board_view&amp;id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad">&larr;</a>
        <h1><?= $esc(t('sprint.title')) ?> &mdash; <?= $esc($board['name']) ?></h1>
    </div>
    <div style="display:flex; gap:var(--sp-2);">
        <?php if ($canManage): ?>
            <a href="<?= $esc($baseUrl) ?>/?r=sprint_create&amp;board_id=<?= $boardId ?>" class="btn btn-primary btn-sm-pad"><?= $esc(t('sprint.new_sprint')) ?></a>
        <?php endif; ?>
        <a href="<?= $esc($baseUrl) ?>/?r=sprint_velocity&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('sprint.velocity')) ?></a>
    </div>
</div>

<?php if ($activeSprint): ?>
<!-- Active Sprint -->
<div class="card" style="margin-bottom:var(--sp-4); border-left:4px solid var(--color-success);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span class="status-badge" style="background:var(--color-success); color:#fff;"><?= $esc(t('sprint.status.active')) ?></span>
            <strong style="margin-left:var(--sp-2);"><?= $esc($activeSprint['name']) ?></strong>
        </div>
        <div style="display:flex; gap:var(--sp-2);">
            <a href="<?= $esc($baseUrl) ?>/?r=sprint_burndown&amp;id=<?= (int) $activeSprint['id'] ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('sprint.burndown')) ?></a>
            <?php if ($canManage): ?>
            <form method="post" action="<?= $esc($baseUrl) ?>/?r=sprint_close" style="display:inline;"
                  onsubmit="return confirm('<?= $esc(t('sprint.confirm_close')) ?>');">
                <?= Security::csrfField() ?>
                <input type="hidden" name="sprint_id" value="<?= (int) $activeSprint['id'] ?>">
                <button type="submit" class="btn btn-warning btn-sm-pad"><?= $esc(t('sprint.close_sprint')) ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div style="display:flex; gap:var(--sp-4); flex-wrap:wrap;">
            <div><span class="text-muted"><?= $esc(t('sprint.start_date')) ?>:</span> <?= $esc($activeSprint['start_date']) ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.end_date')) ?>:</span> <?= $esc($activeSprint['end_date']) ?></div>
            <?php $ac = $sprintTaskCounts[(int) $activeSprint['id']] ?? ['total' => 0, 'remaining' => 0, 'completed' => 0]; ?>
            <div><span class="text-muted"><?= $esc(t('sprint.tasks_total')) ?>:</span> <?= $ac['total'] ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.tasks_remaining')) ?>:</span> <?= $ac['remaining'] ?></div>
            <div><span class="text-muted"><?= $esc(t('sprint.tasks_completed')) ?>:</span> <?= $ac['completed'] ?></div>
        </div>
        <?php if ($ac['total'] > 0): ?>
        <div style="margin-top:var(--sp-2);">
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width:<?= $ac['total'] > 0 ? round($ac['completed'] / $ac['total'] * 100) : 0 ?>%;"></div>
            </div>
            <span class="text-muted" style="font-size:0.85rem;"><?= $ac['total'] > 0 ? round($ac['completed'] / $ac['total'] * 100) : 0 ?>%</span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($plannedSprints)): ?>
<!-- Planned Sprints -->
<h2 style="margin-bottom:var(--sp-3);"><?= $esc(t('sprint.status.planned')) ?></h2>
<div class="table-responsive" style="margin-bottom:var(--sp-4);">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $esc(t('labels.name')) ?></th>
                <th><?= $esc(t('sprint.start_date')) ?></th>
                <th><?= $esc(t('sprint.end_date')) ?></th>
                <th><?= $esc(t('sprint.tasks_total')) ?></th>
                <th><?= $esc(t('labels.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($plannedSprints as $s):
            $sid = (int) $s['id'];
            $sc  = $sprintTaskCounts[$sid] ?? ['total' => 0];
        ?>
            <tr>
                <td><strong><?= $esc($s['name']) ?></strong></td>
                <td><?= $esc($s['start_date']) ?></td>
                <td><?= $esc($s['end_date']) ?></td>
                <td><?= $sc['total'] ?></td>
                <td>
                    <div style="display:flex; gap:var(--sp-1);">
                        <?php if ($canManage && !$activeSprint): ?>
                        <form method="post" action="<?= $esc($baseUrl) ?>/?r=sprint_activate" style="display:inline;">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="sprint_id" value="<?= $sid ?>">
                            <button type="submit" class="btn btn-success btn-sm-pad"><?= $esc(t('sprint.activate')) ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($canManage): ?>
                        <form method="post" action="<?= $esc($baseUrl) ?>/?r=sprint_delete" style="display:inline;"
                              onsubmit="return confirm('<?= $esc(t('sprint.confirm_delete')) ?>');">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="sprint_id" value="<?= $sid ?>">
                            <button type="submit" class="btn btn-danger btn-sm-pad"><?= $esc(t('actions.delete')) ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($closedSprints)): ?>
<!-- Closed Sprints -->
<h2 style="margin-bottom:var(--sp-3);"><?= $esc(t('sprint.status.closed')) ?></h2>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $esc(t('labels.name')) ?></th>
                <th><?= $esc(t('sprint.start_date')) ?></th>
                <th><?= $esc(t('sprint.end_date')) ?></th>
                <th><?= $esc(t('sprint.tasks_completed')) ?></th>
                <th><?= $esc(t('sprint.tasks_total')) ?></th>
                <th><?= $esc(t('labels.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($closedSprints as $s):
            $sid = (int) $s['id'];
            $sc  = $sprintTaskCounts[$sid] ?? ['total' => 0, 'completed' => 0];
        ?>
            <tr>
                <td><strong><?= $esc($s['name']) ?></strong></td>
                <td><?= $esc($s['start_date']) ?></td>
                <td><?= $esc($s['end_date']) ?></td>
                <td><?= $sc['completed'] ?></td>
                <td><?= $sc['total'] ?></td>
                <td>
                    <a href="<?= $esc($baseUrl) ?>/?r=sprint_burndown&amp;id=<?= $sid ?>" class="btn btn-secondary btn-sm-pad"><?= $esc(t('sprint.burndown')) ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!$activeSprint && empty($plannedSprints) && empty($closedSprints)): ?>
<div class="empty-state">
    <p><?= $esc(t('sprint.no_sprints')) ?></p>
    <?php if ($canManage): ?>
        <a href="<?= $esc($baseUrl) ?>/?r=sprint_create&amp;board_id=<?= $boardId ?>" class="btn btn-primary"><?= $esc(t('sprint.new_sprint')) ?></a>
    <?php endif; ?>
</div>
<?php endif; ?>
