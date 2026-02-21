<?php
/**
 * Notification settings view (AP15).
 * Variables: $settings (array), $error (string|null)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1><?= Security::esc(t('settings.notifications_title')) ?></h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=settings_notifications" class="form-stack">
    <?= Security::csrfField() ?>

    <!-- E-Mail Einstellungen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend"><?= Security::esc(t('settings.email_notifications')) ?></legend>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="email_enabled" value="1"
                    <?= !empty($settings['email_enabled']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.email_notifications_enabled')) ?>
            </label>
        </div>

        <div class="form-group">
            <label for="email_mode" class="form-label"><?= Security::esc(t('settings.send_mode')) ?></label>
            <select id="email_mode" name="email_mode" class="form-input">
                <option value="immediate" <?= ($settings['email_mode'] ?? '') === 'immediate' ? 'selected' : '' ?>><?= Security::esc(t('settings.mode_immediate')) ?></option>
                <option value="digest_daily" <?= ($settings['email_mode'] ?? '') === 'digest_daily' ? 'selected' : '' ?>><?= Security::esc(t('settings.mode_daily')) ?></option>
                <option value="digest_weekly" <?= ($settings['email_mode'] ?? '') === 'digest_weekly' ? 'selected' : '' ?>><?= Security::esc(t('settings.mode_weekly')) ?></option>
                <option value="digest_off" <?= ($settings['email_mode'] ?? '') === 'digest_off' ? 'selected' : '' ?>><?= Security::esc(t('settings.mode_off')) ?></option>
            </select>
        </div>

        <div class="form-group">
            <label for="email_address_override" class="form-label"><?= Security::esc(t('settings.alt_email')) ?></label>
            <input type="email" id="email_address_override" name="email_address_override"
                   class="form-input" placeholder="<?= Security::esc(t('placeholders.default_account_email')) ?>"
                   value="<?= Security::esc($settings['email_address_override'] ?? '') ?>">
            <small class="form-hint"><?= Security::esc(t('settings.alt_email_hint')) ?></small>
        </div>
    </fieldset>

    <!-- Auto-Watch Einstellungen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend"><?= Security::esc(t('settings.auto_watch')) ?></legend>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="watch_auto_on_create" value="1"
                    <?= !empty($settings['watch_auto_on_create']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.watch_on_create')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="watch_auto_on_comment" value="1"
                    <?= !empty($settings['watch_auto_on_comment']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.watch_on_comment')) ?>
            </label>
        </div>
    </fieldset>

    <!-- Benachrichtigungstypen -->
    <fieldset class="section-block">
        <legend class="fieldset-legend"><?= Security::esc(t('settings.notification_types')) ?></legend>
        <p class="form-hint" style="margin-bottom: var(--sp-3);"><?= Security::esc(t('settings.notification_types_hint')) ?></p>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_mentions" value="1"
                    <?= !empty($settings['notify_on_mentions']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_mentions')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_assignments" value="1"
                    <?= !empty($settings['notify_on_assignments']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_assignments')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_comments" value="1"
                    <?= !empty($settings['notify_on_comments']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_comments')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_task_updates" value="1"
                    <?= !empty($settings['notify_on_task_updates']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_task_updates')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_page_updates" value="1"
                    <?= !empty($settings['notify_on_page_updates']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_page_updates')) ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-checkbox-label">
                <input type="checkbox" name="notify_on_moves" value="1"
                    <?= !empty($settings['notify_on_moves']) ? 'checked' : '' ?>>
                <?= Security::esc(t('settings.notify_moves')) ?>
            </label>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= Security::esc(t('actions.save_settings')) ?></button>
    </div>
</form>
