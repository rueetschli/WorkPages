<?php
/**
 * Reports Overview - KPI tiles and risk tables (AP18).
 *
 * Expected variables:
 *   $filters, $filterData, $wip, $throughput, $avgCycleTime,
 *   $overdueCount, $bottleneck, $topTags, $topOverdue, $topAged
 */
$reportRoute = 'reports_overview';
$fqs = ReportsController::filterQueryString($filters);
?>

<div class="report-header">
    <h1>Reports</h1>
    <div class="report-nav">
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_overview" class="report-nav-link active">Uebersicht</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_flow<?= Security::esc($fqs) ?>" class="report-nav-link">Flow Metrics</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_aging<?= Security::esc($fqs) ?>" class="report-nav-link">Aging</a>
        <?php if (Authz::can(Authz::REPORT_EXPORT)): ?>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_export_csv&type=overview<?= Security::esc($fqs) ?>" class="report-nav-link report-export-link">CSV Export</a>
        <?php endif; ?>
    </div>
</div>

<?php $showColumnFilter = false; require APP_DIR . '/views/reports/_filters.php'; ?>

<!-- KPI Tiles -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-value"><?= (int) $wip ?></div>
        <div class="kpi-label">WIP (Work in Progress)</div>
        <div class="kpi-hint">Tasks in aktiven Spalten</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value"><?= (int) $throughput ?></div>
        <div class="kpi-label">Throughput</div>
        <div class="kpi-hint">Abgeschlossen im Zeitraum</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value"><?= $avgCycleTime !== null ? number_format($avgCycleTime, 1) : '-' ?></div>
        <div class="kpi-label">Cycle Time (Tage)</div>
        <div class="kpi-hint">Durchschnitt im Zeitraum</div>
    </div>
    <div class="kpi-card <?= $overdueCount > 0 ? 'kpi-card-warning' : '' ?>">
        <div class="kpi-value"><?= (int) $overdueCount ?></div>
        <div class="kpi-label">Ueberfaellig</div>
        <div class="kpi-hint">Offene Tasks nach Faelligkeit</div>
    </div>
    <?php if ($bottleneck): ?>
    <div class="kpi-card">
        <div class="kpi-value"><?= Security::esc($bottleneck['name']) ?></div>
        <div class="kpi-label">Engpass-Spalte</div>
        <div class="kpi-hint"><?= (int) $bottleneck['task_count'] ?> Tasks</div>
    </div>
    <?php endif; ?>
</div>

<!-- Top Tags -->
<?php if (!empty($topTags)): ?>
<div class="report-section">
    <h2 class="report-section-title">Top Tags im Zeitraum</h2>
    <div class="tag-chips">
        <?php foreach ($topTags as $tt): ?>
        <span class="tag"><?= Security::esc($tt['name']) ?> <span class="tag-count">(<?= (int) $tt['task_count'] ?>)</span></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Risk Tables -->
<div class="report-grid-2">
    <!-- Overdue Tasks -->
    <div class="report-section">
        <h2 class="report-section-title">Ueberfaellige Tasks</h2>
        <?php if (empty($topOverdue)): ?>
        <p class="text-muted">Keine ueberfaelligen Tasks.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Owner</th>
                        <th>Spalte</th>
                        <th>Tage ueberfaellig</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topOverdue as $task): ?>
                    <tr>
                        <td><a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= (int) $task['id'] ?>"><?= Security::esc($task['title']) ?></a></td>
                        <td><?= Security::esc($task['owner_name'] ?? '-') ?></td>
                        <td><?= Security::esc($task['column_name']) ?></td>
                        <td class="text-warning"><?= (int) $task['overdue_days'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Aged Tasks -->
    <div class="report-section">
        <h2 class="report-section-title">Aelteste offene Tasks</h2>
        <?php if (empty($topAged)): ?>
        <p class="text-muted">Keine offenen Tasks.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Owner</th>
                        <th>Spalte</th>
                        <th>Alter (Tage)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topAged as $task): ?>
                    <tr>
                        <td><a href="<?= Security::esc($baseUrl) ?>/?r=task_view&id=<?= (int) $task['id'] ?>"><?= Security::esc($task['title']) ?></a></td>
                        <td><?= Security::esc($task['owner_name'] ?? '-') ?></td>
                        <td><?= Security::esc($task['column_name']) ?></td>
                        <td><?= (int) $task['age_days'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
