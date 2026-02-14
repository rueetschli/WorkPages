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
            <h1>Benutzerverwaltung</h1>
            <p class="subtitle">Benutzer verwalten und Rollen zuweisen</p>
        </div>
        <div>
            <a href="<?= Security::esc($baseUrl) ?>/?r=admin_user_create" class="btn btn-primary">+ Neuer Benutzer</a>
        </div>
    </div>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-error"><?= Security::esc($flashError) ?></div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="section-block">
        <p class="placeholder-text">Keine Benutzer vorhanden.</p>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Status</th>
                    <th>Letzter Login</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="card-cell-title"><?= Security::esc($u['name']) ?></td>
                    <td data-label="E-Mail"><?= Security::esc($u['email']) ?></td>
                    <td data-label="Rolle">
                        <span class="role-badge role-<?= Security::esc($u['role']) ?>">
                            <?= Security::esc($u['role']) ?>
                        </span>
                    </td>
                    <td data-label="Status">
                        <?php if ((int) ($u['is_active'] ?? 1) === 1): ?>
                            <span class="status-badge status-doing">Aktiv</span>
                        <?php else: ?>
                            <span class="status-badge status-backlog">Inaktiv</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Letzter Login">
                        <?php if ($u['last_login_at']): ?>
                            <?= Security::esc(date('d.m.Y H:i', strtotime($u['last_login_at']))) ?>
                        <?php else: ?>
                            <span class="text-muted">Nie</span>
                        <?php endif; ?>
                    </td>
                    <td class="card-cell-actions">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_user_edit&amp;id=<?= (int) $u['id'] ?>" class="btn-sm">Bearbeiten</a>
                        <?php if ((int) ($u['is_active'] ?? 1) === 1 && (int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_user_disable&amp;id=<?= (int) $u['id'] ?>"
                                  class="inline-form" onsubmit="return confirm('Benutzer wirklich deaktivieren?');">
                                <?= Security::csrfField() ?>
                                <button type="submit" class="btn-sm btn-remove">Deaktivieren</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
