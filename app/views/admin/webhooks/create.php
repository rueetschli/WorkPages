<?php
/**
 * AP19: Webhooks admin - create form.
 *
 * Variables: $allEvents, $teams, $error, $formData
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1>Neuer Webhook</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_webhook_create" class="form" style="max-width:600px">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= Security::esc($formData['name']) ?>"
               required maxlength="100" class="form-control" placeholder="z.B. Slack Notifications">
    </div>

    <div class="form-group">
        <label for="url">URL</label>
        <input type="url" id="url" name="url" value="<?= Security::esc($formData['url']) ?>"
               required class="form-control" placeholder="https://example.com/webhook">
        <small class="form-hint">Die URL, an die Webhook-Payloads gesendet werden (POST, JSON).</small>
    </div>

    <div class="form-group">
        <label for="team_id">Team (optional)</label>
        <select id="team_id" name="team_id" class="form-control">
            <option value="">Global (alle Teams)</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"
                    <?= ($formData['team_id'] == $team['id']) ? 'selected' : '' ?>>
                    <?= Security::esc($team['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="form-hint">Einschraenkung auf ein bestimmtes Team. Leer = globaler Webhook.</small>
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

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Webhook erstellen</button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_webhooks" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>

<style>
.checkbox-row { margin-bottom: 4px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
</style>
