<?php
/**
 * Flow Metrics Report - Throughput, Cycle Time, WIP (AP18).
 *
 * Expected variables:
 *   $filters, $filterData, $throughputWeekly, $cycleTimeSummary,
 *   $wipPerColumn, $wipTrend
 */
$reportRoute = 'reports_flow';
$fqs = ReportsController::filterQueryString($filters);
?>

<div class="report-header">
    <h1>Flow Metrics</h1>
    <div class="report-nav">
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_overview<?= Security::esc($fqs) ?>" class="report-nav-link">Uebersicht</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_flow" class="report-nav-link active">Flow Metrics</a>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_aging<?= Security::esc($fqs) ?>" class="report-nav-link">Aging</a>
        <?php if (Authz::can(Authz::REPORT_EXPORT)): ?>
        <a href="<?= Security::esc($baseUrl) ?>/?r=reports_export_csv&type=flow<?= Security::esc($fqs) ?>" class="report-nav-link report-export-link">CSV Export</a>
        <?php endif; ?>
    </div>
</div>

<?php $showColumnFilter = false; require APP_DIR . '/views/reports/_filters.php'; ?>

<!-- Cycle Time Summary -->
<div class="report-section">
    <h2 class="report-section-title">Cycle Time</h2>
    <?php if ($cycleTimeSummary['count'] === 0): ?>
    <p class="text-muted">Keine abgeschlossenen Tasks im Zeitraum.</p>
    <?php else: ?>
    <div class="kpi-grid kpi-grid-sm">
        <div class="kpi-card">
            <div class="kpi-value"><?= (int) $cycleTimeSummary['count'] ?></div>
            <div class="kpi-label">Tasks</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?= $cycleTimeSummary['average'] !== null ? number_format($cycleTimeSummary['average'], 1) : '-' ?></div>
            <div class="kpi-label">Durchschnitt (Tage)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?= $cycleTimeSummary['p50'] !== null ? number_format($cycleTimeSummary['p50'], 1) : '-' ?></div>
            <div class="kpi-label">Median (P50)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?= $cycleTimeSummary['p85'] !== null ? number_format($cycleTimeSummary['p85'], 1) : '-' ?></div>
            <div class="kpi-label">P85</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?= $cycleTimeSummary['p95'] !== null ? number_format($cycleTimeSummary['p95'], 1) : '-' ?></div>
            <div class="kpi-label">P95</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Throughput per Week -->
<div class="report-section">
    <h2 class="report-section-title">Throughput pro Woche</h2>
    <?php if (empty($throughputWeekly)): ?>
    <p class="text-muted">Keine Daten im Zeitraum.</p>
    <?php else: ?>
    <?php
        $maxThroughput = max(array_column($throughputWeekly, 'throughput'));
        $maxThroughput = max($maxThroughput, 1);
    ?>
    <div class="chart-bar-container">
        <?php foreach ($throughputWeekly as $week): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label">KW <?= Security::esc(substr($week['yw'], -2)) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width: <?= round(((int) $week['throughput'] / $maxThroughput) * 100) ?>%">
                    <span class="chart-bar-value"><?= (int) $week['throughput'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive" style="margin-top: var(--sp-4)">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Kalenderwoche</th>
                    <th>Wochenstart</th>
                    <th>Throughput</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($throughputWeekly as $week): ?>
                <tr>
                    <td>KW <?= Security::esc(substr($week['yw'], -2)) ?></td>
                    <td><?= Security::esc($week['week_start']) ?></td>
                    <td><?= (int) $week['throughput'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- WIP per Column -->
<div class="report-section">
    <h2 class="report-section-title">WIP pro Spalte (aktuell)</h2>
    <?php if (empty($wipPerColumn)): ?>
    <p class="text-muted">Keine Spalten vorhanden.</p>
    <?php else: ?>
    <?php
        $maxWip = max(array_column($wipPerColumn, 'task_count'));
        $maxWip = max($maxWip, 1);
    ?>
    <div class="chart-bar-container">
        <?php foreach ($wipPerColumn as $col): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label"><?= Security::esc($col['column_name']) ?> <span class="text-muted text-sm">(<?= Security::esc($col['category']) ?>)</span></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill chart-bar-fill-<?= Security::esc($col['category']) ?>" style="width: <?= round(((int) $col['task_count'] / $maxWip) * 100) ?>%">
                    <span class="chart-bar-value"><?= (int) $col['task_count'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- WIP Trend -->
<?php if (!empty($wipTrend)): ?>
<div class="report-section">
    <h2 class="report-section-title">WIP Trend (aktive Spalten)</h2>
    <?php
        $maxWipTrend = max(array_column($wipTrend, 'wip'));
        $maxWipTrend = max($maxWipTrend, 1);
    ?>
    <div class="chart-bar-container">
        <?php foreach ($wipTrend as $snap): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label"><?= Security::esc($snap['snap_date']) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width: <?= round(((int) $snap['wip'] / $maxWipTrend) * 100) ?>%">
                    <span class="chart-bar-value"><?= (int) $snap['wip'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
