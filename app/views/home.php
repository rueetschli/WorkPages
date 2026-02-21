<?php
/**
 * Home view - Personal dashboard "Meine Arbeit" (AP22).
 *
 * Variables: $overdue, $dueToday, $dueWeek, $assignedToMe, $watching,
 *            $doneColumnId, $canEdit, $users
 */
$baseUrl  = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$userName = Security::esc($_SESSION['user_name'] ?? '');
?>

<div class="page-header">
    <h1><?= Security::esc(t('home.title')) ?></h1>
    <p class="subtitle"><?= Security::esc(t('home.greeting', ['name' => $userName])) ?></p>
</div>

<?php if (!empty($overdue)): ?>
<section class="home-section home-section-overdue">
    <h2 class="home-section-title home-section-title-overdue">
        <span class="home-section-icon-overdue">!</span>
        <?= Security::esc(t('home.overdue')) ?>
        <span class="home-section-count"><?= count($overdue) ?></span>
    </h2>
    <div class="home-task-list">
        <?php foreach ($overdue as $t): ?>
            <?php $this_task = $t; require APP_DIR . '/views/partials/home_task_row.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($dueToday)): ?>
<section class="home-section home-section-today">
    <h2 class="home-section-title home-section-title-today">
        <?= Security::esc(t('home.due_today')) ?>
        <span class="home-section-count"><?= count($dueToday) ?></span>
    </h2>
    <div class="home-task-list">
        <?php foreach ($dueToday as $t): ?>
            <?php $this_task = $t; require APP_DIR . '/views/partials/home_task_row.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($dueWeek)): ?>
<section class="home-section">
    <h2 class="home-section-title">
        <?= Security::esc(t('home.due_this_week')) ?>
        <span class="home-section-count"><?= count($dueWeek) ?></span>
    </h2>
    <div class="home-task-list">
        <?php foreach ($dueWeek as $t): ?>
            <?php $this_task = $t; require APP_DIR . '/views/partials/home_task_row.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-section">
    <h2 class="home-section-title">
        <?= Security::esc(t('home.assigned_to_me')) ?>
        <span class="home-section-count"><?= count($assignedToMe) ?></span>
    </h2>
    <?php if (empty($assignedToMe)): ?>
        <p class="placeholder-text"><?= Security::esc(t('messages.no_tasks_assigned')) ?></p>
    <?php else: ?>
    <div class="home-task-list">
        <?php foreach ($assignedToMe as $t): ?>
            <?php $this_task = $t; require APP_DIR . '/views/partials/home_task_row.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php if (!empty($watching)): ?>
<section class="home-section">
    <h2 class="home-section-title">
        <?= Security::esc(t('home.watching')) ?>
        <span class="home-section-count"><?= count($watching) ?></span>
    </h2>
    <div class="home-task-list">
        <?php foreach ($watching as $t): ?>
            <?php $this_task = $t; $isWatchSection = true; require APP_DIR . '/views/partials/home_task_row.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (empty($overdue) && empty($dueToday) && empty($dueWeek) && empty($assignedToMe) && empty($watching)): ?>
<div class="home-empty-state">
    <p><?= Security::esc(t('messages.all_done')) ?></p>
    <div class="home-empty-links">
        <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="btn btn-secondary"><?= Security::esc(t('home.all_tasks')) ?></a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=boards" class="btn btn-secondary">Boards</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=pages" class="btn btn-secondary">Pages</a>
    </div>
</div>
<?php endif; ?>
