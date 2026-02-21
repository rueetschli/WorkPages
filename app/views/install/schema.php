<?php
/**
 * Installer Step 3: Schema creation + migrations.
 * Variables: $error, $success, $migrationCount
 */
$esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<h2 style="margin-bottom: 1rem;">Schritt 3: Datenbank-Schema erstellen</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $esc($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $esc($success) ?></div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=admin" class="btn btn-primary">Weiter: Admin-Benutzer anlegen</a>
    </div>
<?php else: ?>
    <p style="margin-bottom: 1rem;">
        Im naechsten Schritt werden alle Datenbanktabellen erstellt und saemtliche
        Migrationen ausgefuehrt. Das System ist danach sofort voll funktionsfaehig.
    </p>
    <ul style="margin-bottom: 1.5rem; padding-left: 1.5rem;">
        <li><code>app_meta</code> (Versionsverwaltung)</li>
        <li><code>users</code> (Benutzer)</li>
        <li><code>pages</code> (Seiten)</li>
        <li><code>tasks</code>, <code>tags</code>, <code>task_tags</code> (Aufgaben und Tags)</li>
        <li><code>board_columns</code>, <code>boards</code> (Kanban-Boards)</li>
        <li><code>page_tasks</code> (Seiten-Aufgaben-Verknuepfung)</li>
        <li><code>comments</code>, <code>mentions</code> (Kommentare)</li>
        <li><code>activity</code> (Aktivitaetslog)</li>
        <li><code>notifications</code>, <code>notification_settings</code> (Benachrichtigungen)</li>
        <li><code>watchers</code> (Beobachter)</li>
        <li><code>email_outbox</code> (E-Mail-Warteschlange)</li>
        <li><code>teams</code>, <code>team_users</code> (Teams)</li>
        <li><code>attachments</code> (Dateianhang)</li>
        <li><code>page_shares</code> (Freigabelinks)</li>
        <li><code>api_keys</code>, <code>webhook_endpoints</code>, <code>webhook_outbox</code> (API und Webhooks)</li>
        <li><code>system_settings</code> (Systemeinstellungen)</li>
    </ul>

    <form method="post" action="?r=install&amp;step=schema">
        <?= Security::csrfField() ?>
        <div class="btn-group">
            <a href="?r=install&amp;step=database" class="btn btn-secondary">Zurueck</a>
            <button type="submit" class="btn btn-primary">Schema und Migrationen ausfuehren</button>
        </div>
    </form>
<?php endif; ?>
