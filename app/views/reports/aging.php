<?php
/**
 * Aging Report - Aging buckets, overdue buckets, oldest tasks (AP18).
 *
 * Expected variables:
 *   $filters, $filterData, $agingBuckets, $overdueBuckets, $oldestTasks
 */
$reportRoute = 'reports_aging';
$fqs = ReportsController::filterQueryString($filters);
?>

<div class="report-header">
    <h1>Aging Analyse</h1>
    <div class="report-nav">
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_overview<?= Security::esc($fqs) ?>" class="report-nav-link">Uebersicht</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_flow<?= Security::esc($fqs) ?>" class="report-nav-link">Flow Metrics</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_aging" class="report-nav-link active">Aging</a>
        <?php if (Authz::can(Authz::REPORT_EXPORT)): ?>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_export_csv&type=aging<?= Security::esc($fqs) ?>" class="report-nav-link report-export-link">CSV Export</a>
        <?php endif; ?>
    </div>
</div>

<?php $showColumnFilter = true; require APP_DIR . '/views/reports/_filters.php'; ?>

<div class="report-grid-2">
    <!-- Aging Buckets -->
    <div class="report-section">
        <h2 class="report-section-title">Alter offener Tasks</h2>
        <?php
            $totalAging = array_sum(array_column($agingBuckets, 'count'));
            $maxAging = max(array_column($agingBuckets, 'count'));
            $maxAging = max($maxAging, 1);
        ?>
        <?php if ($totalAging === 0): ?>
        <p class="text-muted">Keine offenen Tasks.</p>
        <?php else: ?>
        <div class="chart-bar-container">
            <?php foreach ($agingBuckets as $bucket): ?>
            <div class="chart-bar-row">
                <div class="chart-bar-label"><?= Security::esc($bucket['label']) ?></div>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width: <?= round(($bucket['count'] / $maxAging) * 100) ?>%">
                        <span class="chart-bar-value"><?= (int) $bucket['count'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted text-sm" style="margin-top: var(--sp-2)">Total: <?= $totalAging ?> offene Tasks</p>
        <?php endif; ?>
    </div>

    <!-- Overdue Buckets -->
    <div class="report-section">
        <h2 class="report-section-title">Ueberfaellige Tasks</h2>
        <?php
            $totalOverdue = array_sum(array_column($overdueBuckets, 'count'));
            $maxOverdue = max(array_column($overdueBuckets, 'count'));
            $maxOverdue = max($maxOverdue, 1);
        ?>
        <?php if ($totalOverdue === 0): ?>
        <p class="text-muted">Keine ueberfaelligen Tasks.</p>
        <?php else: ?>
        <div class="chart-bar-container">
            <?php foreach ($overdueBuckets as $bucket): ?>
            <div class="chart-bar-row">
                <div class="chart-bar-label"><?= Security::esc($bucket['label']) ?></div>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill chart-bar-fill-warning" style="width: <?= round(($bucket['count'] / $maxOverdue) * 100) ?>%">
                        <span class="chart-bar-value"><?= (int) $bucket['count'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted text-sm" style="margin-top: var(--sp-2)">Total: <?= $totalOverdue ?> ueberfaellige Tasks</p>
        <?php endif; ?>
    </div>
</div>

<!-- Oldest Open Tasks -->
<div class="report-section">
    <h2 class="report-section-title">Aelteste offene Tasks (Top 50)</h2>
    <?php if (empty($oldestTasks)): ?>
    <p class="text-muted">Keine offenen Tasks.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Owner</th>
                    <th>Spalte</th>
                    <th>Erstellt</th>
                    <th>Alter (Tage)</th>
                    <th>Faelligkeit</th>
                    <th>Ueberfaellig</th>
                    <th>Tags</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($oldestTasks as $task): ?>
                <tr>
                    <td><a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= (int) $task['id'] ?>"><?= Security::esc($task['title']) ?></a></td>
                    <td><?= Security::esc($task['owner_name'] ?? '-') ?></td>
                    <td><?= Security::esc($task['column_name']) ?></td>
                    <td><?= Security::esc(substr($task['created_at'] ?? '', 0, 10)) ?></td>
                    <td><?= (int) $task['age_days'] ?></td>
                    <td><?= Security::esc($task['due_date'] ?? '-') ?></td>
                    <td class="<?= !empty($task['overdue_days']) ? 'text-warning' : '' ?>"><?= $task['overdue_days'] !== null ? (int) $task['overdue_days'] : '-' ?></td>
                    <td>
                        <?php if (!empty($task['tags'])): ?>
                        <?php foreach ($task['tags'] as $tagName): ?>
                        <span class="tag tag-sm"><?= Security::esc($tagName) ?></span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
