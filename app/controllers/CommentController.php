<?php
/**
 * CommentController - Handles comment creation (and optional deletion).
 */
class CommentController
{
    /**
     * Create a new comment on a Page or Task.
     * POST only, CSRF protected, requires member or admin role.
     */
    public function create(): void
    {
        Authz::require(Authz::COMMENT_CREATE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $entityType = $_POST['entity_type'] ?? '';
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $bodyMd     = $_POST['body_md'] ?? '';
        $userId     = (int) $_SESSION['user_id'];

        // Validate entity_type
        if (!in_array($entityType, ['page', 'task'], true)) {
            http_response_code(400);
            Logger::error('Comment create: invalid entity_type', ['entity_type' => $entityType]);
            $this->redirect('home');
            return;
        }

        // Validate entity exists
        $entity = null;
        if ($entityType === 'page') {
            $entity = Page::findById($entityId);
        } else {
            $entity = Task::findById($entityId);
        }

        if (!$entity) {
            http_response_code(404);
            Logger::error('Comment create: entity not found', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
            $this->redirect('home');
            return;
        }

        // Validate body
        $bodyMd = trim($bodyMd);
        $validationError = Comment::validateBody($bodyMd);
        if ($validationError !== null) {
            // Store error in session flash and redirect back
            $_SESSION['_flash_error'] = $validationError;
            $this->redirectToEntity($entityType, $entity);
            return;
        }

        try {
            $commentId = Comment::create($entityType, $entityId, $bodyMd, $userId);

            ActivityService::log($entityType, $entityId, 'comment_created', $userId, [
                'comment_id' => $commentId,
            ]);

            Logger::info('Comment created', [
                'comment_id'  => $commentId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to create comment', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Kommentar konnte nicht gespeichert werden.';
        }

        $this->redirectToEntity($entityType, $entity);
    }

    /**
     * Soft-delete a comment.
     * POST only, CSRF protected, requires member or admin role.
     */
    public function delete(): void
    {
        Authz::require(Authz::COMMENT_DELETE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $commentId = (int) ($_GET['id'] ?? 0);
        $userId    = (int) $_SESSION['user_id'];

        $comment = Comment::findById($commentId);
        if (!$comment) {
            http_response_code(404);
            $this->redirect('home');
            return;
        }

        try {
            Comment::softDelete($commentId, $userId);

            ActivityService::log(
                $comment['entity_type'],
                (int) $comment['entity_id'],
                'comment_deleted',
                $userId,
                ['comment_id' => $commentId]
            );

            Logger::info('Comment deleted', ['comment_id' => $commentId]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete comment', ['error' => $e->getMessage()]);
        }

        // Redirect back to entity
        $entity = null;
        if ($comment['entity_type'] === 'page') {
            $entity = Page::findById((int) $comment['entity_id']);
        } else {
            $entity = Task::findById((int) $comment['entity_id']);
        }

        if ($entity) {
            $this->redirectToEntity($comment['entity_type'], $entity);
        } else {
            $this->redirect('home');
        }
    }

    /**
     * Redirect to the detail view of the given entity.
     */
    private function redirectToEntity(string $entityType, array $entity): void
    {
        if ($entityType === 'page') {
            $this->redirect('page_view&slug=' . urlencode($entity['slug']));
        } else {
            $this->redirect('task_view&id=' . (int) $entity['id']);
        }
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
