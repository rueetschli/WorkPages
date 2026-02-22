<?php
/**
 * AP30: Page copy form.
 * Variables: $page (array), $parentPages (array), $formData (array), $error (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages"><?= Security::esc(t('pages.title')) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($page['slug']) ?>">
                <?= Security::esc($page['title']) ?>
            </a>
        </li>
        <li class="breadcrumb-item breadcrumb-current">
            <span><?= Security::esc(t('ap30.copy_page_title')) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1><?= Security::esc(t('ap30.copy_page_title')) ?></h1>
    <p class="text-muted"><?= Security::esc(t('ap30.copy_page_description')) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_copy&slug=<?= Security::esc($page['slug']) ?>" class="form-stack">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="title" class="form-label"><?= Security::esc(t('ap30.copy_new_title')) ?> <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" class="form-input" value="<?= Security::esc($formData['title']) ?>" required autofocus>
    </div>

    <fieldset class="form-group">
        <legend class="form-label"><?= Security::esc(t('ap30.copy_target_hierarchy')) ?></legend>

        <div class="form-group">
            <label class="form-label">
                <input type="radio" name="target_type" value="root" <?= ($formData['target_type'] ?? 'root') === 'root' ? 'checked' : '' ?> onchange="document.getElementById('copy-parent-group').style.display='none'">
                <?= Security::esc(t('ap30.move_target_root')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-label">
                <input type="radio" name="target_type" value="parent" <?= ($formData['target_type'] ?? '') === 'parent' ? 'checked' : '' ?> onchange="document.getElementById('copy-parent-group').style.display='block'">
                <?= Security::esc(t('ap30.move_target_parent')) ?>
            </label>
        </div>

        <div class="form-group" id="copy-parent-group" style="display: <?= ($formData['target_type'] ?? 'root') === 'parent' ? 'block' : 'none' ?>; margin-left: var(--sp-4);">
            <label for="parent_id" class="form-label"><?= Security::esc(t('labels.parent_page')) ?></label>
            <select id="parent_id" name="parent_id" class="form-input">
                <option value=""><?= Security::esc(t('ap30.select_parent_page')) ?></option>
                <?php foreach ($parentPages as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= ((string) ($formData['parent_id'] ?? '')) === (string) $p['id'] ? 'selected' : '' ?>>
                        <?= Security::esc($p['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>

    <fieldset class="form-group">
        <legend class="form-label"><?= Security::esc(t('actions.filter')) ?></legend>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="copy_attachments" value="1" <?= !empty($formData['copy_attachments']) ? 'checked' : '' ?>>
                <?= Security::esc(t('ap30.copy_option_attachments')) ?>
            </label>
        </div>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="copy_tasks" value="1" <?= !empty($formData['copy_tasks']) ? 'checked' : '' ?>>
                <?= Security::esc(t('ap30.copy_option_tasks')) ?>
            </label>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= Security::esc(t('ap30.copy_button')) ?></button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
    </div>
</form>
