<?php
/**
 * Page edit form.
 * Variables: $page (array), $error (string|null), $formData (array), $parentPages (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1><?= Security::esc(t('pages.edit_title')) ?></h1>
    <p class="subtitle"><?= Security::esc($page['title']) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<div class="section-block">
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($page['slug']) ?>" class="page-form">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="title"><?= Security::esc(t('labels.title')) ?></label>
            <input type="text" id="title" name="title" class="form-input"
                   value="<?= Security::esc($formData['title']) ?>"
                   required autofocus>
        </div>

        <div class="form-group">
            <label for="parent_id"><?= Security::esc(t('labels.parent_page')) ?></label>
            <select id="parent_id" name="parent_id" class="form-input">
                <option value=""><?= Security::esc(t('pages.parent_none')) ?></option>
                <?php foreach ($parentPages as $pp): ?>
                    <option value="<?= (int) $pp['id'] ?>"
                        <?= (string) $formData['parent_id'] === (string) $pp['id'] ? 'selected' : '' ?>>
                        <?= Security::esc($pp['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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

        <div class="form-group">
            <label for="content_md"><?= Security::esc(t('labels.content_md')) ?></label>
            <textarea id="content_md" name="content_md" class="form-input form-textarea"
                      rows="18" data-mentions="true" data-context="page"><?= Security::esc($formData['content_md']) ?></textarea>
            <span class="textarea-hint"><?= Security::esc(t('pages.textarea_hint')) ?></span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.save_changes')) ?></button>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
        </div>
    </form>
</div>
