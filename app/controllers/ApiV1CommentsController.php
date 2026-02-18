<?php
/**
 * ApiV1CommentsController - REST API v1 for comments (AP19).
 *
 * Endpoints:
 *   GET  /api/v1/comments                  List comments (filtered by entity)
 *   POST /api/v1/comments                  Create comment
 *   GET  /api/v1/comments/{id}             Get single comment
 *   DELETE /api/v1/comments/{id}           Soft delete comment
 */
class ApiV1CommentsController
{
    /**
     * GET /api/v1/comments
     */
    public function index(): void
    {
        ApiScopeService::requireScope('comments:read');

        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int) ($_GET['entity_id'] ?? 0);

        if (!in_array($entityType, ['page', 'task'], true) || $entityId <= 0) {
            ApiResponse::badRequest('entity_type (page|task) und entity_id sind erforderlich.');
        }

        // Check entity access
        if (!$this->canViewEntity($entityType, $entityId)) {
            ApiResponse::forbidden();
        }

        [$limit, $cursor] = ApiRouter::parsePagination();

        $where = ['c.entity_type = ?', 'c.entity_id = ?', 'c.deleted_at IS NULL'];
        $params = [$entityType, $entityId];

        if ($cursor !== null) {
            $where[] = 'c.id > ?';
            $params[] = (int) $cursor;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = DB::fetchAll(
            "SELECT c.id, c.entity_type, c.entity_id, c.body_md, c.created_by,
                    u.name AS author_name, c.created_at
             FROM comments c
             LEFT JOIN users u ON u.id = c.created_by
             {$whereSql}
             ORDER BY c.id ASC
             LIMIT " . ($limit + 1),
            $params
        );

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $lastRow = end($rows);
            $nextCursor = (string) $lastRow['id'];
        }

        $data = array_map(fn($r) => $this->formatComment($r), $rows);
        ApiResponse::paginated($data, $nextCursor);
    }

    /**
     * GET /api/v1/comments/{id}
     */
    public function show(int $id): void
    {
        ApiScopeService::requireScope('comments:read');

        $comment = Comment::findById($id);
        if (!$comment) {
            ApiResponse::notFound('Kommentar nicht gefunden.');
        }

        if (!$this->canViewEntity($comment['entity_type'], (int) $comment['entity_id'])) {
            ApiResponse::forbidden();
        }

        ApiResponse::json($this->formatComment($comment));
    }

    /**
     * POST /api/v1/comments
     */
    public function create(): void
    {
        ApiScopeService::requireScope('comments:write');
        ApiScopeService::requireWrite();

        $rawBody = ApiRouter::getRawBody();
        $idempotency = IdempotencyService::handleRequest(
            ApiScopeService::getKeyPrefix(),
            $rawBody
        );

        $data = ApiRouter::getJsonBody();
        $userId = ApiScopeService::getUserId();

        $entityType = $data['entity_type'] ?? '';
        $entityId = (int) ($data['entity_id'] ?? 0);
        $bodyMd = trim($data['body_md'] ?? '');

        if (!in_array($entityType, ['page', 'task'], true)) {
            ApiResponse::unprocessable('entity_type muss page oder task sein.');
        }
        if ($entityId <= 0) {
            ApiResponse::unprocessable('entity_id ist erforderlich.');
        }

        // Validate body
        $validationError = Comment::validateBody($bodyMd);
        if ($validationError !== null) {
            ApiResponse::unprocessable($validationError);
        }

        // Check entity exists and user has access
        if (!$this->canViewEntity($entityType, $entityId)) {
            ApiResponse::forbidden('Kein Zugriff auf die Ziel-Entitaet.');
        }

        try {
            $commentId = Comment::create($entityType, $entityId, $bodyMd, $userId);

            ActivityService::log($entityType, $entityId, 'comment_created', $userId, [
                'comment_id' => $commentId,
                'source'     => 'api',
            ]);

            WatcherService::autoWatchOnCreate($entityType, $entityId, $userId);
            EventService::emit('comment.created', $entityType, $entityId, $userId, [
                'comment_id' => $commentId,
            ]);

            $comment = Comment::findById($commentId);
            $result = $this->formatComment($comment);

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
            Logger::error('API comment create failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Kommentar konnte nicht erstellt werden.');
        }
    }

    /**
     * PATCH /api/v1/comments/{id} - Not supported.
     */
    public function update(int $id): void
    {
        ApiResponse::error('method_not_allowed', 'Kommentare koennen nicht bearbeitet werden.', 405);
    }

    /**
     * DELETE /api/v1/comments/{id}
     */
    public function delete(int $id): void
    {
        ApiScopeService::requireScope('comments:write');
        ApiScopeService::requireWrite();

        $comment = Comment::findById($id);
        if (!$comment) {
            ApiResponse::notFound('Kommentar nicht gefunden.');
        }

        $userId = ApiScopeService::getUserId();
        $userRole = ApiScopeService::getUserRole();

        // Only the author or admin can delete
        if ((int) $comment['created_by'] !== $userId && $userRole !== 'admin') {
            ApiResponse::forbidden('Nur der Autor oder ein Admin kann diesen Kommentar loeschen.');
        }

        try {
            Comment::softDelete($id, $userId);
            Logger::info('Comment soft-deleted via API', ['comment_id' => $id]);
            ApiResponse::noContent();
        } catch (\Throwable $e) {
            Logger::error('API comment delete failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Kommentar konnte nicht geloescht werden.');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatComment(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'entity_type' => $row['entity_type'],
            'entity_id'   => (int) $row['entity_id'],
            'body_md'     => $row['body_md'],
            'created_by'  => (int) $row['created_by'],
            'author_name' => $row['author_name'] ?? null,
            'created_at'  => $row['created_at'],
        ];
    }

    private function canViewEntity(string $entityType, int $entityId): bool
    {
        if ($entityType === 'task') {
            $task = Task::findById($entityId);
            return $task && ApiScopeService::canViewTask($task);
        }
        if ($entityType === 'page') {
            $page = Page::findById($entityId);
            return $page && ApiScopeService::canViewPage($page);
        }
        return false;
    }
}
