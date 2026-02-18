<?php
/**
 * AP19: Webhooks admin - edit form.
 *
 * Variables: $webhook, $allEvents, $teams, $error, $formData
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1>Webhook bearbeiten</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_edit&amp;id=<?= (int) $webhook['id'] ?>" class="form" style="max-width:600px">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= Security::esc($formData['name']) ?>"
               required maxlength="100" class="form-control">
    </div>

    <div class="form-group">
        <label for="url">URL</label>
        <input type="url" id="url" name="url" value="<?= Security::esc($formData['url']) ?>"
               required class="form-control">
    </div>

    <div class="form-group">
        <label>Events</label>
        <?php foreach ($allEvents as $event): ?>
            <div class="checkbox-row">
                <label class="checkbox-label">
                    <input type="checkbox" name="events[]" value="<?= Security::esc($event) ?>"
                           <?= in_array($event, $formData['events'], true) ? 'checked' : '' ?>>
                    <code><?= Security::esc($event) ?></code>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1"
                   <?= $formData['is_active'] ? 'checked' : '' ?>>
            Webhook aktiv
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhooks" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>

<div style="margin-top:2rem;padding:1rem;background:var(--bg-secondary);border-radius:8px">
    <h3 style="margin-bottom:0.5rem">Secret</h3>
    <p style="color:var(--text-secondary);margin-bottom:0.5rem">
        Das Secret wird zur HMAC-SHA256-Signierung der Payloads verwendet.
        Praefix: <code><?= Security::esc(substr($webhook['secret'], 0, 8)) ?>...</code>
    </p>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_regen_secret"
          onsubmit="return confirm('Secret wirklich erneuern? Das alte Secret wird ungueltig.')">
        <?= Security::csrfField() ?>
        <input type="hidden" name="webhook_id" value="<?= (int) $webhook['id'] ?>">
        <button type="submit" class="btn btn-small btn-warning">Secret erneuern</button>
    </form>
</div>

<style>
.checkbox-row { margin-bottom: 4px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
</style>
