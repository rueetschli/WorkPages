<?php
/**
 * Admin email queue view (AP15).
 * Variables: $entries (array), $pendingCount (int), $failedCount (int)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <h1>E-Mail Warteschlange</h1>
        <div class="page-actions">
            <?php if ($pendingCount > 0): ?>
            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_email_send" class="inline-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn btn-primary">Jetzt versenden (<?= (int) $pendingCount ?> ausstehend)</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="filter-row" style="margin-bottom: var(--sp-4);">
    <div class="filter-group">
        <strong>Ausstehend:</strong> <?= (int) $pendingCount ?>
    </div>
    <div class="filter-group">
        <strong>Fehlgeschlagen:</strong> <?= (int) $failedCount ?>
    </div>
    <div class="filter-group">
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_email_digest_daily" class="inline-form">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-secondary btn-sm-pad">Tages-Digest erstellen</button>
        </form>
    </div>
    <div class="filter-group">
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_email_digest_weekly" class="inline-form">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-secondary btn-sm-pad">Wochen-Digest erstellen</button>
        </form>
    </div>
</div>

<?php if (empty($entries)): ?>
    <div class="section-block">
        <p class="placeholder-text">Keine E-Mails in der Warteschlange.</p>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
    <table class="pages-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Empfaenger</th>
                <th>Betreff</th>
                <th>Status</th>
                <th>Versuche</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td data-label="ID"><?= (int) $entry['id'] ?></td>
                <td data-label="Empfaenger">
                    <?= Security::esc($entry['to_email']) ?>
                    <?php if (!empty($entry['user_name'])): ?>
                        <br><small class="text-muted"><?= Security::esc($entry['user_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td data-label="Betreff"><?= Security::esc(mb_substr($entry['subject'], 0, 60, 'UTF-8')) ?></td>
                <td data-label="Status">
                    <?php
                    $statusClass = '';
                    $statusLabel = $entry['status'];
                    if ($entry['status'] === 'pending') { $statusClass = 'status-badge-pending'; $statusLabel = 'Ausstehend'; }
                    elseif ($entry['status'] === 'sent')  { $statusClass = 'status-badge-sent'; $statusLabel = 'Gesendet'; }
                    elseif ($entry['status'] === 'failed') { $statusClass = 'status-badge-failed'; $statusLabel = 'Fehlgeschlagen'; }
                    ?>
                    <span class="status-badge <?= $statusClass ?>"><?= Security::esc($statusLabel) ?></span>
                    <?php if ($entry['status'] === 'failed' && !empty($entry['last_error'])): ?>
                        <br><small class="text-muted"><?= Security::esc(mb_substr($entry['last_error'], 0, 80, 'UTF-8')) ?></small>
                    <?php endif; ?>
                </td>
                <td data-label="Versuche"><?= (int) $entry['attempts'] ?></td>
                <td data-label="Erstellt"><?= Security::esc(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></td>
                <td class="card-cell-actions">
                    <?php if ($entry['status'] === 'failed'): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_email_retry&amp;id=<?= (int) $entry['id'] ?>" class="inline-form">
                        <?= Security::csrfField() ?>
                        <button type="submit" class="btn-sm btn-primary">Retry</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
