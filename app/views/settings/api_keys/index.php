<?php
/**
 * AP19: API Keys - list view.
 *
 * Variables: $keys, $availableScopes, $newKey
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>
<div class="content-header">
    <h1><?= Security::esc(t('settings.api_keys')) ?></h1>
    <a href="<?= Security::esc($baseUrl) ?>/?r=settings_api_key_create" class="btn btn-primary"><?= Security::esc(t('settings.api_keys_new')) ?></a>
</div>

<?php if ($newKey): ?>
<div class="alert alert-success" style="word-break:break-all">
    <strong><?= Security::esc(t('settings.api_key_created')) ?></strong> <?= Security::esc(t('settings.api_key_copy_now')) ?><br>
    <code style="display:block;margin-top:8px;padding:8px;background:rgba(0,0,0,0.05);border-radius:4px;font-size:0.9rem;user-select:all"><?= Security::esc($newKey) ?></code>
</div>
<?php endif; ?>

<p style="margin-bottom:1rem;color:var(--text-secondary)">
    <?= Security::esc(t('settings.api_keys_description')) ?>
</p>

<?php if (empty($keys)): ?>
<div class="empty-state">
    <p><?= Security::esc(t('settings.api_keys_empty')) ?></p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= Security::esc(t('labels.name')) ?></th>
                <th><?= Security::esc(t('settings.api_key_prefix')) ?></th>
                <th><?= Security::esc(t('settings.api_key_scopes')) ?></th>
                <th><?= Security::esc(t('labels.created')) ?></th>
                <th><?= Security::esc(t('settings.api_key_last_used')) ?></th>
                <th><?= Security::esc(t('labels.status')) ?></th>
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
                        <span class="badge badge-danger"><?= Security::esc(t('labels.revoked')) ?></span>
                    <?php else: ?>
                        <span class="badge badge-success"><?= Security::esc(t('labels.active')) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$key['revoked_at']): ?>
                    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=settings_api_key_revoke"
                          onsubmit="return confirm('<?= Security::esc(t('messages.confirm_revoke_key')) ?>')">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger"><?= Security::esc(t('actions.revoke')) ?></button>
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
    <h3 style="margin-bottom:0.5rem"><?= Security::esc(t('settings.api_usage')) ?></h3>
    <p style="margin-bottom:0.5rem;color:var(--text-secondary)"><?= Security::esc(t('settings.api_auth_header')) ?></p>
    <code style="display:block;padding:8px;background:rgba(0,0,0,0.05);border-radius:4px">Authorization: Bearer wp_xxxxxxxx_...</code>
    <p style="margin-top:0.5rem;color:var(--text-secondary)">Base URL: <code><?= Security::esc($baseUrl) ?>/api/v1/</code></p>
</div>
