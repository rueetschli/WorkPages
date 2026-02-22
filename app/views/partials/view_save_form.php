<?php
/**
 * AP27: Inline "Save View" form partial.
 *
 * Renders a collapsible form to save the current filter state as a named view.
 * Include this in board/index.php, structure/index.php, and tasks/index.php.
 *
 * Required variables:
 *   $__viewType   - string: 'board' | 'structure' | 'tasks'
 *   $__contextId  - int|null: board_id for board/structure, null for tasks
 *   $__returnTo   - string: query string for redirect back (e.g. '?r=board_view&id=3')
 *   $__viewParams - array: current filter key-value pairs to persist
 *
 * Optional:
 *   $__userViews  - array: existing views of this type for the user (for quick-switch dropdown)
 */
$__baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$__esc     = [Security::class, 'esc'];
?>

<div class="views-bar" id="views-bar">
    <!-- Quick-switch: existing saved views -->
    <?php if (!empty($__userViews)): ?>
    <div class="views-quick-switch">
        <label class="views-label"><?= Security::esc(t('views.saved_views')) ?></label>
        <div class="views-chips">
            <?php foreach ($__userViews as $__uv): ?>
                <a href="<?= Security::esc(UserView::buildUrl($__uv, $__baseUrl)) ?>"
                   class="views-chip<?= !empty($__uv['is_default']) ? ' views-chip--default' : '' ?>"
                   title="<?= Security::esc($__uv['name']) ?>">
                    <?= Security::esc($__uv['name']) ?>
                    <?php if (!empty($__uv['is_default'])): ?>
                        <span class="views-chip-star" title="<?= Security::esc(t('views.is_default')) ?>">&#9733;</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Save current view button (toggles form) -->
    <button type="button" class="btn btn-secondary btn-sm-pad views-save-btn" id="views-save-toggle">
        <?= Security::esc(t('views.save_current')) ?>
    </button>

    <!-- Save form (hidden by default) -->
    <div class="views-save-form" id="views-save-form" style="display:none;">
        <form method="post" action="<?= Security::esc($__baseUrl) ?>/?r=view_save">
            <?= Security::csrfField() ?>
            <input type="hidden" name="view_type" value="<?= Security::esc($__viewType) ?>">
            <?php if ($__contextId !== null): ?>
                <input type="hidden" name="context_id" value="<?= (int) $__contextId ?>">
            <?php endif; ?>
            <input type="hidden" name="return_to" value="<?= Security::esc($__returnTo) ?>">

            <!-- Persist current filter params as hidden fields -->
            <?php foreach ($__viewParams as $__pk => $__pv): ?>
                <input type="hidden" name="param_<?= Security::esc($__pk) ?>" value="<?= Security::esc((string) $__pv) ?>">
            <?php endforeach; ?>

            <div class="views-save-row">
                <input type="text" name="view_name" class="form-input form-input-sm views-name-input"
                       placeholder="<?= Security::esc(t('views.name_placeholder')) ?>"
                       required maxlength="150" autocomplete="off">
                <label class="views-default-label">
                    <input type="checkbox" name="is_default" value="1">
                    <?= Security::esc(t('views.set_default')) ?>
                </label>
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('views.save_view')) ?></button>
                <button type="button" class="btn btn-secondary btn-sm-pad" id="views-save-cancel"><?= Security::esc(t('actions.cancel')) ?></button>
            </div>
            <p class="views-hint"><?= Security::esc(t('views.current_filters')) ?></p>
        </form>
    </div>
</div>

<script>
(function() {
    var toggleBtn  = document.getElementById('views-save-toggle');
    var form       = document.getElementById('views-save-form');
    var cancelBtn  = document.getElementById('views-save-cancel');
    if (!toggleBtn || !form) return;

    toggleBtn.addEventListener('click', function() {
        var visible = form.style.display !== 'none';
        form.style.display = visible ? 'none' : 'block';
        if (!visible) {
            var nameInput = form.querySelector('.views-name-input');
            if (nameInput) nameInput.focus();
        }
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            form.style.display = 'none';
        });
    }
})();
</script>
