<?php
/**
 * Home view - Dashboard / landing page.
 */
$baseUrl  = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
$userName = Security::esc($_SESSION['user_name'] ?? '');
?>

<div class="page-header">
    <h1>Willkommen, <?= $userName ?></h1>
    <p class="subtitle">Ihr Arbeitsbereich fuer Seiten, Aufgaben und Entscheidungen.</p>
</div>

<!-- Quick-access cards -->
<div class="card-grid">

    <a href="<?= Security::esc($baseUrl) ?>/?r=pages" class="card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <h3 class="card-title">Pages</h3>
        <p class="card-desc">Wissensseiten erstellen, organisieren und durchsuchen.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <h3 class="card-title">Tasks</h3>
        <p class="card-desc">Aufgaben erstellen, verwalten und nachverfolgen.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=board" class="card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        </div>
        <h3 class="card-title">Board</h3>
        <p class="card-desc">Kanban-Board fuer visuelle Aufgabenverwaltung.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=search" class="card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <h3 class="card-title">Suche</h3>
        <p class="card-desc">Seiten und Aufgaben schnell finden.</p>
    </a>

</div>

<!-- Recent tasks -->
<?php
    $recentTasks = Task::all();
    $recentTasks = array_slice($recentTasks, 0, 5);
?>
<section class="section-block">
    <h2>Aktuelle Aufgaben</h2>
    <?php if (empty($recentTasks)): ?>
        <p class="placeholder-text">Noch keine Aufgaben vorhanden.</p>
    <?php else: ?>
        <div class="pages-table-wrap" style="border: none; box-shadow: none;">
            <table class="pages-table">
                <tbody>
                <?php foreach ($recentTasks as $rt): ?>
                    <tr>
                        <td>
                            <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $rt['id'] ?>" class="page-link">
                                <?= Security::esc($rt['title']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="status-badge">
                                <?= Security::esc($rt['column_name'] ?? '') ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= Security::esc($rt['owner_name'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top: var(--sp-3);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks">Alle Aufgaben anzeigen &rarr;</a>
        </p>
    <?php endif; ?>
</section>

<!-- Recent Activity -->
<?php
    $recentActivity = [];
    try {
        $recentActivity = DB::fetchAll(
            'SELECT a.*, u.name AS user_name
             FROM activity a
             LEFT JOIN users u ON u.id = a.created_by
             ORDER BY a.created_at DESC
             LIMIT 10'
        );
    } catch (Throwable $e) {
        // Activity table may not exist in early installations
    }
?>
<section class="section-block">
    <h2>Letzte Aktivitaet</h2>
    <?php if (empty($recentActivity)): ?>
        <p class="placeholder-text">Keine Aktivitaet vorhanden.</p>
    <?php else: ?>
        <ul class="activity-list">
            <?php foreach ($recentActivity as $entry): ?>
            <li class="activity-item">
                <span class="activity-time"><?= Security::esc(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></span>
                <span class="activity-text"><?= ActivityService::formatActivity($entry) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
