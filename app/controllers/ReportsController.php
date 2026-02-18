<?php
/**
 * ReportsController - Reporting and Flow Analytics (AP18).
 *
 * Routes:
 *   /?r=reports_overview   Overview with KPI tiles
 *   /?r=reports_flow       Flow metrics (throughput, cycle time, WIP trend)
 *   /?r=reports_aging      Aging and overdue analysis
 *   /?r=reports_export_csv CSV export
 */
class ReportsController
{
    /**
     * Reports overview: KPI tiles, risk tables.
     */
    public function overview(): void
    {
        Authz::require(Authz::REPORT_VIEW);

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $filters    = ReportingService::parseFilters($_GET);

        // Enforce team visibility on filter
        $this->enforceTeamFilter($filters, $userId, $globalRole);

        // KPIs
        $wip           = ReportingService::getWipCount($filters, $userId, $globalRole);
        $throughput    = ReportingService::getThroughput($filters, $userId, $globalRole);
        $avgCycleTime  = ReportingService::getAvgCycleTime($filters, $userId, $globalRole);
        $overdueCount  = ReportingService::getOverdueCount($filters, $userId, $globalRole);
        $bottleneck    = ReportingService::getBottleneckColumn($filters, $userId, $globalRole);
        $topTags       = ReportingService::getTopTags($filters, $userId, $globalRole);

        // Risk tables
        $topOverdue = ReportingService::getTopOverdueTasks($filters, $userId, $globalRole);
        $topAged    = ReportingService::getTopAgedTasks($filters, $userId, $globalRole);

        // Filter dropdown data
        $filterData = $this->getFilterDropdownData($userId, $globalRole);

        $pageTitle   = 'Reports';
        $contentView = APP_DIR . '/views/reports/overview.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Flow metrics: throughput trend, cycle time summary, WIP.
     */
    public function flow(): void
    {
        Authz::require(Authz::REPORT_VIEW);

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $filters    = ReportingService::parseFilters($_GET);

        $this->enforceTeamFilter($filters, $userId, $globalRole);

        // Throughput trend per week
        $throughputWeekly = ReportingService::getThroughputPerWeek($filters, $userId, $globalRole);

        // Cycle time summary
        $cycleTimeSummary = ReportingService::getCycleTimeSummary($filters, $userId, $globalRole);

        // WIP per column (current)
        $wipPerColumn = ReportingService::getWipPerColumn($filters, $userId, $globalRole);

        // WIP trend
        $wipTrend = ReportingService::getWipTrend($filters, $userId, $globalRole);

        // Filter dropdown data
        $filterData = $this->getFilterDropdownData($userId, $globalRole);

        $pageTitle   = 'Flow Metrics';
        $contentView = APP_DIR . '/views/reports/flow.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Aging and overdue analysis.
     */
    public function aging(): void
    {
        Authz::require(Authz::REPORT_VIEW);

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $filters    = ReportingService::parseFilters($_GET);

        $this->enforceTeamFilter($filters, $userId, $globalRole);

        // Aging buckets
        $agingBuckets = ReportingService::getAgingBuckets($filters, $userId, $globalRole);

        // Overdue buckets
        $overdueBuckets = ReportingService::getOverdueBuckets($filters, $userId, $globalRole);

        // Oldest open tasks
        $oldestTasks = ReportingService::getOldestOpenTasks($filters, $userId, $globalRole);

        // Filter dropdown data
        $filterData = $this->getFilterDropdownData($userId, $globalRole);

        $pageTitle   = 'Aging Analyse';
        $contentView = APP_DIR . '/views/reports/aging.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * CSV export for reports.
     */
    public function exportCsv(): void
    {
        Authz::require(Authz::REPORT_EXPORT);

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $filters    = ReportingService::parseFilters($_GET);
        $type       = $_GET['type'] ?? 'flow';

        $this->enforceTeamFilter($filters, $userId, $globalRole);

        $filename = 'workpages-report-' . $type . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM for Excel UTF-8
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        switch ($type) {
            case 'aging':
                $this->exportAgingCsv($out, $filters, $userId, $globalRole);
                break;

            case 'overview':
                $this->exportOverviewCsv($out, $filters, $userId, $globalRole);
                break;

            case 'flow':
            default:
                $this->exportFlowCsv($out, $filters, $userId, $globalRole);
                break;
        }

        fclose($out);
        exit;
    }

    // ── CSV export helpers ────────────────────────────────────────────

    /**
     * @param resource $out
     */
    private function exportFlowCsv($out, array $filters, int $userId, string $globalRole): void
    {
        fputcsv($out, [
            'ID', 'Titel', 'Team', 'Owner', 'Gestartet', 'Abgeschlossen',
            'Cycle Time (Tage)', 'Lead Time (Tage)', 'Tags'
        ], ';');

        $tasks = ReportingService::getFlowExportData($filters, $userId, $globalRole);

        foreach ($tasks as $task) {
            fputcsv($out, [
                $task['id'],
                $task['title'],
                $task['team_name'] ?? '',
                $task['owner_name'] ?? '',
                $task['started_at'] ?? '',
                $task['done_at'] ?? '',
                $task['cycle_time_days'] ?? '',
                $task['lead_time_days'] ?? '',
                $task['tag_list'] ?? '',
            ], ';');
        }
    }

    /**
     * @param resource $out
     */
    private function exportAgingCsv($out, array $filters, int $userId, string $globalRole): void
    {
        fputcsv($out, [
            'ID', 'Titel', 'Team', 'Owner', 'Erstellt', 'Gestartet',
            'Alter (Tage)', 'Faelligkeitsdatum', 'Ueberfaellig (Tage)', 'Spalte', 'Tags'
        ], ';');

        $tasks = ReportingService::getAgingExportData($filters, $userId, $globalRole);

        foreach ($tasks as $task) {
            fputcsv($out, [
                $task['id'],
                $task['title'],
                $task['team_name'] ?? '',
                $task['owner_name'] ?? '',
                $task['created_at'] ?? '',
                $task['started_at'] ?? '',
                $task['age_days'] ?? '',
                $task['due_date'] ?? '',
                $task['overdue_days'] ?? '',
                $task['column_name'] ?? '',
                $task['tag_list'] ?? '',
            ], ';');
        }
    }

    /**
     * @param resource $out
     */
    private function exportOverviewCsv($out, array $filters, int $userId, string $globalRole): void
    {
        // Export overview as key-value pairs
        fputcsv($out, ['Metrik', 'Wert'], ';');

        $wip          = ReportingService::getWipCount($filters, $userId, $globalRole);
        $throughput   = ReportingService::getThroughput($filters, $userId, $globalRole);
        $avgCycle     = ReportingService::getAvgCycleTime($filters, $userId, $globalRole);
        $overdueCount = ReportingService::getOverdueCount($filters, $userId, $globalRole);

        fputcsv($out, ['WIP (Work in Progress)', $wip], ';');
        fputcsv($out, ['Throughput (Zeitraum)', $throughput], ';');
        fputcsv($out, ['Durchschn. Cycle Time (Tage)', $avgCycle ?? '-'], ';');
        fputcsv($out, ['Ueberfaellige Tasks', $overdueCount], ';');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Enforce team filter: user may only filter teams they are member of.
     */
    private function enforceTeamFilter(array &$filters, int $userId, string $globalRole): void
    {
        if (!empty($filters['team_id']) && $globalRole !== 'admin') {
            $teamIds = TeamUser::getTeamIds($userId);
            if (!in_array((int) $filters['team_id'], $teamIds, true)) {
                $filters['team_id'] = null;
            }
        }
    }

    /**
     * Get data for filter dropdowns (teams, users, tags, columns).
     */
    private function getFilterDropdownData(int $userId, string $globalRole): array
    {
        return [
            'teams'   => TeamService::getTeamsForSwitcher($userId, $globalRole),
            'users'   => User::allForDropdown(),
            'tags'    => Task::allTags(),
            'columns' => BoardColumn::allOrdered(),
        ];
    }

    /**
     * Build query string from current filters (for cross-report links).
     */
    public static function filterQueryString(array $filters): string
    {
        $params = [];
        if (!empty($filters['team_id'])) {
            $params['team_id'] = $filters['team_id'];
        }
        if (!empty($filters['preset'])) {
            $params['preset'] = $filters['preset'];
        }
        if (!empty($filters['from'])) {
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['owner_id'])) {
            $params['owner_id'] = $filters['owner_id'];
        }
        if (!empty($filters['tag'])) {
            $params['tag'] = $filters['tag'];
        }
        if (!empty($filters['column_id'])) {
            $params['column_id'] = $filters['column_id'];
        }
        return empty($params) ? '' : '&' . http_build_query($params);
    }

    /**
     * Redirect helper.
     */
    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
