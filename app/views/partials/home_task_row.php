<?php
/**
 * Home task row partial (AP22).
 *
 * Variables expected from parent scope:
 *   $this_task   - task row array
 *   $baseUrl     - base URL
 *   $canEdit     - bool
 *   $doneColumnId - int|null
 *   $users       - array for owner dropdown
 *   $isWatchSection - bool (optional, shows owner instead of due)
 */
$_tid = (int) $this_task['id'];
$_due = $this_task['due_date'] ?? null;
$_isOverdue = ($_due && $_due < date('Y-m-d'));
$_isWatch = !empty($isWatchSection);
$isWatchSection = false; // reset for next iteration
?>
<div class="home-task-row">
    <div class="home-task-main">
        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= $_tid ?>" class="home-task-title">
            <?= Security::esc($this_task['title']) ?>
        </a>
        <div class="home-task-meta">
            <?php if (!empty($this_task['column_name'])): ?>
                <span class="home-task-column"
                      <?php if (!empty($this_task['column_color'])): ?>style="border-left: 3px solid <?= Security::esc($this_task['column_color']) ?>; padding-left: 6px;"<?php endif; ?>>
                    <?= Security::esc($this_task['column_name']) ?>
                </span>
            <?php endif; ?>
            <?php if ($_due): ?>
                <span class="home-task-due <?= $_isOverdue ? 'text-overdue' : '' ?>">
                    <?= Security::esc(date('d.m.Y', strtotime($_due))) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($this_task['board_name'])): ?>
                <span class="home-task-board"><?= Security::esc($this_task['board_name']) ?></span>
            <?php endif; ?>
            <?php if ($_isWatch && !empty($this_task['owner_name'])): ?>
                <span class="home-task-owner"><?= Security::esc($this_task['owner_name']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($canEdit): ?>
    <div class="home-task-actions">
        <?php if ($doneColumnId !== null): ?>
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=home" class="inline-form">
            <?= Security::csrfField() ?>
            <input type="hidden" name="home_action" value="mark_done">
            <input type="hidden" name="task_id" value="<?= $_tid ?>">
            <button type="submit" class="home-btn-done" title="Als erledigt markieren">&#10003;</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
