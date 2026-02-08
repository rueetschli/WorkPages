<?php
/**
 * Installer Step 2: Database configuration.
 * Variables: $error, $success, $formData
 */
$esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<h2 style="margin-bottom: 1rem;">Schritt 2: Datenbank konfigurieren</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $esc($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $esc($success) ?></div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=schema" class="btn btn-primary">Weiter: Schema erstellen</a>
    </div>
<?php else: ?>
    <form method="post" action="?r=install&amp;step=database">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="DB_HOST">Datenbank Host</label>
            <input type="text" id="DB_HOST" name="DB_HOST" class="form-input"
                   value="<?= $esc($formData['DB_HOST']) ?>" required>
            <div class="form-hint">z.B. localhost oder 127.0.0.1</div>
        </div>

        <div class="form-group">
            <label for="DB_NAME">Datenbankname</label>
            <input type="text" id="DB_NAME" name="DB_NAME" class="form-input"
                   value="<?= $esc($formData['DB_NAME']) ?>" required>
        </div>

        <div class="form-group">
            <label for="DB_USER">Datenbank Benutzer</label>
            <input type="text" id="DB_USER" name="DB_USER" class="form-input"
                   value="<?= $esc($formData['DB_USER']) ?>" required>
        </div>

        <div class="form-group">
            <label for="DB_PASS">Datenbank Passwort</label>
            <input type="password" id="DB_PASS" name="DB_PASS" class="form-input"
                   value="<?= $esc($formData['DB_PASS']) ?>">
        </div>

        <div class="form-group">
            <label for="BASE_URL">Base URL</label>
            <input type="url" id="BASE_URL" name="BASE_URL" class="form-input"
                   value="<?= $esc($formData['BASE_URL']) ?>" required>
            <div class="form-hint">z.B. https://meinedomain.ch/public (ohne Slash am Ende)</div>
        </div>

        <div class="btn-group">
            <a href="?r=install&amp;step=environment" class="btn btn-secondary">Zurueck</a>
            <button type="submit" class="btn btn-primary">Verbindung testen und speichern</button>
        </div>
    </form>
<?php endif; ?>
