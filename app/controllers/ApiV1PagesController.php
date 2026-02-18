<?php
/**
 * ApiV1PagesController - REST API v1 for pages (AP19).
 *
 * Endpoints:
 *   GET    /api/v1/pages            List pages (filtered, paginated)
 *   POST   /api/v1/pages            Create page
 *   GET    /api/v1/pages/{id}       Get single page
 *   PATCH  /api/v1/pages/{id}       Update page
 *   DELETE /api/v1/pages/{id}       Soft delete page
 *   GET    /api/v1/pages/{id}/tasks Linked tasks
 */
class ApiV1PagesController
{
    /**
     * GET /api/v1/pages
     */
    public function index(): void
    {
        ApiScopeService::requireScope('pages:read');

        [$limit, $cursor] = ApiRouter::parsePagination();

        $where = [];
        $params = [];

        // Team visibility
        [$visSql, $visParams] = ApiScopeService::pageVisibilityWhere('p');
        $where[] = $visSql;
        $params = array_merge($params, $visParams);

        // Not deleted
        $where[] = 'p.deleted_at IS NULL';

        // Filters
        if (!empty($_GET['team_id'])) {
            $where[] = 'p.team_id = ?';
            $params[] = (int) $_GET['team_id'];
        }
        if (isset($_GET['parent_id'])) {
            if ($_GET['parent_id'] === '' || $_GET['parent_id'] === 'null') {
                $where[] = 'p.parent_id IS NULL';
            } else {
                $where[] = 'p.parent_id = ?';
                $params[] = (int) $_GET['parent_id'];
            }
        }
        if (!empty($_GET['updated_after'])) {
            $where[] = 'p.updated_at >= ?';
            $params[] = $_GET['updated_after'];
        }

        // Cursor
        if ($cursor !== null) {
            $where[] = 'p.id < ?';
            $params[] = (int) $cursor;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT p.id, p.title, p.slug, p.parent_id, p.content_md,
                       p.team_id, p.created_by, p.updated_by, p.created_at, p.updated_at
                FROM pages p
                {$whereSql}
                ORDER BY p.id DESC
                LIMIT " . ($limit + 1);

        $rows = DB::fetchAll($sql, $params);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $lastRow = end($rows);
            $nextCursor = (string) $lastRow['id'];
        }

        $data = array_map(fn($row) => $this->formatPage($row), $rows);
        ApiResponse::paginated($data, $nextCursor);
    }

    /**
     * GET /api/v1/pages/{id}
     */
    public function show(int $id): void
    {
        ApiScopeService::requireScope('pages:read');

        $page = Page::findById($id);
        if (!$page) {
            ApiResponse::notFound('Page nicht gefunden.');
        }

        if (!ApiScopeService::canViewPage($page)) {
            ApiResponse::forbidden();
        }

        ApiResponse::json($this->formatPage($page));
    }

    /**
     * POST /api/v1/pages
     */
    public function create(): void
    {
        ApiScopeService::requireScope('pages:write');
        ApiScopeService::requireWrite();

        $rawBody = ApiRouter::getRawBody();
        $idempotency = IdempotencyService::handleRequest(
            ApiScopeService::getKeyPrefix(),
            $rawBody
        );

        $data = ApiRouter::getJsonBody();
        $userId = ApiScopeService::getUserId();

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            ApiResponse::unprocessable('Titel ist erforderlich.');
        }
        if (mb_strlen($title, 'UTF-8') > 255) {
            ApiResponse::unprocessable('Titel darf maximal 255 Zeichen lang sein.');
        }

        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId !== null) {
            $parent = Page::findById($parentId);
            if (!$parent) {
                ApiResponse::unprocessable('Ungueltige parent_id.');
            }
        }

        $teamId = !empty($data['team_id']) ? (int) $data['team_id'] : null;

        try {
            $pageId = Page::create([
                'title'      => $title,
                'content_md' => $data['content_md'] ?? '',
                'parent_id'  => $parentId,
                'created_by' => $userId,
                'team_id'    => $teamId,
            ]);

            ActivityService::log('page', $pageId, 'page_created', $userId, [
                'title'  => $title,
                'source' => 'api',
            ]);
            Logger::info('Page created via API', ['page_id' => $pageId]);

            WatcherService::autoWatchOnCreate('page', $pageId, $userId);
            EventService::emit('page.created', 'page', $pageId, $userId, [
                'title' => $title,
            ]);

            $page = Page::findById($pageId);
            $result = $this->formatPage($page);

            $responseBody = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($idempotency !== null) {
                IdempotencyService::store(
                    ApiScopeService::getKeyPrefix(),
                    $idempotency['key'],
                    $idempotency['hash'],
                    201,
                    $responseBody
                );
            }

            ApiResponse::created($result);
        } catch (\Throwable $e) {
            Logger::error('API page create failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Page konnte nicht erstellt werden.');
        }
    }

    /**
     * PATCH /api/v1/pages/{id}
     */
    public function update(int $id): void
    {
        ApiScopeService::requireScope('pages:write');
        ApiScopeService::requireWrite();

        $page = Page::findById($id);
        if (!$page) {
            ApiResponse::notFound('Page nicht gefunden.');
        }

        if (!ApiScopeService::canEditPage($page)) {
            ApiResponse::forbidden();
        }

        $data = ApiRouter::getJsonBody();
        $userId = ApiScopeService::getUserId();

        $updateData = ['updated_by' => $userId];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title === '') {
                ApiResponse::unprocessable('Titel darf nicht leer sein.');
            }
            $updateData['title'] = $title;
        }

        if (array_key_exists('content_md', $data)) {
            $updateData['content_md'] = $data['content_md'];
        }

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
            if ($parentId !== null) {
                if ($parentId === $id) {
                    ApiResponse::unprocessable('Eine Seite kann nicht ihr eigenes Elternelement sein.');
                }
                $parent = Page::findById($parentId);
                if (!$parent) {
                    ApiResponse::unprocessable('Ungueltige parent_id.');
                }
            }
            $updateData['parent_id'] = $parentId;
        }

        if (array_key_exists('team_id', $data)) {
            $updateData['team_id'] = $data['team_id'] !== null ? (int) $data['team_id'] : null;
        }

        try {
            Page::update($id, $updateData);

            ActivityService::log('page', $id, 'page_updated', $userId, [
                'source' => 'api',
            ]);
            EventService::emit('page.updated', 'page', $id, $userId, [
                'title' => $updateData['title'] ?? $page['title'],
            ]);

            $updated = Page::findById($id);
            ApiResponse::json($this->formatPage($updated));
        } catch (\Throwable $e) {
            Logger::error('API page update failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Page konnte nicht aktualisiert werden.');
        }
    }

    /**
     * DELETE /api/v1/pages/{id}
     */
    public function delete(int $id): void
    {
        ApiScopeService::requireScope('pages:write');
        ApiScopeService::requireWrite();

        $page = Page::findById($id);
        if (!$page) {
            ApiResponse::notFound('Page nicht gefunden.');
        }

        if (!ApiScopeService::canEditPage($page)) {
            ApiResponse::forbidden();
        }

        try {
            $userId = ApiScopeService::getUserId();
            Page::softDelete($id);
            ActivityService::log('page', $id, 'page_deleted', $userId, [
                'title'  => $page['title'],
                'source' => 'api',
            ]);
            Logger::info('Page soft-deleted via API', ['page_id' => $id]);

            EventService::emit('page.deleted', 'page', $id, $userId, [
                'title' => $page['title'],
            ]);

            ApiResponse::noContent();
        } catch (\Throwable $e) {
            Logger::error('API page delete failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Page konnte nicht geloescht werden.');
        }
    }

    /**
     * GET /api/v1/pages/{id}/tasks
     */
    public function linkedTasks(int $pageId): void
    {
        ApiScopeService::requireScope('pages:read');

        $page = Page::findById($pageId);
        if (!$page) {
            ApiResponse::notFound('Page nicht gefunden.');
        }

        if (!ApiScopeService::canViewPage($page)) {
            ApiResponse::forbidden();
        }

        $tasks = PageTask::getTasks($pageId);

        $data = array_map(fn($t) => [
            'id'          => (int) $t['id'],
            'title'       => $t['title'],
            'column_id'   => isset($t['column_id']) ? (int) $t['column_id'] : null,
            'column_name' => $t['column_name'] ?? null,
            'owner_id'    => $t['owner_id'] !== null ? (int) $t['owner_id'] : null,
            'due_date'    => $t['due_date'] ?? null,
        ], $tasks);

        ApiResponse::json(['data' => $data]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatPage(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'title'      => $row['title'],
            'slug'       => $row['slug'],
            'parent_id'  => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'content_md' => $row['content_md'] ?? null,
            'team_id'    => $row['team_id'] !== null ? (int) $row['team_id'] : null,
            'created_by' => (int) $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
