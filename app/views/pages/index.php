<?php
/**
 * Pages list view.
 * Variables: $pages (array of page rows)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Authz::can(Authz::PAGE_CREATE);
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1><?= Security::esc(t('pages.title')) ?></h1>
            <p class="subtitle"><?= Security::esc(t('pages.subtitle')) ?></p>
        </div>
        <?php if ($canEdit): ?>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_create" class="btn btn-primary"><?= Security::esc(t('pages.new_page')) ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($pages)): ?>
    <div class="section-block">
        <p class="placeholder-text"><?= Security::esc(t('messages.no_pages')) ?></p>
        <?php if ($canEdit): ?>
        <p style="margin-top: var(--sp-4);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_create" class="btn btn-primary"><?= Security::esc(t('pages.create_first')) ?></a>
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('pages.th_title')) ?></th>
                    <th><?= Security::esc(t('pages.th_parent')) ?></th>
                    <th><?= Security::esc(t('pages.th_created_by')) ?></th>
                    <th><?= Security::esc(t('pages.th_created_at')) ?></th>
                    <?php if ($canEdit): ?>
                    <th>Aktionen</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="card-cell-title">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($p['slug']) ?>" class="page-link">
                            <?= Security::esc($p['title']) ?>
                        </a>
                    </td>
                    <td data-label="<?= Security::esc(t('pages.th_parent')) ?>">
                        <?php if ($p['parent_title']): ?>
                            <?= Security::esc($p['parent_title']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('pages.th_created_by')) ?>"><?= Security::esc($p['creator_name'] ?? '') ?></td>
                    <td data-label="<?= Security::esc(t('pages.th_created_at')) ?>"><?= Security::esc(date('d.m.Y', strtotime($p['created_at']))) ?></td>
                    <?php if ($canEdit): ?>
                    <td class="card-cell-actions">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($p['slug']) ?>" class="btn-sm"><?= Security::esc(t('actions.edit')) ?></a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
