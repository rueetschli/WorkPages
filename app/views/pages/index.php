<?php
/**
 * Pages list view.
 * Variables: $pages (array of page rows)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Security::hasRole(['admin', 'member']);
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Pages</h1>
            <p class="subtitle">Wissens- und Arbeitsseiten</p>
        </div>
        <?php if ($canEdit): ?>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_create" class="btn btn-primary">+ Neue Seite</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($pages)): ?>
    <div class="section-block">
        <p class="placeholder-text">Noch keine Seiten vorhanden.</p>
        <?php if ($canEdit): ?>
        <p style="margin-top: var(--space-md);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_create" class="btn btn-primary">Erste Seite erstellen</a>
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="pages-table-wrap">
        <table class="pages-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Uebergeordnet</th>
                    <th>Erstellt von</th>
                    <th>Erstellt am</th>
                    <?php if ($canEdit): ?>
                    <th>Aktionen</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($p['slug']) ?>" class="page-link">
                            <?= Security::esc($p['title']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($p['parent_title']): ?>
                            <?= Security::esc($p['parent_title']) ?>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td><?= Security::esc($p['creator_name'] ?? '') ?></td>
                    <td><?= Security::esc(date('d.m.Y', strtotime($p['created_at']))) ?></td>
                    <?php if ($canEdit): ?>
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($p['slug']) ?>" class="btn-sm">Bearbeiten</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
