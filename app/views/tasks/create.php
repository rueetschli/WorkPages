<?php
/**
 * Task create form.
 * Variables: $error (string|null), $formData (array), $users (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1><?= Security::esc(t('tasks.create_title')) ?></h1>
    <p class="subtitle"><?= Security::esc(t('tasks.create_subtitle')) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<div class="section-block">
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=task_create" class="page-form">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="title"><?= Security::esc(t('labels.title')) ?></label>
            <input type="text" id="title" name="title" class="form-input"
                   value="<?= Security::esc($formData['title']) ?>"
                   required autofocus>
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="column_id"><?= Security::esc(t('labels.column')) ?></label>
                <select id="column_id" name="column_id" class="form-input">
                    <?php foreach ($boardColumns as $col): ?>
                        <option value="<?= (int) $col['id'] ?>"
                            <?= (string) $formData['column_id'] === (string) $col['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($col['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group form-group-half">
                <label for="owner_id"><?= Security::esc(t('labels.owner')) ?></label>
                <select id="owner_id" name="owner_id" class="form-input">
                    <option value=""><?= Security::esc(t('tasks.owner_none')) ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                            <?= (string) $formData['owner_id'] === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= Security::esc($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="due_date"><?= Security::esc(t('labels.due_date')) ?></label>
                <input type="date" id="due_date" name="due_date" class="form-input"
                       value="<?= Security::esc($formData['due_date']) ?>">
            </div>

            <div class="form-group form-group-half">
                <label for="tags"><?= Security::esc(t('labels.tags')) ?></label>
                <?php
                    $tagInputId    = 'tags';
                    $tagInputName  = 'tags';
                    $tagInputValue = $formData['tags'];
                    require APP_DIR . '/views/partials/tag_input.php';
                ?>
            </div>
        </div>

        <?php if (!empty($availableTeams)): ?>
        <div class="form-group">
            <label for="team_id"><?= Security::esc(t('labels.team')) ?></label>
            <select id="team_id" name="team_id" class="form-input team-select">
                <option value=""><?= Security::esc(t('pages.team_global')) ?></option>
                <?php foreach ($availableTeams as $__t): ?>
                    <option value="<?= (int) $__t['id'] ?>"
                        <?= (string) ($formData['team_id'] ?? '') === (string) $__t['id'] ? 'selected' : '' ?>>
                        <?= Security::esc($__t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!empty($availableBoards)): ?>
        <div class="form-group">
            <label for="board_id"><?= Security::esc(t('labels.board')) ?></label>
            <select id="board_id" name="board_id" class="form-input">
                <option value=""><?= Security::esc(t('tasks.board_none')) ?></option>
                <?php foreach ($availableBoards as $__b): ?>
                    <option value="<?= (int) $__b['id'] ?>"
                        <?= (string) ($formData['board_id'] ?? '') === (string) $__b['id'] ? 'selected' : '' ?>>
                        <?= Security::esc($__b['name']) ?><?= !empty($__b['team_name']) ? ' (' . Security::esc($__b['team_name']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="description_md"><?= Security::esc(t('labels.description_md')) ?></label>
            <textarea id="description_md" name="description_md" class="form-input form-textarea"
                      rows="14" data-mentions="true" data-context="task"><?= Security::esc($formData['description_md']) ?></textarea>
            <span class="textarea-hint"><?= Security::esc(t('tasks.textarea_hint')) ?></span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('tasks.create_button')) ?></button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
        </div>
    </form>
</div>
