<?php
/**
 * Page detail view.
 * Variables: $page (array), $breadcrumb (array), $renderedContent (string)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$canEdit = Security::hasRole(['admin', 'member']);
?>

<!-- Breadcrumb -->
<?php if (!empty($breadcrumb)): ?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="<?= Security::esc($baseUrl) ?>/?r=pages">Pages</a>
        </li>
        <?php foreach ($breadcrumb as $i => $crumb): ?>
        <li class="breadcrumb-item <?= $i === count($breadcrumb) - 1 ? 'breadcrumb-current' : '' ?>">
            <?php if ($i < count($breadcrumb) - 1): ?>
                <a href="<?= Security::esc($baseUrl) ?>/?r=page_view&slug=<?= Security::esc($crumb['slug']) ?>">
                    <?= Security::esc($crumb['title']) ?>
                </a>
            <?php else: ?>
                <span><?= Security::esc($crumb['title']) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= Security::esc($page['title']) ?></h1>
        <?php if ($canEdit): ?>
        <div class="page-actions">
            <a href="<?= Security::esc($baseUrl) ?>/?r=page_edit&slug=<?= Security::esc($page['slug']) ?>" class="btn btn-primary">Bearbeiten</a>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=page_delete&slug=<?= Security::esc($page['slug']) ?>"
                  class="inline-form" onsubmit="return confirm('Seite wirklich loeschen?');">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-danger">Loeschen</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="page-content md-content">
    <?= $renderedContent ?>
</div>

<div class="page-meta">
    <span class="text-muted">
        Erstellt am <?= Security::esc(date('d.m.Y H:i', strtotime($page['created_at']))) ?>
        <?php if ($page['updated_at']): ?>
            &middot; Aktualisiert am <?= Security::esc(date('d.m.Y H:i', strtotime($page['updated_at']))) ?>
        <?php endif; ?>
    </span>
</div>
