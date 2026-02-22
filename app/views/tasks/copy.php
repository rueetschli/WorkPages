<?php
/**
 * AP30: Task copy form.
 * Variables: $task (array), $formData (array), $availableBoards (array),
 *            $tags (array), $taskSprint (array|null), $error (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$originalBoardId = !empty($task['board_id']) ? (int) $task['board_id'] : null;
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks"><?= Security::esc(t('tasks.title')) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= (int) $task['id'] ?>">
                <?= Security::esc($task['title']) ?>
            </a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc(t('ap30.copy_task_title')) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1><?= Security::esc(t('ap30.copy_task_title')) ?></h1>
    <p class="text-muted"><?= Security::esc(t('ap30.copy_task_description')) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_copy&id=<?= (int) $task['id'] ?>" class="form-stack">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="title" class="form-label"><?= Security::esc(t('ap30.copy_new_title')) ?> <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" class="form-input" value="<?= Security::esc($formData['title']) ?>" required autofocus>
    </div>

    <div class="form-group">
        <label for="board_id" class="form-label"><?= Security::esc(t('ap30.copy_task_target_board')) ?></label>
        <select id="board_id" name="board_id" class="form-input">
            <option value=""><?= Security::esc(t('tasks.board_none')) ?></option>
            <?php foreach ($availableBoards as $board): ?>
                <option value="<?= (int) $board['id'] ?>" <?= ((string) ($formData['board_id'] ?? '')) === (string) $board['id'] ? 'selected' : '' ?>>
                    <?= Security::esc($board['name']) ?>
                    <?php if (!empty($board['team_name'])): ?> (<?= Security::esc($board['team_name']) ?>)<?php endif; ?>
                    <?php if ($originalBoardId !== null && (int) $board['id'] === $originalBoardId): ?> — <?= Security::esc(t('ap30.copy_task_board_same')) ?><?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <fieldset class="form-group">
        <legend class="form-label"><?= Security::esc(t('actions.filter')) ?></legend>

        <?php if (!empty($tags)): ?>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="keep_tags" value="1" <?= !empty($formData['keep_tags']) ? 'checked' : '' ?>>
                <?= Security::esc(t('ap30.copy_task_keep_tags')) ?>
                <span class="text-muted">(<?= Security::esc(implode(', ', array_column($tags, 'name'))) ?>)</span>
            </label>
        </div>
        <?php endif; ?>

        <?php if (!empty($task['parent_task_id'])): ?>
        <?php
            $parentTask = null;
            try { $parentTask = Task::findById((int) $task['parent_task_id']); } catch (Throwable $e) {}
        ?>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="keep_parent" value="1" <?= !empty($formData['keep_parent']) ? 'checked' : '' ?>>
                <?= Security::esc(t('ap30.copy_task_keep_parent')) ?>
                <?php if ($parentTask): ?>
                    <span class="text-muted">(<?= Security::esc($parentTask['title']) ?>)</span>
                <?php endif; ?>
            </label>
            <small class="form-hint"><?= Security::esc(t('structure.error.different_board')) ?></small>
        </div>
        <?php endif; ?>

        <?php if ($taskSprint): ?>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="keep_sprint" value="1" <?= !empty($formData['keep_sprint']) ? 'checked' : '' ?>>
                <?= Security::esc(t('ap30.copy_task_keep_sprint')) ?>
                <span class="text-muted">(<?= Security::esc($taskSprint['name']) ?>)</span>
            </label>
        </div>
        <?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= Security::esc(t('ap30.copy_task_button')) ?></button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= (int) $task['id'] ?>" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
    </div>
</form>
