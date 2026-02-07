<?php
/**
 * Home view - Dashboard / landing page.
 */
$baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
?>

<div class="page-header">
    <h1>Welcome to Work Pages</h1>
    <p class="subtitle">Your workspace for pages, tasks, and decisions -- all in one place.</p>
</div>

<!-- Quick-access cards -->
<div class="card-grid">

    <a href="<?= Security::esc($baseUrl) ?>/?r=pages" class="card">
        <div class="card-icon">&#9783;</div>
        <h3 class="card-title">Pages</h3>
        <p class="card-desc">Create and browse knowledge pages with embedded tasks.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=tasks" class="card">
        <div class="card-icon">&#9745;</div>
        <h3 class="card-title">Tasks</h3>
        <p class="card-desc">Aufgaben erstellen, verwalten und nachverfolgen.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=board" class="card">
        <div class="card-icon">&#9638;</div>
        <h3 class="card-title">Board</h3>
        <p class="card-desc">Kanban board for visual task management.</p>
    </a>

    <a href="<?= Security::esc($baseUrl) ?>/?r=search" class="card">
        <div class="card-icon">&#8981;</div>
        <h3 class="card-title">Search</h3>
        <p class="card-desc">Find pages and tasks quickly.</p>
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
        <table class="pages-table" style="margin: 0 calc(-1 * var(--space-lg)); width: calc(100% + 2 * var(--space-lg));">
            <tbody>
            <?php foreach ($recentTasks as $rt): ?>
                <tr>
                    <td>
                        <a href="<?= Security::esc($baseUrl) ?>/?r=task_view&amp;id=<?= (int) $rt['id'] ?>" class="page-link">
                            <?= Security::esc($rt['title']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="status-badge status-<?= Security::esc($rt['status']) ?>">
                            <?= Security::esc(Task::STATUS_LABELS[$rt['status']] ?? $rt['status']) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= Security::esc($rt['owner_name'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: var(--space-md);">
            <a href="<?= Security::esc($baseUrl) ?>/?r=tasks">Alle Aufgaben anzeigen &rarr;</a>
        </p>
    <?php endif; ?>
</section>

<section class="section-block">
    <h2>Recent Activity</h2>
    <p class="placeholder-text">Activity feed will be available after AP8.</p>
</section>
