<?php
/**
 * Admin: Team list view (AP16).
 * Variables: $teams (array)
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$globalRole = $_SESSION['user_role'] ?? '';
?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>Teams verwalten</h1>
            <p class="subtitle">Teams erstellen, Mitglieder zuweisen und Rollen verwalten</p>
        </div>
        <?php if ($globalRole === 'admin'): ?>
        <div>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('create-team-form').style.display = document.getElementById('create-team-form').style.display === 'none' ? 'block' : 'none'">+ Neues Team</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($globalRole === 'admin'): ?>
<div id="create-team-form" class="section-block" style="display: none;">
    <h2>Neues Team erstellen</h2>
    <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_create">
        <?= Security::csrfField() ?>
        <div class="form-group">
            <label for="team-name">Teamname</label>
            <input type="text" id="team-name" name="name" class="form-control" maxlength="100" required>
        </div>
        <div class="form-group">
            <label for="team-description">Beschreibung (optional)</label>
            <input type="text" id="team-description" name="description" class="form-control" maxlength="255">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Team erstellen</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($teams)): ?>
    <div class="section-block">
        <p class="placeholder-text">Keine Teams vorhanden.</p>
    </div>
<?php else: ?>
    <div class="pages-table-wrap responsive-cards">
        <table class="pages-table">
            <thead>
                <tr>
                    <th>Teamname</th>
                    <th>Beschreibung</th>
                    <th>Mitglieder</th>
                    <th>Seiten</th>
                    <th>Aufgaben</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td class="card-cell-title"><?= Security::esc($team['name']) ?></td>
                    <td data-label="Beschreibung"><?= Security::esc($team['description'] ?? '') ?></td>
                    <td data-label="Mitglieder"><?= (int) $team['member_count'] ?></td>
                    <td data-label="Seiten"><?= (int) $team['page_count'] ?></td>
                    <td data-label="Aufgaben"><?= (int) $team['task_count'] ?></td>
                    <td class="card-cell-actions">
                        <a href="<?= Security::esc($baseUrl) ?>/?r=admin_team_edit&amp;id=<?= (int) $team['id'] ?>" class="btn-sm">Bearbeiten</a>
                        <?php if ($globalRole === 'admin'): ?>
                        <form method="post" action="<?= Security::esc($baseUrl) ?>/?r=admin_team_delete"
                              class="inline-form" onsubmit="return confirm('Team &quot;<?= Security::esc($team['name']) ?>&quot; wirklich loeschen? Zugeordnete Seiten und Aufgaben werden global sichtbar.');">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $team['id'] ?>">
                            <button type="submit" class="btn-sm btn-remove">Loeschen</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
