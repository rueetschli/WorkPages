<?php
/**
 * AP19: API Keys - list view.
 *
 * Variables: $keys, $availableScopes, $newKey
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1>API-Schluessel</h1>
    <a href="<?= Security::esc($baseUrl) ?>/?r=settings_api_key_create" class="btn btn-primary">Neuer Schluessel</a>
</div>

<?php if ($newKey): ?>
<div class="alert alert-success" style="word-break:break-all">
    <strong>Neuer API-Schluessel erstellt.</strong> Kopieren Sie ihn jetzt - er wird nicht erneut angezeigt:<br>
    <code style="display:block;margin-top:8px;padding:8px;background:rgba(0,0,0,0.05);border-radius:4px;font-size:0.9rem;user-select:all"><?= Security::esc($newKey) ?></code>
</div>
<?php endif; ?>

<p style="margin-bottom:1rem;color:var(--text-secondary)">
    API-Schluessel ermoeglichen externen Anwendungen den Zugriff auf die WorkPages API.
    Jeder Schluessel ist an Ihr Benutzerkonto gebunden.
</p>

<?php if (empty($keys)): ?>
<div class="empty-state">
    <p>Noch keine API-Schluessel vorhanden.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Praefix</th>
                <th>Scopes</th>
                <th>Erstellt</th>
                <th>Zuletzt verwendet</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($keys as $key): ?>
            <tr class="<?= $key['revoked_at'] ? 'row-muted' : '' ?>">
                <td><?= Security::esc($key['name']) ?></td>
                <td><code>wp_<?= Security::esc($key['key_prefix']) ?>_...</code></td>
                <td>
                    <?php
                    $scopes = array_filter(array_map('trim', explode(',', $key['scopes'])));
                    foreach ($scopes as $scope):
                    ?>
                        <span class="tag tag-small"><?= Security::esc($scope) ?></span>
                    <?php endforeach; ?>
                </td>
                <td><?= Security::esc(date('d.m.Y H:i', strtotime($key['created_at']))) ?></td>
                <td><?= $key['last_used_at'] ? Security::esc(date('d.m.Y H:i', strtotime($key['last_used_at']))) : '-' ?></td>
                <td>
                    <?php if ($key['revoked_at']): ?>
                        <span class="badge badge-danger">Widerrufen</span>
                    <?php else: ?>
                        <span class="badge badge-success">Aktiv</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$key['revoked_at']): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=settings_api_key_revoke"
                          onsubmit="return confirm('Schluessel wirklich widerrufen? Dies kann nicht rueckgaengig gemacht werden.')">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Widerrufen</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="margin-top:2rem;padding:1rem;background:var(--bg-secondary);border-radius:8px">
    <h3 style="margin-bottom:0.5rem">API-Nutzung</h3>
    <p style="margin-bottom:0.5rem;color:var(--text-secondary)">Authentifizierung via HTTP-Header:</p>
    <code style="display:block;padding:8px;background:rgba(0,0,0,0.05);border-radius:4px">Authorization: Bearer wp_xxxxxxxx_...</code>
    <p style="margin-top:0.5rem;color:var(--text-secondary)">Base URL: <code><?= Security::esc($baseUrl) ?>/api/v1/</code></p>
</div>
