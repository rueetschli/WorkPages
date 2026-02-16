<?php
/**
 * Notification settings view (AP15).
 * Variables: $settings (array), $error (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1>Benachrichtigungseinstellungen</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=settings_notifications" class="form-stack">
    <?= Security::csrfField() ?>

    <!-- E-Mail Einstellungen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend">E-Mail Benachrichtigungen</legend>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="email_enabled" value="1"
                    <?= !empty($settings['email_enabled']) ? 'checked' : '' ?>>
                E-Mail Benachrichtigungen aktiviert
            </label>
        </div>

        <div class="form-group">
            <label for="email_mode" class="form-label">Versandmodus</label>
            <select id="email_mode" name="email_mode" class="form-input">
                <option value="immediate" <?= ($settings['email_mode'] ?? '') === 'immediate' ? 'selected' : '' ?>>Sofort</option>
                <option value="digest_daily" <?= ($settings['email_mode'] ?? '') === 'digest_daily' ? 'selected' : '' ?>>Tages-Zusammenfassung</option>
                <option value="digest_weekly" <?= ($settings['email_mode'] ?? '') === 'digest_weekly' ? 'selected' : '' ?>>Wochen-Zusammenfassung</option>
                <option value="digest_off" <?= ($settings['email_mode'] ?? '') === 'digest_off' ? 'selected' : '' ?>>Keine E-Mails</option>
            </select>
        </div>

        <div class="form-group">
            <label for="email_address_override" class="form-label">Alternative E-Mail Adresse (optional)</label>
            <input type="email" id="email_address_override" name="email_address_override"
                   class="form-input" placeholder="Standard: Account E-Mail"
                   value="<?= Security::esc($settings['email_address_override'] ?? '') ?>">
            <small class="form-hint">Leer lassen fuer die E-Mail Adresse des Benutzerkontos.</small>
        </div>
    </fieldset>

    <!-- Auto-Watch Einstellungen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend">Automatisches Beobachten</legend>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="watch_auto_on_create" value="1"
                    <?= !empty($settings['watch_auto_on_create']) ? 'checked' : '' ?>>
                Erstellte Seiten/Aufgaben automatisch beobachten
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="watch_auto_on_comment" value="1"
                    <?= !empty($settings['watch_auto_on_comment']) ? 'checked' : '' ?>>
                Kommentierte Seiten/Aufgaben automatisch beobachten
            </label>
        </div>
    </fieldset>

    <!-- Benachrichtigungstypen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend">Benachrichtigungstypen</legend>
        <p class="form-hint" style="margin-bottom: var(--sp-3);">Welche Ereignisse sollen Benachrichtigungen ausloesen?</p>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_mentions" value="1"
                    <?= !empty($settings['notify_on_mentions']) ? 'checked' : '' ?>>
                Erwaehnungen (@mention)
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_assignments" value="1"
                    <?= !empty($settings['notify_on_assignments']) ? 'checked' : '' ?>>
                Aufgaben-Zuweisungen
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_comments" value="1"
                    <?= !empty($settings['notify_on_comments']) ? 'checked' : '' ?>>
                Kommentare
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_task_updates" value="1"
                    <?= !empty($settings['notify_on_task_updates']) ? 'checked' : '' ?>>
                Aufgaben-Aenderungen
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_page_updates" value="1"
                    <?= !empty($settings['notify_on_page_updates']) ? 'checked' : '' ?>>
                Seiten-Aenderungen
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_moves" value="1"
                    <?= !empty($settings['notify_on_moves']) ? 'checked' : '' ?>>
                Board-Verschiebungen
            </label>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
    </div>
</form>
