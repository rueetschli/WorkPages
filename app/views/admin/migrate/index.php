<?php
/**
 * Admin: Database migrations view.
 * Variables: $currentVersion (int), $pendingMigrations (array), $error, $success
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Datenbank-Migrationen</h1>
            <p class="subtitle">Schema-Updates verwalten</p>
        </div>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_users" class="btn btn-secondary">Zurueck zur Verwaltung</a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= Security::esc($success) ?></div>
<?php endif; ?>

<div class="section-block">
    <h2>Aktueller Stand</h2>
    <p style="margin-top: 0.5rem;">
        Schema-Version: <strong><?= (int) $currentVersion ?></strong>
    </p>
</div>

<?php if (empty($pendingMigrations)): ?>
    <div class="section-block">
        <p class="placeholder-text">Keine ausstehenden Migrationen. Die Datenbank ist aktuell.</p>
    </div>
<?php else: ?>
    <div class="section-block">
        <h2>Ausstehende Migrationen</h2>
        <ul style="margin: 0.75rem 0 1.25rem 1.5rem;">
            <?php foreach ($pendingMigrations as $m): ?>
                <li><code><?= Security::esc($m['file']) ?></code> (Version <?= (int) $m['version'] ?>)</li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_migrate">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Migrationen jetzt ausfuehren?');">
                Migrationen ausfuehren
            </button>
        </form>
    </div>
<?php endif; ?>
