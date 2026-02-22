<?php
/**
 * AP27: Manage saved views partial.
 *
 * Renders a small panel to rename, toggle default, update params, or delete existing views.
 * Typically included below the views bar or in a dropdown.
 *
 * Required variables:
 *   $__userViews  - array: views for this user (of the current type)
 *   $__returnTo   - string: query string for redirect back
 *   $__viewParams - array: current filter params (for overwrite)
 */
$__baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');

if (empty($__userViews)) {
    return;
}
?>

<div class="views-manage" id="views-manage">
    <details class="views-manage-details">
        <summary class="views-manage-summary"><?= Security::esc(t('views.saved_views')) ?> (<?= count($__userViews) ?>)</summary>
        <div class="views-manage-list">
            <?php foreach ($__userViews as $__mv): ?>
            <div class="views-manage-item">
                <div class="views-manage-item-header">
                    <span class="views-manage-name">
                        <?= Security::esc($__mv['name']) ?>
                        <?php if (!empty($__mv['is_default'])): ?>
                            <span class="views-chip-star" title="<?= Security::esc(t('views.is_default')) ?>">&#9733;</span>
                        <?php endif; ?>
                    </span>
                    <span class="views-manage-type"><?= Security::esc(t('views.type.' . ($__mv['view_type'] ?? 'tasks'))) ?></span>
                </div>
                <div class="views-manage-actions">
                    <!-- Load -->
                    <a href="<?= Security::esc(UserView::buildUrl($__mv, $__baseUrl)) ?>"
                       class="btn btn-secondary btn-xs"><?= Security::esc(t('views.load')) ?></a>

                    <!-- Toggle default -->
                    <form method="post" action="<?= Security::esc($__baseUrl) ?>/?r=view_set_default" class="inline-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="view_id" value="<?= (int) $__mv['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= Security::esc($__returnTo) ?>">
                        <button type="submit" class="btn btn-secondary btn-xs"
                                title="<?= Security::esc(empty($__mv['is_default']) ? t('views.set_default') : t('views.unset_default', ['name' => $__mv['name']])) ?>">
                            <?= empty($__mv['is_default']) ? '&#9734;' : '&#9733;' ?>
                        </button>
                    </form>

                    <!-- Update params -->
                    <form method="post" action="<?= Security::esc($__baseUrl) ?>/?r=view_update" class="inline-form">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="view_id" value="<?= (int) $__mv['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= Security::esc($__returnTo) ?>">
                        <input type="hidden" name="overwrite_params" value="1">
                        <?php foreach ($__viewParams as $__pk => $__pv): ?>
                            <input type="hidden" name="param_<?= Security::esc($__pk) ?>" value="<?= Security::esc((string) $__pv) ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-secondary btn-xs"
                                title="<?= Security::esc(t('views.overwrite_params')) ?>">&#8635;</button>
                    </form>

                    <!-- Delete -->
                    <form method="post" action="<?= Security::esc($__baseUrl) ?>/?r=view_delete" class="inline-form"
                          onsubmit="return confirm('<?= Security::esc(t('views.confirm_delete')) ?>')">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="view_id" value="<?= (int) $__mv['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= Security::esc($__returnTo) ?>">
                        <button type="submit" class="btn btn-danger btn-xs">&times;</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
</div>
