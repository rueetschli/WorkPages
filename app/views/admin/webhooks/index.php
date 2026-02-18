<?php
/**
 * AP19: Webhooks admin - list view.
 *
 * Variables: $webhooks, $allEvents
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1>Webhooks</h1>
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_create" class="btn btn-primary">Neuer Webhook</a>
</div>

<p style="margin-bottom:1rem;color:var(--text-secondary)">
    Webhooks senden automatisch HTTP-Benachrichtigungen an externe URLs, wenn Ereignisse in WorkPages auftreten.
</p>

<?php if (empty($webhooks)): ?>
<div class="empty-state">
    <p>Noch keine Webhooks konfiguriert.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>URL</th>
                <th>Events</th>
                <th>Team</th>
                <th>Status</th>
                <th>Erstellt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($webhooks as $wh): ?>
            <tr>
                <td><?= Security::esc($wh['name']) ?></td>
                <td><code style="font-size:0.8rem;word-break:break-all"><?= Security::esc($wh['url']) ?></code></td>
                <td>
                    <?php
                    $events = array_filter(array_map('trim', explode(',', $wh['events'])));
                    foreach ($events as $ev):
                    ?>
                        <span class="tag tag-small"><?= Security::esc($ev) ?></span>
                    <?php endforeach; ?>
                </td>
                <td><?= $wh['team_id'] ? Security::esc('Team #' . $wh['team_id']) : 'Global' ?></td>
                <td>
                    <?php if ((int) $wh['is_active'] === 1): ?>
                        <span class="badge badge-success">Aktiv</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td><?= Security::esc(date('d.m.Y', strtotime($wh['created_at']))) ?></td>
                <td style="white-space:nowrap">
                    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_edit&amp;id=<?= (int) $wh['id'] ?>" class="btn btn-small">Bearbeiten</a>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_delete" style="display:inline"
                          onsubmit="return confirm('Webhook wirklich loeschen? Alle zugehoerigen Queue-Eintraege werden ebenfalls geloescht.')">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="webhook_id" value="<?= (int) $wh['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Loeschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="margin-top:1.5rem">
    <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_queue" class="btn btn-secondary">Webhook Queue anzeigen</a>
</div>
