<?php
/**
 * Admin: Languages overview (AP24).
 * Variables: $langStats (array), $systemDefault (string), $error (string|null), $success (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1><?= Security::esc(t('admin.languages_title')) ?></h1>
            <p class="subtitle"><?= Security::esc(t('admin.languages_subtitle')) ?></p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= Security::esc($success) ?></div>
<?php endif; ?>

<!-- Default language setting -->
<div class="section-block">
    <h2><?= Security::esc(t('admin.default_language')) ?></h2>
    <p class="form-hint" style="margin-bottom: var(--sp-3);"><?= Security::esc(t('admin.default_language_hint')) ?></p>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_languages" class="filter-form">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="set_default_language">
        <div class="filter-row">
            <div class="filter-group">
                <select name="default_language" class="form-input form-input-sm">
                    <?php foreach ($langStats as $lang): ?>
                        <option value="<?= Security::esc($lang['code']) ?>"
                            <?= $lang['code'] === $systemDefault ? 'selected' : '' ?>>
                            <?= Security::esc($lang['name']) ?> (<?= Security::esc($lang['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group filter-actions">
                <button type="submit" class="btn btn-primary btn-sm-pad"><?= Security::esc(t('actions.save')) ?></button>
            </div>
        </div>
    </form>
</div>

<!-- Language overview table -->
<div class="section-block">
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('admin.languages_code')) ?></th>
                    <th><?= Security::esc(t('admin.languages_name')) ?></th>
                    <th><?= Security::esc(t('admin.languages_source')) ?></th>
                    <th><?= Security::esc(t('admin.languages_completeness')) ?></th>
                    <th><?= Security::esc(t('admin.languages_missing_keys')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($langStats as $lang): ?>
                <tr>
                    <td class="card-cell-title">
                        <strong><?= Security::esc($lang['code']) ?></strong>
                        <?php if ($lang['code'] === 'de'): ?>
                            <span class="status-badge status-doing"><?= Security::esc(t('admin.languages_reference')) ?></span>
                        <?php endif; ?>
                        <?php if ($lang['code'] === $systemDefault): ?>
                            <span class="status-badge">Default</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('admin.languages_name')) ?>">
                        <?= Security::esc($lang['name']) ?>
                    </td>
                    <td data-label="<?= Security::esc(t('admin.languages_source')) ?>">
                        <?php
                            $sourceKey = 'admin.languages_source_' . $lang['source'];
                            echo Security::esc(t($sourceKey));
                        ?>
                    </td>
                    <td data-label="<?= Security::esc(t('admin.languages_completeness')) ?>">
                        <?php
                            $percent = $lang['percent'];
                            $barClass = $percent >= 100 ? 'progress-complete' : ($percent >= 80 ? 'progress-good' : 'progress-low');
                        ?>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar <?= $barClass ?>" style="width: <?= (float) $percent ?>%"></div>
                        </div>
                        <span class="text-muted"><?= number_format($percent, 1) ?> %</span>
                        <span class="text-muted">(<?= (int) $lang['translated'] ?>/<?= (int) $lang['total'] ?>)</span>
                    </td>
                    <td data-label="<?= Security::esc(t('admin.languages_missing_keys')) ?>">
                        <?php if ($lang['missing'] > 0): ?>
                            <span class="text-overdue"><?= (int) $lang['missing'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($lang['missing'] > 0): ?>
                        <details class="inline-details">
                            <summary class="btn-sm"><?= Security::esc(t('admin.languages_show_missing')) ?></summary>
                            <div class="missing-keys-list">
                                <ul>
                                    <?php foreach ($lang['missing_keys'] as $mk): ?>
                                        <li><code><?= Security::esc($mk) ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="form-hint"><?= Security::esc(t('messages.missing_translations_hint')) ?></p>
                            </div>
                        </details>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.progress-bar-wrap {
    display: inline-block;
    width: 100px;
    height: 8px;
    background: var(--color-border, #e2e8f0);
    border-radius: 4px;
    overflow: hidden;
    vertical-align: middle;
    margin-right: var(--sp-1, 4px);
}
.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.progress-complete { background: var(--color-success, #22c55e); }
.progress-good { background: var(--color-accent, #3b82f6); }
.progress-low { background: var(--color-warning, #f59e0b); }
.inline-details { display: inline; }
.inline-details summary { cursor: pointer; }
.missing-keys-list {
    margin-top: var(--sp-2, 8px);
    padding: var(--sp-3, 12px);
    background: var(--color-surface, #f8fafc);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: var(--radius-md, 6px);
    max-height: 300px;
    overflow-y: auto;
}
.missing-keys-list ul {
    margin: 0;
    padding: 0 0 0 1.2rem;
    list-style: disc;
}
.missing-keys-list li {
    margin-bottom: 2px;
    font-size: 0.85rem;
}
</style>
