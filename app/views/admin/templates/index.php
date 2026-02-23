<?php
/**
 * Admin Templates view (AP31).
 *
 * Variables: $grouped, $stats, $templates, $hasZipArchive
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$currentLang = I18nService::getCurrentLanguage();
?>

<div class="content-header">
    <h1><?= Security::esc(t('templates.admin_title')) ?></h1>
    <p class="text-muted"><?= Security::esc(t('templates.admin_description')) ?></p>
</div>

<!-- Stats -->
<div class="stats-row" style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
    <div class="stat-card">
        <span class="stat-value"><?= (int) $stats['total'] ?></span>
        <span class="stat-label"><?= Security::esc(t('templates.stat_total')) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-value" style="color:var(--color-success, #00b894)"><?= (int) $stats['imported'] ?></span>
        <span class="stat-label"><?= Security::esc(t('templates.stat_imported')) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-value" style="color:var(--color-warning, #fdcb6e)"><?= (int) $stats['update_available'] ?></span>
        <span class="stat-label"><?= Security::esc(t('templates.stat_update')) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-value" style="color:var(--text-muted, #636e72)"><?= (int) $stats['not_imported'] ?></span>
        <span class="stat-label"><?= Security::esc(t('templates.stat_not_imported')) ?></span>
    </div>
</div>

<!-- Bulk actions -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_templates_import_all" style="display:inline-flex; gap:0.5rem; align-items:center;">
            <?= Security::csrfField() ?>
            <select name="language" class="form-input" style="width:auto;">
                <option value="de" <?= $currentLang === 'de' ? 'selected' : '' ?>>Deutsch</option>
                <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>English</option>
            </select>
            <button type="submit" class="btn btn-primary">
                <?= Security::esc(t('templates.import_all')) ?>
            </button>
        </form>

        <?php if ($hasZipArchive): ?>
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_templates_refresh" style="display:inline;">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-secondary" onclick="return confirm('<?= Security::esc(t('templates.refresh_confirm')) ?>')">
                <?= Security::esc(t('templates.refresh_from_github')) ?>
            </button>
        </form>
        <?php else: ?>
        <span class="text-muted" style="font-size:0.85rem;">
            <?= Security::esc(t('templates.zip_not_available')) ?>
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Template list grouped by category -->
<?php if (empty($grouped)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted"><?= Security::esc(t('templates.none_found')) ?></p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $category => $tpls): ?>
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <h3 style="margin:0; font-size:1.05rem;"><?= Security::esc(TemplateService::categoryLabel($category)) ?></h3>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('templates.col_title')) ?></th>
                    <th><?= Security::esc(t('templates.col_language')) ?></th>
                    <th><?= Security::esc(t('templates.col_status')) ?></th>
                    <th style="text-align:right;"><?= Security::esc(t('labels.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tpls as $tpl): ?>
                <tr>
                    <td>
                        <?php if ($tpl['page_id']): ?>
                            <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&amp;slug=<?= Security::esc(Page::findById($tpl['page_id'])['slug'] ?? '') ?>">
                                <?= Security::esc($tpl['title']) ?>
                            </a>
                        <?php else: ?>
                            <?= Security::esc($tpl['title']) ?>
                        <?php endif; ?>
                        <span class="text-muted" style="font-size:0.8rem; margin-left:0.25rem;"><?= Security::esc($tpl['filename']) ?></span>
                    </td>
                    <td>
                        <span class="badge"><?= Security::esc(strtoupper($tpl['language'])) ?></span>
                    </td>
                    <td>
                        <?php if ($tpl['status'] === 'imported'): ?>
                            <span class="badge badge-success"><?= Security::esc(t('templates.status_imported')) ?></span>
                        <?php elseif ($tpl['status'] === 'update_available'): ?>
                            <span class="badge badge-warning"><?= Security::esc(t('templates.status_update')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-muted"><?= Security::esc(t('templates.status_not_imported')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if ($tpl['status'] === 'not_imported' || $tpl['status'] === 'update_available'): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_templates_import" style="display:inline;">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="template_key" value="<?= Security::esc($tpl['key']) ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <?= Security::esc(t('templates.btn_import')) ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:0.8rem;"><?= Security::esc($tpl['imported_at'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
.stat-card {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #dfe6e9);
    border-radius: 8px;
    padding: 1rem 1.25rem;
    min-width: 120px;
    text-align: center;
}
.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    display: block;
    font-size: 0.8rem;
    color: var(--text-muted, #636e72);
    margin-top: 0.25rem;
}
.badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--border-color, #dfe6e9);
    color: var(--text-color, #2d3436);
}
.badge-success {
    background: #e8f8f5;
    color: #00b894;
}
.badge-warning {
    background: #fef9e7;
    color: #f39c12;
}
.badge-muted {
    background: var(--border-color, #dfe6e9);
    color: var(--text-muted, #636e72);
}
.card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #dfe6e9); border-radius: 8px; overflow: hidden; }
.card-header { padding: 0.75rem 1rem; background: var(--bg-light, #f8f9fa); border-bottom: 1px solid var(--border-color, #dfe6e9); }
.card-body { padding: 1rem; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border-color, #dfe6e9); text-align: left; font-size: 0.9rem; }
.table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted, #636e72); }
.table tbody tr:last-child td { border-bottom: none; }
.btn-sm { padding: 0.3rem 0.75rem; font-size: 0.8rem; }
.btn-secondary { background: var(--border-color, #dfe6e9); color: var(--text-color, #2d3436); border: none; cursor: pointer; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
.btn-secondary:hover { background: #b2bec3; }
</style>
