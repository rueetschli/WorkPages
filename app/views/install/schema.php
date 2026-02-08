<?php
/**
 * Installer Step 3: Schema creation.
 * Variables: $error, $success
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
        Im naechsten Schritt werden alle Datenbanktabellen erstellt.
        Folgende Tabellen werden angelegt:
    </p>
    <ul style="margin-bottom: 1.5rem; padding-left: 1.5rem;">
        <li><code>app_meta</code> (Versionsverwaltung)</li>
        <li><code>users</code> (Benutzer)</li>
        <li><code>pages</code> (Seiten)</li>
        <li><code>tasks</code> (Aufgaben)</li>
        <li><code>tags</code>, <code>task_tags</code> (Tags)</li>
        <li><code>page_tasks</code> (Seiten-Aufgaben-Verknuepfung)</li>
        <li><code>comments</code> (Kommentare)</li>
        <li><code>activity</code> (Aktivitaetslog)</li>
        <li><code>page_shares</code> (Freigabelinks)</li>
    </ul>

    <form method="post" action="?r=install&amp;step=schema">
        <?= Security::csrfField() ?>
        <div class="btn-group">
            <a href="?r=install&amp;step=database" class="btn btn-secondary">Zurueck</a>
            <button type="submit" class="btn btn-primary">Schema jetzt erstellen</button>
        </div>
    </form>
<?php endif; ?>
