<?php
/**
 * Create Sprint form (AP26).
 *
 * Variables:
 *   $board    - current board
 *   $formData - pre-filled form data
 *   $error    - error message or null
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$boardId = (int) $board['id'];
?>

<div class="content-header">
    <div style="display:flex; align-items:center; gap:var(--sp-3);">
        <a href="<?= $esc($baseUrl) ?>/?r=sprints&amp;board_id=<?= $boardId ?>" class="btn btn-secondary btn-sm-pad">&larr;</a>
        <h1><?= $esc(t('sprint.create_title')) ?></h1>
    </div>
</div>

<div class="card" style="max-width:600px;">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $esc($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= $esc($baseUrl) ?>/?r=sprint_create&amp;board_id=<?= $boardId ?>">
        <?= Security::csrfField() ?>
        <input type="hidden" name="board_id" value="<?= $boardId ?>">

        <div class="form-group">
            <label class="form-label"><?= $esc(t('labels.name')) ?> <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" value="<?= $esc($formData['name']) ?>" required maxlength="150"
                   placeholder="<?= $esc(t('sprint.placeholder_name')) ?>">
        </div>

        <div class="form-group">
            <label class="form-label"><?= $esc(t('sprint.start_date')) ?> <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-input" value="<?= $esc($formData['start_date']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $esc(t('sprint.end_date')) ?> <span class="text-danger">*</span></label>
            <input type="date" name="end_date" class="form-input" value="<?= $esc($formData['end_date']) ?>" required>
        </div>

        <div class="form-group" style="display:flex; gap:var(--sp-2);">
            <button type="submit" class="btn btn-primary"><?= $esc(t('sprint.create_button')) ?></button>
            <a href="<?= $esc($baseUrl) ?>/?r=sprints&amp;board_id=<?= $boardId ?>" class="btn btn-secondary"><?= $esc(t('actions.cancel')) ?></a>
        </div>
    </form>
</div>
