<?php
/**
 * Admin: Team edit view with member management (AP16).
 * Variables: $team, $members, $allUsers, $error
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$globalRole = $_SESSION['user_role'] ?? '';
$existingUserIds = array_map(fn($m) => (int) $m['user_id'], $members);
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Team bearbeiten: <?= Security::esc($team['name']) ?></h1>
            <p class="subtitle"><a href="<?= Security::esc($baseUrl) ?>/?r=admin_teams">Zurueck zur Teamliste</a></p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= Security::esc($error) ?></div>
<?php endif; ?>

<div class="section-block">
    <h2>Team-Details</h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_edit&amp;id=<?= (int) $team['id'] ?>">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="update">
        <div class="form-group">
            <label for="team-name">Teamname</label>
            <input type="text" id="team-name" name="name" class="form-control" maxlength="100" value="<?= Security::esc($team['name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="team-description">Beschreibung (optional)</label>
            <input type="text" id="team-description" name="description" class="form-control" maxlength="255" value="<?= Security::esc($team['description'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="section-block">
    <h2>Mitglieder (<?= count($members) ?>)</h2>

    <?php if (empty($members)): ?>
        <p class="placeholder-text">Noch keine Mitglieder.</p>
    <?php else: ?>
        <div class="pages-table-wrap responsive-cards">
            <table class="pages-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Globale Rolle</th>
                        <th>Team-Rolle</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                    <tr>
                        <td class="card-cell-title"><?= Security::esc($member['user_name']) ?></td>
                        <td data-label="E-Mail"><?= Security::esc($member['user_email']) ?></td>
                        <td data-label="Globale Rolle">
                            <span class="role-badge role-<?= Security::esc($member['global_role']) ?>">
                                <?= Security::esc($member['global_role']) ?>
                            </span>
                        </td>
                        <td data-label="Team-Rolle">
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_edit&amp;id=<?= (int) $team['id'] ?>" class="inline-form">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?= (int) $member['user_id'] ?>">
                                <select name="team_role" class="form-control form-control-inline" onchange="this.form.submit()">
                                    <option value="team_admin" <?= $member['role'] === 'team_admin' ? 'selected' : '' ?>>Team-Admin</option>
                                    <option value="team_member" <?= $member['role'] === 'team_member' ? 'selected' : '' ?>>Team-Mitglied</option>
                                    <option value="team_viewer" <?= $member['role'] === 'team_viewer' ? 'selected' : '' ?>>Team-Viewer</option>
                                </select>
                            </form>
                        </td>
                        <td class="card-cell-actions">
                            <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_edit&amp;id=<?= (int) $team['id'] ?>"
                                  class="inline-form" onsubmit="return confirm('Mitglied wirklich entfernen?');">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="remove_member">
                                <input type="hidden" name="user_id" value="<?= (int) $member['user_id'] ?>">
                                <button type="submit" class="btn-sm btn-remove">Entfernen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="section-block">
    <h2>Mitglied hinzufuegen</h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_edit&amp;id=<?= (int) $team['id'] ?>">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="add_member">
        <div class="form-row">
            <div class="form-group form-group-inline">
                <label for="add-user">Benutzer</label>
                <select id="add-user" name="user_id" class="form-control" required>
                    <option value="">-- Benutzer waehlen --</option>
                    <?php foreach ($allUsers as $u): ?>
                        <?php if (!in_array((int) $u['id'], $existingUserIds, true)): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= Security::esc($u['name']) ?> (<?= Security::esc($u['email']) ?>)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group-inline">
                <label for="add-role">Team-Rolle</label>
                <select id="add-role" name="team_role" class="form-control">
                    <option value="team_member">Team-Mitglied</option>
                    <option value="team_admin">Team-Admin</option>
                    <option value="team_viewer">Team-Viewer</option>
                </select>
            </div>
            <div class="form-group form-group-inline" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Hinzufuegen</button>
            </div>
        </div>
    </form>
</div>
