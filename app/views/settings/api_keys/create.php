<?php
/**
 * AP19: API Keys - create form.
 *
 * Variables: $availableScopes, $error, $formData
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$userRole = $_SESSION['user_role'] ?? 'viewer';
$writeScopes = ['tasks:write', 'pages:write', 'comments:write', 'attachments:write', 'webhooks:manage'];
?>
<div class="content-header">
    <h1>Neuer API-Schluessel</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= Security::esc($baseUrl) ?>/?r=settings_api_key_create" class="form" style="max-width:600px">
    <?= Security::csrfField() ?>

    <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= Security::esc($formData['name']) ?>"
               required maxlength="100" placeholder="z.B. CI/CD Integration"
               class="form-control">
        <small class="form-hint">Ein beschreibender Name fuer diesen Schluessel.</small>
    </div>

    <div class="form-group">
        <label>Berechtigungen (Scopes)</label>
        <?php foreach ($availableScopes as $scope => $label): ?>
            <?php
            $isWrite = in_array($scope, $writeScopes, true);
            $disabled = ($userRole === 'viewer' && $isWrite);
            $checked = in_array($scope, $formData['scopes'], true);
            ?>
            <div class="checkbox-row">
                <label class="checkbox-label <?= $disabled ? 'checkbox-disabled' : '' ?>">
                    <input type="checkbox" name="scopes[]" value="<?= Security::esc($scope) ?>"
                           <?= $checked ? 'checked' : '' ?>
                           <?= $disabled ? 'disabled' : '' ?>>
                    <code><?= Security::esc($scope) ?></code>
                    <span class="scope-desc"> - <?= Security::esc($label) ?></span>
                    <?php if ($disabled): ?>
                        <span class="scope-hint">(nicht verfuegbar fuer Viewer)</span>
                    <?php endif; ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Schluessel erstellen</button>
        <a href="<?= Security::esc($baseUrl) ?>/?r=settings_api_keys" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>

<style>
.checkbox-row { margin-bottom: 6px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { margin: 0; }
.checkbox-disabled { opacity: 0.5; cursor: not-allowed; }
.scope-desc { color: var(--text-secondary); font-size: 0.9rem; }
.scope-hint { color: var(--text-tertiary); font-size: 0.8rem; font-style: italic; }
</style>
