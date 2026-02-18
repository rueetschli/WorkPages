<?php
/**
 * ApiV1BoardColumnsController - REST API v1 for board columns (AP19).
 *
 * Read-only endpoints for integration tools to know available columns.
 *
 * Endpoints:
 *   GET /api/v1/board_columns       List all columns
 *   GET /api/v1/board_columns/{id}  Get single column
 */
class ApiV1BoardColumnsController
{
    /**
     * GET /api/v1/board_columns
     */
    public function index(): void
    {
        ApiScopeService::requireScope('tasks:read');

        $columns = BoardColumn::allOrdered();

        $data = array_map(fn($c) => $this->formatColumn($c), $columns);
        ApiResponse::json(['data' => $data]);
    }

    /**
     * GET /api/v1/board_columns/{id}
     */
    public function show(int $id): void
    {
        ApiScopeService::requireScope('tasks:read');

        $column = BoardColumn::findById($id);
        if (!$column) {
            ApiResponse::notFound('Board-Spalte nicht gefunden.');
        }

        ApiResponse::json($this->formatColumn($column));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatColumn(array $col): array
    {
        return [
            'id'         => (int) $col['id'],
            'name'       => $col['name'],
            'slug'       => $col['slug'],
            'color'      => $col['color'] ?? null,
            'category'   => $col['category'] ?? 'active',
            'position'   => (int) $col['position'],
            'wip_limit'  => $col['wip_limit'] !== null ? (int) $col['wip_limit'] : null,
            'is_default' => (int) ($col['is_default'] ?? 0) === 1,
        ];
    }
}
