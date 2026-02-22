<?php
/**
 * AP30: Page move form.
 * Variables: $page (array), $availableParents (array), $error (string|null)
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
            <span><?= Security::esc(t('ap30.move_page_title')) ?></span>
        </li>
    </ol>
</nav>

<div class="page-header">
    <h1><?= Security::esc(t('ap30.move_page_title')) ?></h1>
    <p class="text-muted"><?= Security::esc(t('ap30.move_page_description')) ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_move&slug=<?= Security::esc($page['slug']) ?>" class="form-stack">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label class="form-label">
            <input type="radio" name="target_type" value="root" <?= ($_POST['target_type'] ?? 'root') === 'root' ? 'checked' : '' ?> onchange="document.getElementById('parent-select-group').style.display='none'">
            <?= Security::esc(t('ap30.move_target_root')) ?>
        </label>
    </div>

    <div class="form-group">
        <label class="form-label">
            <input type="radio" name="target_type" value="parent" <?= ($_POST['target_type'] ?? '') === 'parent' ? 'checked' : '' ?> onchange="document.getElementById('parent-select-group').style.display='block'">
            <?= Security::esc(t('ap30.move_target_parent')) ?>
        </label>
    </div>

    <div class="form-group" id="parent-select-group" style="display: <?= ($_POST['target_type'] ?? 'root') === 'parent' ? 'block' : 'none' ?>; margin-left: var(--sp-4);">
        <label for="parent_id" class="form-label"><?= Security::esc(t('labels.parent_page')) ?></label>
        <select id="parent_id" name="parent_id" class="form-input">
            <option value=""><?= Security::esc(t('ap30.select_parent_page')) ?></option>
            <?php foreach ($availableParents as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= ((int) ($_POST['parent_id'] ?? 0)) === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= Security::esc($p['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php
        $currentParent = null;
        if (!empty($page['parent_id'])) {
            $currentParent = Page::findById((int) $page['parent_id']);
        }
    ?>
    <div class="form-group">
        <small class="form-hint">
            <?php if ($currentParent): ?>
                Aktueller Parent: <strong><?= Security::esc($currentParent['title']) ?></strong>
            <?php else: ?>
                Aktueller Parent: <strong>Root-Ebene</strong>
            <?php endif; ?>
        </small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= Security::esc(t('ap30.move_button')) ?></button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-secondary"><?= Security::esc(t('actions.cancel')) ?></a>
    </div>
</form>
