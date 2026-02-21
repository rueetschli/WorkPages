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
    <h1><?= Security::esc(t('reports.title')) ?></h1>
    <div class="report-nav">
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_overview" class="report-nav-link active"><?= Security::esc(t('reports.overview')) ?></a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_flow<?= Security::esc($fqs) ?>" class="report-nav-link"><?= Security::esc(t('reports.flow_metrics')) ?></a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_aging<?= Security::esc($fqs) ?>" class="report-nav-link"><?= Security::esc(t('reports.aging')) ?></a>
        <?php if (Authz::can(Authz::REPORT_EXPORT)): ?>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_export_csv&type=overview<?= Security::esc($fqs) ?>" class="report-nav-link report-export-link"><?= Security::esc(t('reports.csv_export')) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php $showColumnFilter = false; require APP_DIR . '/views/reports/_filters.php'; ?>

<!-- KPI Tiles -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-value"><?= (int) $wip ?></div>
        <div class="kpi-label"><?= Security::esc(t('reports.wip')) ?></div>
        <div class="kpi-hint"><?= Security::esc(t('reports.wip_hint')) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value"><?= (int) $throughput ?></div>
        <div class="kpi-label"><?= Security::esc(t('reports.throughput')) ?></div>
        <div class="kpi-hint"><?= Security::esc(t('reports.throughput_hint')) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value"><?= $avgCycleTime !== null ? number_format($avgCycleTime, 1) : '-' ?></div>
        <div class="kpi-label"><?= Security::esc(t('reports.cycle_time')) ?></div>
        <div class="kpi-hint"><?= Security::esc(t('reports.cycle_time_hint')) ?></div>
    </div>
    <div class="kpi-card <?= $overdueCount > 0 ? 'kpi-card-warning' : '' ?>">
        <div class="kpi-value"><?= (int) $overdueCount ?></div>
        <div class="kpi-label"><?= Security::esc(t('reports.overdue')) ?></div>
        <div class="kpi-hint"><?= Security::esc(t('reports.overdue_hint')) ?></div>
    </div>
    <?php if ($bottleneck): ?>
    <div class="kpi-card">
        <div class="kpi-value"><?= Security::esc($bottleneck['name']) ?></div>
        <div class="kpi-label"><?= Security::esc(t('reports.bottleneck')) ?></div>
        <div class="kpi-hint"><?= (int) $bottleneck['task_count'] ?> Tasks</div>
    </div>
    <?php endif; ?>
</div>

<!-- Top Tags -->
<?php if (!empty($topTags)): ?>
<div class="report-section">
    <h2 class="report-section-title"><?= Security::esc(t('reports.top_tags')) ?></h2>
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
        <h2 class="report-section-title"><?= Security::esc(t('reports.overdue_tasks')) ?></h2>
        <?php if (empty($topOverdue)): ?>
        <p class="text-muted"><?= Security::esc(t('reports.no_overdue')) ?></p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th><?= Security::esc(t('labels.owner')) ?></th>
                        <th><?= Security::esc(t('labels.column')) ?></th>
                        <th><?= Security::esc(t('reports.days_overdue')) ?></th>
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
        <h2 class="report-section-title"><?= Security::esc(t('reports.oldest_tasks')) ?></h2>
        <?php if (empty($topAged)): ?>
        <p class="text-muted"><?= Security::esc(t('reports.no_open_tasks')) ?></p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th><?= Security::esc(t('labels.owner')) ?></th>
                        <th><?= Security::esc(t('labels.column')) ?></th>
                        <th><?= Security::esc(t('reports.age_days')) ?></th>
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
