<?php
/**
 * Admin: System Settings view (AP20).
 * Variables: $settings, $presets, $versionInfo, $activeTab, $error, $success
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$logoUrl = SystemSettingsService::logoUrl();
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1><?= Security::esc(t('admin.settings_title')) ?></h1>
            <p class="subtitle"><?= Security::esc(t('admin.settings_subtitle')) ?></p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= Security::esc($success) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="admin-settings-tabs">
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=general"
       class="admin-settings-tab <?= $activeTab === 'general' ? 'admin-settings-tab-active' : '' ?>"><?= Security::esc(t('admin.tab_general')) ?></a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=design"
       class="admin-settings-tab <?= $activeTab === 'design' ? 'admin-settings-tab-active' : '' ?>"><?= Security::esc(t('admin.tab_design')) ?></a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=maintenance"
       class="admin-settings-tab <?= $activeTab === 'maintenance' ? 'admin-settings-tab-active' : '' ?>"><?= Security::esc(t('admin.tab_maintenance')) ?></a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=info"
       class="admin-settings-tab <?= $activeTab === 'info' ? 'admin-settings-tab-active' : '' ?>"><?= Security::esc(t('admin.tab_info')) ?></a>
</div>

<!-- Tab: General -->
<?php if ($activeTab === 'general'): ?>
<div class="section-block">
    <h2><?= Security::esc(t('admin.general_settings')) ?></h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=general" enctype="multipart/form-data">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save_general">

        <div class="form-group">
            <label for="company_name" class="form-label"><?= Security::esc(t('admin.company_name')) ?></label>
            <input type="text" id="company_name" name="company_name"
                   value="<?= Security::esc($settings['company_name'] ?? '') ?>"
                   class="form-input" maxlength="150"
                   placeholder="WorkPages">
            <p class="form-hint"><?= Security::esc(t('admin.company_name_hint')) ?></p>
        </div>

        <div class="form-group">
            <label for="logo" class="form-label"><?= Security::esc(t('admin.logo')) ?></label>
            <?php if ($logoUrl !== ''): ?>
                <div class="logo-preview">
                    <img src="<?= Security::esc($logoUrl) ?>" alt="Logo" class="logo-preview-img">
                </div>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" class="form-input" accept=".png,.jpg,.jpeg,.svg">
            <p class="form-hint"><?= Security::esc(t('admin.logo_hint')) ?></p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.save')) ?></button>
            <?php if ($logoUrl !== ''): ?>
                <button type="submit" name="action" value="remove_logo" class="btn btn-secondary"
                        onclick="return confirm('<?= Security::esc(t('messages.confirm_remove_logo')) ?>')"><?= Security::esc(t('admin.remove_logo')) ?></button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Tab: Design -->
<?php if ($activeTab === 'design'): ?>
<div class="section-block">
    <h2><?= Security::esc(t('admin.color_scheme')) ?></h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=design" id="design-form">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save_design">

        <div class="form-group">
            <label class="form-label"><?= Security::esc(t('admin.mode')) ?></label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="theme_mode" value="preset"
                           <?= ($settings['theme_mode'] ?? 'preset') === 'preset' ? 'checked' : '' ?>
                           onchange="document.getElementById('preset-section').style.display='block';document.getElementById('custom-section').style.display='none';">
                    <?= Security::esc(t('admin.preset_colors')) ?>
                </label>
                <label class="radio-label">
                    <input type="radio" name="theme_mode" value="custom"
                           <?= ($settings['theme_mode'] ?? 'preset') === 'custom' ? 'checked' : '' ?>
                           onchange="document.getElementById('preset-section').style.display='none';document.getElementById('custom-section').style.display='block';">
                    <?= Security::esc(t('admin.custom_colors')) ?>
                </label>
            </div>
        </div>

        <!-- Preset selection -->
        <div id="preset-section" style="<?= ($settings['theme_mode'] ?? 'preset') === 'custom' ? 'display:none' : '' ?>">
            <div class="form-group">
                <label class="form-label"><?= Security::esc(t('admin.choose_color_set')) ?></label>
                <div class="preset-grid">
                    <?php foreach ($presets as $key => $preset): ?>
                    <label class="preset-card <?= ($settings['theme_preset'] ?? 'blau') === $key ? 'preset-card-active' : '' ?>">
                        <input type="radio" name="theme_preset" value="<?= Security::esc($key) ?>"
                               <?= ($settings['theme_preset'] ?? 'blau') === $key ? 'checked' : '' ?>
                               class="preset-radio">
                        <div class="preset-colors">
                            <span class="preset-swatch" style="background:<?= Security::esc($preset['primary']) ?>"></span>
                            <span class="preset-swatch" style="background:<?= Security::esc($preset['secondary']) ?>"></span>
                            <span class="preset-swatch" style="background:<?= Security::esc($preset['accent']) ?>"></span>
                        </div>
                        <span class="preset-name"><?= Security::esc($preset['label']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Custom colors -->
        <div id="custom-section" style="<?= ($settings['theme_mode'] ?? 'preset') !== 'custom' ? 'display:none' : '' ?>">
            <div class="color-fields">
                <div class="form-group">
                    <label for="color_primary" class="form-label"><?= Security::esc(t('admin.color_primary')) ?></label>
                    <div class="color-input-wrap">
                        <input type="color" id="color_primary_picker" value="<?= Security::esc($settings['color_primary'] ?? '#2563eb') ?>"
                               onchange="document.getElementById('color_primary').value=this.value">
                        <input type="text" id="color_primary" name="color_primary"
                               value="<?= Security::esc($settings['color_primary'] ?? '#2563eb') ?>"
                               class="form-input" maxlength="7" pattern="#[0-9a-fA-F]{6}"
                               placeholder="#2563eb"
                               onchange="document.getElementById('color_primary_picker').value=this.value">
                    </div>
                </div>
                <div class="form-group">
                    <label for="color_secondary" class="form-label"><?= Security::esc(t('admin.color_secondary')) ?></label>
                    <div class="color-input-wrap">
                        <input type="color" id="color_secondary_picker" value="<?= Security::esc($settings['color_secondary'] ?? '#1e293b') ?>"
                               onchange="document.getElementById('color_secondary').value=this.value">
                        <input type="text" id="color_secondary" name="color_secondary"
                               value="<?= Security::esc($settings['color_secondary'] ?? '#1e293b') ?>"
                               class="form-input" maxlength="7" pattern="#[0-9a-fA-F]{6}"
                               placeholder="#1e293b"
                               onchange="document.getElementById('color_secondary_picker').value=this.value">
                    </div>
                </div>
                <div class="form-group">
                    <label for="color_accent" class="form-label"><?= Security::esc(t('admin.color_accent')) ?></label>
                    <div class="color-input-wrap">
                        <input type="color" id="color_accent_picker" value="<?= Security::esc($settings['color_accent'] ?? '#3b82f6') ?>"
                               onchange="document.getElementById('color_accent').value=this.value">
                        <input type="text" id="color_accent" name="color_accent"
                               value="<?= Security::esc($settings['color_accent'] ?? '#3b82f6') ?>"
                               class="form-input" maxlength="7" pattern="#[0-9a-fA-F]{6}"
                               placeholder="#3b82f6"
                               onchange="document.getElementById('color_accent_picker').value=this.value">
                    </div>
                </div>
            </div>
            <div class="color-preview" id="color-preview">
                <div class="color-preview-header" style="background: var(--wp-color-primary, #2563eb)">
                    <span style="color: #fff; font-weight: 600;"><?= Security::esc(t('admin.preview_header')) ?></span>
                </div>
                <div class="color-preview-body">
                    <button type="button" class="btn btn-primary" style="pointer-events:none"><?= Security::esc(t('admin.primary')) ?></button>
                    <button type="button" class="btn btn-secondary" style="pointer-events:none"><?= Security::esc(t('admin.secondary')) ?></button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.save')) ?></button>
        </div>
    </form>
</div>

<script>
(function() {
    'use strict';
    // Live preview for custom colors
    var fields = ['color_primary', 'color_secondary', 'color_accent'];
    var preview = document.getElementById('color-preview');
    if (!preview) return;

    function updatePreview() {
        var p = document.getElementById('color_primary').value;
        var s = document.getElementById('color_secondary').value;
        var a = document.getElementById('color_accent').value;
        var header = preview.querySelector('.color-preview-header');
        var btnPrimary = preview.querySelector('.btn-primary');
        var btnSecondary = preview.querySelector('.btn-secondary');
        if (header) header.style.background = p;
        if (btnPrimary) { btnPrimary.style.background = p; btnPrimary.style.borderColor = p; }
        if (btnSecondary) { btnSecondary.style.background = s; btnSecondary.style.borderColor = s; }
    }

    for (var i = 0; i < fields.length; i++) {
        var el = document.getElementById(fields[i]);
        var picker = document.getElementById(fields[i] + '_picker');
        if (el) el.addEventListener('input', updatePreview);
        if (picker) picker.addEventListener('input', function() {
            var id = this.id.replace('_picker', '');
            document.getElementById(id).value = this.value;
            updatePreview();
        });
    }

    // Preset card selection highlight
    var presetRadios = document.querySelectorAll('.preset-radio');
    for (var j = 0; j < presetRadios.length; j++) {
        presetRadios[j].addEventListener('change', function() {
            var cards = document.querySelectorAll('.preset-card');
            for (var k = 0; k < cards.length; k++) {
                cards[k].classList.remove('preset-card-active');
            }
            this.closest('.preset-card').classList.add('preset-card-active');
        });
    }
})();
</script>
<?php endif; ?>

<!-- Tab: Maintenance -->
<?php if ($activeTab === 'maintenance'): ?>
<div class="section-block">
    <h2><?= Security::esc(t('admin.maintenance_title')) ?></h2>
    <p class="subtitle"><?= Security::esc(t('admin.maintenance_subtitle')) ?></p>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_settings&tab=maintenance">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save_maintenance">

        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="maintenance_active" value="1"
                       <?= (int) ($settings['maintenance_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                <?= Security::esc(t('admin.maintenance_active')) ?>
            </label>
        </div>

        <div class="form-group">
            <label for="maintenance_message" class="form-label"><?= Security::esc(t('admin.maintenance_message')) ?></label>
            <input type="text" id="maintenance_message" name="maintenance_message"
                   value="<?= Security::esc($settings['maintenance_message'] ?? '') ?>"
                   class="form-input" maxlength="255"
                   placeholder="<?= Security::esc(t('placeholders.maintenance_message')) ?>">
        </div>

        <div class="form-group">
            <label for="maintenance_level" class="form-label"><?= Security::esc(t('admin.maintenance_level')) ?></label>
            <select id="maintenance_level" name="maintenance_level" class="form-input">
                <option value="info" <?= ($settings['maintenance_level'] ?? 'info') === 'info' ? 'selected' : '' ?>><?= Security::esc(t('admin.level_info')) ?></option>
                <option value="warning" <?= ($settings['maintenance_level'] ?? 'info') === 'warning' ? 'selected' : '' ?>><?= Security::esc(t('admin.level_warning')) ?></option>
                <option value="critical" <?= ($settings['maintenance_level'] ?? 'info') === 'critical' ? 'selected' : '' ?>><?= Security::esc(t('admin.level_critical')) ?></option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.save')) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Tab: Info -->
<?php if ($activeTab === 'info'): ?>
<div class="section-block">
    <h2><?= Security::esc(t('admin.about_workpages')) ?></h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600; width: 40%;"><?= Security::esc(t('admin.version')) ?></td>
                <td>v<?= Security::esc($versionInfo['version'] ?? t('admin.unknown')) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.license')) ?></td>
                <td><?= Security::esc($versionInfo['license'] ?? 'MIT') ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.repository')) ?></td>
                <td><a href="<?= Security::esc($versionInfo['repo'] ?? '#') ?>" target="_blank" rel="noopener"><?= Security::esc($versionInfo['repo'] ?? '') ?></a></td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.project')) ?></td>
                <td><?= Security::esc(t('admin.project_description')) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section-block">
    <h2><?= Security::esc(t('admin.technology')) ?></h2>
    <table class="pages-table" style="margin-top: 0.75rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600; width: 40%;"><?= Security::esc(t('admin.backend')) ?></td>
                <td>PHP <?= Security::esc(PHP_VERSION) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.database')) ?></td>
                <td>MySQL (PDO)</td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.frontend')) ?></td>
                <td><?= Security::esc(t('admin.frontend_description')) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;"><?= Security::esc(t('admin.architecture')) ?></td>
                <td><?= Security::esc(t('admin.architecture_description')) ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>
