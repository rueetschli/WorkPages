<?php
/**
 * ApiV1ReportsController - REST API v1 for reports (AP19).
 *
 * Read-only endpoints for flow and aging metrics.
 *
 * Endpoints:
 *   GET /api/v1/reports/flow     Flow metrics (throughput, cycle time)
 *   GET /api/v1/reports/aging    Aging metrics (open tasks, overdue)
 */
class ApiV1ReportsController
{
    /**
     * Route to the appropriate report type.
     */
    public function show(string $type): void
    {
        ApiScopeService::requireScope('reports:read');

        switch ($type) {
            case 'flow':
                $this->flowReport();
                break;
            case 'aging':
                $this->agingReport();
                break;
            default:
                ApiResponse::notFound('Unbekannter Report-Typ: ' . $type);
        }
    }

    /**
     * GET /api/v1/reports/flow
     */
    private function flowReport(): void
    {
        $userId = ApiScopeService::getUserId();
        $globalRole = ApiScopeService::getUserRole();
        $filters = ReportingService::parseFilters($_GET);

        $throughput = ReportingService::getThroughput($filters, $userId, $globalRole);
        $avgCycleTime = ReportingService::getAvgCycleTime($filters, $userId, $globalRole);
        $cycleTimeSummary = ReportingService::getCycleTimeSummary($filters, $userId, $globalRole);
        $throughputPerWeek = ReportingService::getThroughputPerWeek($filters, $userId, $globalRole);

        ApiResponse::json([
            'data' => [
                'period'        => [
                    'from' => $filters['from'],
                    'to'   => $filters['to'],
                ],
                'throughput'         => $throughput,
                'avg_cycle_time'     => $avgCycleTime,
                'cycle_time_summary' => $cycleTimeSummary,
                'throughput_per_week' => array_map(fn($r) => [
                    'week_start' => $r['week_start'],
                    'throughput' => (int) $r['throughput'],
                ], $throughputPerWeek),
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/aging
     */
    private function agingReport(): void
    {
        $userId = ApiScopeService::getUserId();
        $globalRole = ApiScopeService::getUserRole();
        $filters = ReportingService::parseFilters($_GET);

        $wipCount = ReportingService::getWipCount($filters, $userId, $globalRole);
        $overdueCount = ReportingService::getOverdueCount($filters, $userId, $globalRole);
        $agingBuckets = ReportingService::getAgingBuckets($filters, $userId, $globalRole);
        $overdueBuckets = ReportingService::getOverdueBuckets($filters, $userId, $globalRole);
        $topAged = ReportingService::getTopAgedTasks($filters, $userId, $globalRole, 10);

        ApiResponse::json([
            'data' => [
                'wip_count'       => $wipCount,
                'overdue_count'   => $overdueCount,
                'aging_buckets'   => $agingBuckets,
                'overdue_buckets' => $overdueBuckets,
                'top_aged_tasks'  => array_map(fn($t) => [
                    'id'          => (int) $t['id'],
                    'title'       => $t['title'],
                    'column_name' => $t['column_name'],
                    'age_days'    => (int) $t['age_days'],
                    'owner_name'  => $t['owner_name'] ?? null,
                ], $topAged),
            ],
        ]);
    }
}
