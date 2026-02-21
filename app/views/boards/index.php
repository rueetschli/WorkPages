<?php
/**
 * Boards index view - List all boards grouped by team (AP21).
 *
 * Variables:
 *   $grouped        - array: boards grouped by team name (key 'global' for team_id=NULL)
 *   $taskCounts     - array: board_id => task count
 *   $availableTeams - array: teams for board creation dropdown
 *   $canCreate      - bool: can the user create boards?
 */
$esc     = [Security::class, 'esc'];
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= $esc(t('board.all_boards')) ?></h1>
        <?php if ($canCreate): ?>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('board-create-form').style.display = document.getElementById('board-create-form').style.display === 'none' ? 'block' : 'none'">
            <?= $esc(t('board.new_board')) ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($canCreate): ?>
<div id="board-create-form" style="display:none;" class="section-block">
    <h2><?= $esc(t('board.create_title')) ?></h2>
    <form method="post" action="<?= $esc($baseUrl) ?>/?r=board_create">
        <?= Security::csrfField() ?>
        <div class="form-group">
            <label for="board-name" class="form-label"><?= $esc(t('labels.name')) ?> *</label>
            <input type="text" id="board-name" name="name" class="form-input" required maxlength="150" placeholder="<?= $esc(t('placeholders.board_name')) ?>">
        </div>
        <div class="form-group">
            <label for="board-desc" class="form-label"><?= $esc(t('labels.description')) ?></label>
            <input type="text" id="board-desc" name="description" class="form-input" maxlength="255" placeholder="<?= $esc(t('placeholders.board_description')) ?>">
        </div>
        <div class="form-group">
            <label for="board-team" class="form-label"><?= $esc(t('labels.team')) ?></label>
            <select id="board-team" name="team_id" class="form-input">
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <option value=""><?= $esc(t('board.no_team')) ?></option>
                <?php endif; ?>
                <?php foreach ($availableTeams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"><?= $esc($team['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esc(t('board.create_button')) ?></button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('board-create-form').style.display='none'"><?= $esc(t('actions.cancel')) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($grouped) || (count($grouped) === 1 && empty($grouped['global']))): ?>
<div class="section-block">
    <p class="placeholder-text"><?= $esc(t('messages.no_boards')) ?></p>
</div>
<?php else: ?>

<?php foreach ($grouped as $groupName => $boards): ?>
    <?php if (empty($boards)) continue; ?>
    <section class="section-block">
        <h2><?= $groupName === 'global' ? $esc(t('board.general')) : $esc($groupName) ?></h2>
        <div class="card-grid">
            <?php foreach ($boards as $board):
                $boardId = (int) $board['id'];
                $count   = $taskCounts[$boardId] ?? 0;
            ?>
            <a href="<?= $esc($baseUrl) ?>/?r=board_view&amp;id=<?= $boardId ?>" class="card">
                <div class="card-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                </div>
                <h3 class="card-title"><?= $esc($board['name']) ?></h3>
                <p class="card-desc">
                    <?php if ($board['description']): ?>
                        <?= $esc($board['description']) ?>
                    <?php else: ?>
                        <?= $esc(t('board.tasks_count', ['count' => $count])) ?>
                    <?php endif; ?>
                </p>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php endif; ?>
