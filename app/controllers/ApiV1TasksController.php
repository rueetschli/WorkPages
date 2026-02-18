<?php
/**
 * ApiV1TasksController - REST API v1 for tasks (AP19).
 *
 * Endpoints:
 *   GET    /api/v1/tasks          List tasks (filtered, paginated)
 *   POST   /api/v1/tasks          Create task
 *   GET    /api/v1/tasks/{id}     Get single task
 *   PATCH  /api/v1/tasks/{id}     Update task
 *   DELETE /api/v1/tasks/{id}     Delete task (hard delete)
 */
class ApiV1TasksController
{
    /**
     * GET /api/v1/tasks
     */
    public function index(): void
    {
        ApiScopeService::requireScope('tasks:read');

        [$limit, $cursor] = ApiRouter::parsePagination();

        $where = [];
        $params = [];

        // Team visibility
        [$visSql, $visParams] = ApiScopeService::taskVisibilityWhere('t');
        $where[] = $visSql;
        $params = array_merge($params, $visParams);

        // Filters
        if (!empty($_GET['team_id'])) {
            $where[] = 't.team_id = ?';
            $params[] = (int) $_GET['team_id'];
        }
        if (!empty($_GET['owner_id'])) {
            $where[] = 't.owner_id = ?';
            $params[] = (int) $_GET['owner_id'];
        }
        if (!empty($_GET['column_id'])) {
            $where[] = 't.column_id = ?';
            $params[] = (int) $_GET['column_id'];
        }
        if (!empty($_GET['due_before'])) {
            $where[] = 't.due_date <= ?';
            $params[] = $_GET['due_before'];
        }
        if (!empty($_GET['due_after'])) {
            $where[] = 't.due_date >= ?';
            $params[] = $_GET['due_after'];
        }
        if (!empty($_GET['updated_after'])) {
            $where[] = 't.updated_at >= ?';
            $params[] = $_GET['updated_after'];
        }

        $join = '';
        if (!empty($_GET['tag'])) {
            $join = ' INNER JOIN task_tags tt_filter ON tt_filter.task_id = t.id
                      INNER JOIN tags tg_filter ON tg_filter.id = tt_filter.tag_id AND tg_filter.name = ?';
            $params[] = mb_strtolower(trim($_GET['tag']), 'UTF-8');
        }

        // Cursor-based pagination (using id)
        if ($cursor !== null) {
            $where[] = 't.id < ?';
            $params[] = (int) $cursor;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT t.id, t.title, t.description_md, t.column_id, t.owner_id, t.due_date,
                       t.team_id, t.position, t.created_by, t.updated_by,
                       t.started_at, t.done_at, t.created_at, t.updated_at,
                       u.name AS owner_name, bc.name AS column_name, bc.slug AS column_slug
                FROM tasks t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN board_columns bc ON bc.id = t.column_id
                {$join}
                {$whereSql}
                ORDER BY t.id DESC
                LIMIT " . ($limit + 1);

        $rows = DB::fetchAll($sql, $params);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $lastRow = end($rows);
            $nextCursor = (string) $lastRow['id'];
        }

        // Attach tags in batch
        $taskIds = array_column($rows, 'id');
        $tagMap = $this->loadTagsBatch($taskIds);

        $data = array_map(fn($row) => $this->formatTask($row, $tagMap), $rows);

        ApiResponse::paginated($data, $nextCursor);
    }

    /**
     * GET /api/v1/tasks/{id}
     */
    public function show(int $id): void
    {
        ApiScopeService::requireScope('tasks:read');

        $task = Task::findById($id);
        if (!$task) {
            ApiResponse::notFound('Task nicht gefunden.');
        }

        if (!ApiScopeService::canViewTask($task)) {
            ApiResponse::forbidden();
        }

        $tags = Task::getTags($id);
        $tagNames = array_column($tags, 'name');
        $linkedPages = PageTask::getPages($id);

        $result = $this->formatTask($task, [(int) $task['id'] => $tagNames]);
        $result['linked_pages'] = array_map(fn($p) => [
            'id'    => (int) $p['id'],
            'title' => $p['title'],
            'slug'  => $p['slug'] ?? null,
        ], $linkedPages);

        ApiResponse::json($result);
    }

    /**
     * POST /api/v1/tasks
     */
    public function create(): void
    {
        ApiScopeService::requireScope('tasks:write');
        ApiScopeService::requireWrite();

        $rawBody = ApiRouter::getRawBody();
        $idempotency = IdempotencyService::handleRequest(
            ApiScopeService::getKeyPrefix(),
            $rawBody
        );

        $data = ApiRouter::getJsonBody();
        $userId = ApiScopeService::getUserId();

        // Validate required fields
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            ApiResponse::unprocessable('Titel ist erforderlich.');
        }
        if (mb_strlen($title, 'UTF-8') > 255) {
            ApiResponse::unprocessable('Titel darf maximal 255 Zeichen lang sein.');
        }

        // Validate optional fields
        $columnId = !empty($data['column_id']) ? (int) $data['column_id'] : BoardColumn::getDefaultId();
        if ($columnId > 0 && !BoardColumn::findById($columnId)) {
            ApiResponse::unprocessable('Ungueltige column_id.');
        }

        $ownerId = !empty($data['owner_id']) ? (int) $data['owner_id'] : null;
        if ($ownerId !== null && !User::findById($ownerId)) {
            ApiResponse::unprocessable('Ungueltiger owner_id.');
        }

        $dueDate = !empty($data['due_date']) ? trim($data['due_date']) : null;
        if ($dueDate !== null) {
            $d = date_create_from_format('Y-m-d', $dueDate);
            if (!$d || $d->format('Y-m-d') !== $dueDate) {
                ApiResponse::unprocessable('Ungueltiges due_date Format (YYYY-MM-DD erwartet).');
            }
        }

        $teamId = !empty($data['team_id']) ? (int) $data['team_id'] : null;

        try {
            $taskId = Task::create([
                'title'          => $title,
                'description_md' => $data['description_md'] ?? null,
                'column_id'      => $columnId,
                'owner_id'       => $ownerId,
                'due_date'       => $dueDate,
                'created_by'     => $userId,
                'team_id'        => $teamId,
            ]);

            // Flow dates
            TaskFlowService::onTaskCreated($taskId, $columnId);

            // Tags
            if (!empty($data['tags']) && is_array($data['tags'])) {
                Task::setTags($taskId, $data['tags']);
            }

            // Link pages
            if (!empty($data['page_ids']) && is_array($data['page_ids'])) {
                foreach ($data['page_ids'] as $pageId) {
                    PageTask::add((int) $pageId, $taskId, $userId);
                }
            }

            // Activity + events
            ActivityService::log('task', $taskId, 'task_created', $userId, [
                'title' => $title,
                'source' => 'api',
            ]);
            Logger::info('Task created via API', ['task_id' => $taskId]);

            WatcherService::autoWatchOnCreate('task', $taskId, $userId);
            EventService::emit('task.created', 'task', $taskId, $userId, [
                'title' => $title,
            ]);

            if ($ownerId !== null && $ownerId !== $userId) {
                WatcherService::autoWatchOnAssignment($taskId, $ownerId);
                EventService::emit('task.assigned', 'task', $taskId, $userId, [
                    'new_owner_id' => $ownerId,
                    'task_id'      => $taskId,
                    'task_title'   => $title,
                ]);
            }

            // Fetch created task
            $task = Task::findById($taskId);
            $tags = Task::getTags($taskId);
            $tagNames = array_column($tags, 'name');
            $result = $this->formatTask($task, [$taskId => $tagNames]);

            // Store idempotency response
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
            Logger::error('API task create failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Task konnte nicht erstellt werden.');
        }
    }

    /**
     * PATCH /api/v1/tasks/{id}
     */
    public function update(int $id): void
    {
        ApiScopeService::requireScope('tasks:write');
        ApiScopeService::requireWrite();

        $task = Task::findById($id);
        if (!$task) {
            ApiResponse::notFound('Task nicht gefunden.');
        }

        if (!ApiScopeService::canEditTask($task)) {
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
            if (mb_strlen($title, 'UTF-8') > 255) {
                ApiResponse::unprocessable('Titel darf maximal 255 Zeichen lang sein.');
            }
            $updateData['title'] = $title;
        }

        if (array_key_exists('description_md', $data)) {
            $updateData['description_md'] = $data['description_md'];
        }

        if (array_key_exists('owner_id', $data)) {
            $newOwnerId = $data['owner_id'] !== null ? (int) $data['owner_id'] : null;
            if ($newOwnerId !== null && !User::findById($newOwnerId)) {
                ApiResponse::unprocessable('Ungueltiger owner_id.');
            }
            $updateData['owner_id'] = $newOwnerId;
        }

        if (array_key_exists('due_date', $data)) {
            $dueDate = $data['due_date'];
            if ($dueDate !== null) {
                $d = date_create_from_format('Y-m-d', $dueDate);
                if (!$d || $d->format('Y-m-d') !== $dueDate) {
                    ApiResponse::unprocessable('Ungueltiges due_date Format.');
                }
            }
            $updateData['due_date'] = $dueDate;
        }

        $oldColumnId = (int) $task['column_id'];
        if (array_key_exists('column_id', $data)) {
            $newColumnId = (int) $data['column_id'];
            if (!BoardColumn::findById($newColumnId)) {
                ApiResponse::unprocessable('Ungueltige column_id.');
            }
            $updateData['column_id'] = $newColumnId;
        }

        if (array_key_exists('team_id', $data)) {
            $updateData['team_id'] = $data['team_id'] !== null ? (int) $data['team_id'] : null;
        }

        try {
            Task::update($id, $updateData);

            // Tags
            if (array_key_exists('tags', $data) && is_array($data['tags'])) {
                Task::setTags($id, $data['tags']);
            }

            // Page links
            if (!empty($data['add_page_ids']) && is_array($data['add_page_ids'])) {
                foreach ($data['add_page_ids'] as $pageId) {
                    PageTask::add((int) $pageId, $id, $userId);
                }
            }
            if (!empty($data['remove_page_ids']) && is_array($data['remove_page_ids'])) {
                foreach ($data['remove_page_ids'] as $pageId) {
                    PageTask::remove((int) $pageId, $id);
                }
            }

            // Flow dates on column change
            $newColumnId = (int) ($updateData['column_id'] ?? $oldColumnId);
            if ($oldColumnId !== $newColumnId) {
                TaskFlowService::onColumnChange($id, $oldColumnId, $newColumnId);

                $oldColumn = BoardColumn::findById($oldColumnId);
                $newColumn = BoardColumn::findById($newColumnId);
                ActivityService::log('task', $id, 'task_column_changed', $userId, [
                    'old_column_id'   => $oldColumnId,
                    'new_column_id'   => $newColumnId,
                    'old_column_name' => $oldColumn['name'] ?? '',
                    'new_column_name' => $newColumn['name'] ?? '',
                ]);
                EventService::emit('task.moved', 'task', $id, $userId, [
                    'old_column_id'   => $oldColumnId,
                    'new_column_id'   => $newColumnId,
                    'new_column_name' => $newColumn['name'] ?? '',
                ]);
            }

            ActivityService::log('task', $id, 'task_updated', $userId, [
                'source' => 'api',
            ]);
            EventService::emit('task.updated', 'task', $id, $userId, [
                'title' => $updateData['title'] ?? $task['title'],
            ]);

            // Owner change event
            if (array_key_exists('owner_id', $updateData)) {
                $oldOwnerId = (int) ($task['owner_id'] ?? 0);
                $newOwnerId = (int) ($updateData['owner_id'] ?? 0);
                if ($newOwnerId > 0 && $newOwnerId !== $oldOwnerId) {
                    WatcherService::autoWatchOnAssignment($id, $newOwnerId);
                    EventService::emit('task.assigned', 'task', $id, $userId, [
                        'new_owner_id' => $newOwnerId,
                        'old_owner_id' => $oldOwnerId,
                        'task_id'      => $id,
                        'task_title'   => $updateData['title'] ?? $task['title'],
                    ]);
                }
            }

            $updated = Task::findById($id);
            $tags = Task::getTags($id);
            $tagNames = array_column($tags, 'name');

            ApiResponse::json($this->formatTask($updated, [$id => $tagNames]));
        } catch (\Throwable $e) {
            Logger::error('API task update failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Task konnte nicht aktualisiert werden.');
        }
    }

    /**
     * DELETE /api/v1/tasks/{id}
     */
    public function delete(int $id): void
    {
        ApiScopeService::requireScope('tasks:write');
        ApiScopeService::requireWrite();

        $task = Task::findById($id);
        if (!$task) {
            ApiResponse::notFound('Task nicht gefunden.');
        }

        if (!ApiScopeService::canEditTask($task)) {
            ApiResponse::forbidden();
        }

        try {
            $userId = ApiScopeService::getUserId();
            Task::delete($id);
            ActivityService::log('task', $id, 'task_deleted', $userId, [
                'title'  => $task['title'],
                'source' => 'api',
            ]);
            Logger::info('Task deleted via API', ['task_id' => $id]);

            EventService::emit('task.deleted', 'task', $id, $userId, [
                'title' => $task['title'],
            ]);

            ApiResponse::noContent();
        } catch (\Throwable $e) {
            Logger::error('API task delete failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Task konnte nicht geloescht werden.');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatTask(array $row, array $tagMap = []): array
    {
        $id = (int) $row['id'];
        return [
            'id'             => $id,
            'title'          => $row['title'],
            'description_md' => $row['description_md'] ?? null,
            'column_id'      => (int) $row['column_id'],
            'column_name'    => $row['column_name'] ?? null,
            'owner_id'       => $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
            'owner_name'     => $row['owner_name'] ?? null,
            'due_date'       => $row['due_date'] ?? null,
            'team_id'        => $row['team_id'] !== null ? (int) $row['team_id'] : null,
            'tags'           => $tagMap[$id] ?? [],
            'started_at'     => $row['started_at'] ?? null,
            'done_at'        => $row['done_at'] ?? null,
            'created_by'     => (int) $row['created_by'],
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'] ?? null,
        ];
    }

    private function loadTagsBatch(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $rows = DB::fetchAll(
            "SELECT tt.task_id, tg.name
             FROM task_tags tt
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tt.task_id IN ({$placeholders})
             ORDER BY tg.name",
            $taskIds
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['task_id']][] = $r['name'];
        }
        return $map;
    }
}
