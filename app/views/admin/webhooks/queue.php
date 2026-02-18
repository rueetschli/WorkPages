<?php
/**
 * AP19: Webhook queue admin view.
 *
 * Variables: $counts, $entries, $statusFilter
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1>Webhook Queue</h1>
    <div style="display:flex;gap:8px">
        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue_send">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-primary">Queue jetzt senden</button>
        </form>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhooks" class="btn btn-secondary">Webhooks verwalten</a>
    </div>
</div>

<div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue"
       class="stat-card <?= $statusFilter === null ? 'stat-card-active' : '' ?>">
        <span class="stat-label">Gesamt</span>
        <span class="stat-value"><?= array_sum($counts) ?></span>
    </a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue&amp;status=pending"
       class="stat-card <?= $statusFilter === 'pending' ? 'stat-card-active' : '' ?>">
        <span class="stat-label">Ausstehend</span>
        <span class="stat-value"><?= (int) $counts['pending'] ?></span>
    </a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue&amp;status=sent"
       class="stat-card <?= $statusFilter === 'sent' ? 'stat-card-active' : '' ?>">
        <span class="stat-label">Gesendet</span>
        <span class="stat-value"><?= (int) $counts['sent'] ?></span>
    </a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue&amp;status=failed"
       class="stat-card <?= $statusFilter === 'failed' ? 'stat-card-active' : '' ?>">
        <span class="stat-label">Fehlgeschlagen</span>
        <span class="stat-value"><?= (int) $counts['failed'] ?></span>
    </a>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue&amp;status=dead"
       class="stat-card <?= $statusFilter === 'dead' ? 'stat-card-active' : '' ?>">
        <span class="stat-label">Dead Letter</span>
        <span class="stat-value"><?= (int) $counts['dead'] ?></span>
    </a>
</div>

<?php if (empty($entries)): ?>
<div class="empty-state">
    <p>Keine Eintraege<?= $statusFilter ? ' mit Status "' . Security::esc($statusFilter) . '"' : '' ?>.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Endpoint</th>
                <th>Event</th>
                <th>Status</th>
                <th>Versuche</th>
                <th>Naechster Versuch</th>
                <th>Letzter Fehler</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= (int) $entry['id'] ?></td>
                <td><?= Security::esc($entry['endpoint_name'] ?? 'Unbekannt') ?></td>
                <td><code><?= Security::esc($entry['event_name']) ?></code></td>
                <td>
                    <?php
                    $statusClass = match($entry['status']) {
                        'sent'    => 'badge-success',
                        'pending' => 'badge-info',
                        'failed'  => 'badge-warning',
                        'dead'    => 'badge-danger',
                        default   => '',
                    };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= Security::esc($entry['status']) ?></span>
                </td>
                <td><?= (int) $entry['attempts'] ?></td>
                <td>
                    <?php if ($entry['status'] === 'failed'): ?>
                        <?= Security::esc(date('d.m.Y H:i', strtotime($entry['next_attempt_at']))) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($entry['last_error']): ?>
                        <span title="<?= Security::esc($entry['last_error']) ?>" style="cursor:help;color:var(--danger)">
                            <?= Security::esc(mb_substr($entry['last_error'], 0, 40, 'UTF-8')) ?>
                            <?= mb_strlen($entry['last_error'], 'UTF-8') > 40 ? '...' : '' ?>
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= Security::esc(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></td>
                <td>
                    <?php if ($entry['status'] === 'dead'): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue_retry" style="display:inline">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>">
                        <button type="submit" class="btn btn-small">Erneut versuchen</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
.stat-card {
    display: flex;
    flex-direction: column;
    padding: 12px 20px;
    background: var(--bg-secondary);
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    border: 2px solid transparent;
    transition: border-color 0.2s;
    min-width: 100px;
    text-align: center;
}
.stat-card:hover { border-color: var(--primary); }
.stat-card-active { border-color: var(--primary); background: var(--bg-primary); }
.stat-label { font-size: 0.8rem; color: var(--text-secondary); }
.stat-value { font-size: 1.4rem; font-weight: 600; }
</style>
