<?php
/**
 * Admin: User list view.
 * Variables: $users (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$flashError = $_SESSION['_flash_error'] ?? null;
unset($_SESSION['_flash_error']);
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1><?= Security::esc(t('admin.users_title')) ?></h1>
            <p class="subtitle"><?= Security::esc(t('admin.users_subtitle')) ?></p>
        </div>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_user_create" class="btn btn-primary"><?= Security::esc(t('admin.user_new')) ?></a>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-error"><?= Security::esc($flashError) ?></div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="section-block">
        <p class="placeholder-text"><?= Security::esc(t('messages.no_users')) ?></p>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table">
            <thead>
                <tr>
                    <th><?= Security::esc(t('labels.name')) ?></th>
                    <th><?= Security::esc(t('labels.email')) ?></th>
                    <th><?= Security::esc(t('labels.role')) ?></th>
                    <th><?= Security::esc(t('labels.status')) ?></th>
                    <th><?= Security::esc(t('labels.last_login')) ?></th>
                    <th><?= Security::esc(t('labels.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="card-cell-title"><?= Security::esc($u['name']) ?></td>
                    <td data-label="<?= Security::esc(t('labels.email')) ?>"><?= Security::esc($u['email']) ?></td>
                    <td data-label="<?= Security::esc(t('labels.role')) ?>">
                        <span class="role-badge role-<?= Security::esc($u['role']) ?>">
                            <?= Security::esc($u['role']) ?>
                        </span>
                    </td>
                    <td data-label="<?= Security::esc(t('labels.status')) ?>">
                        <?php if ((int) ($u['is_active'] ?? 1) === 1): ?>
                            <span class="status-badge status-doing"><?= Security::esc(t('labels.active')) ?></span>
                        <?php else: ?>
                            <span class="status-badge status-backlog"><?= Security::esc(t('labels.inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= Security::esc(t('labels.last_login')) ?>">
                        <?php if ($u['last_login_at']): ?>
                            <?= Security::esc(date('d.m.Y H:i', strtotime($u['last_login_at']))) ?>
                        <?php else: ?>
                            <span class="text-muted"><?= Security::esc(t('labels.never')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="card-cell-actions">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_user_edit&amp;id=<?= (int) $u['id'] ?>" class="btn-sm"><?= Security::esc(t('actions.edit')) ?></a>
                        <?php if ((int) ($u['is_active'] ?? 1) === 1 && (int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_user_disable&amp;id=<?= (int) $u['id'] ?>"
                                  class="inline-form" onsubmit="return confirm('<?= Security::esc(t('messages.confirm_deactivate_user')) ?>');">
                                <?= Security::csrfField() ?>
                                <button type="submit" class="btn-sm btn-remove"><?= Security::esc(t('actions.deactivate')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
