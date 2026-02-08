<?php
/**
 * Installer Step 4: Admin user creation.
 * Variables: $error, $success, $formData
 */
$esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<h2 style="margin-bottom: 1rem;">Schritt 4: Admin-Benutzer anlegen</h2>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $esc($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $esc($success) ?></div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=done" class="btn btn-primary">Installation abschliessen</a>
    </div>
<?php else: ?>
    <form method="post" action="?r=install&amp;step=admin">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" class="form-input"
                   value="<?= $esc($formData['name']) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" class="form-input"
                   value="<?= $esc($formData['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" class="form-input" required>
            <div class="form-hint">Mindestens 10 Zeichen</div>
        </div>

        <div class="form-group">
            <label for="password_confirm">Passwort bestaetigen</label>
            <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
        </div>

        <div class="btn-group">
            <a href="?r=install&amp;step=schema" class="btn btn-secondary">Zurueck</a>
            <button type="submit" class="btn btn-primary">Admin erstellen</button>
        </div>
    </form>
<?php endif; ?>
