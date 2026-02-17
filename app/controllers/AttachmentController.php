<?php
/**
 * AttachmentController - Upload, download and delete file attachments (AP17).
 *
 * All write actions are POST with CSRF protection.
 * Download is GET with access control.
 */
class AttachmentController
{
    /**
     * Upload a file attachment to a page or task.
     * POST /?r=attachment_upload
     */
    public function upload(): void
    {
        Authz::require(Authz::ATTACHMENT_UPLOAD);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $entityType = $_POST['entity_type'] ?? '';
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $userId     = (int) $_SESSION['user_id'];

        // Validate entity_type
        if (!in_array($entityType, ['page', 'task'], true)) {
            Logger::error('Attachment upload: invalid entity_type', ['entity_type' => $entityType]);
            $_SESSION['_flash_error'] = 'Ungueltiger Anhang-Typ.';
            $this->redirect('home');
            return;
        }

        // Validate entity exists
        $entity = AttachmentService::resolveEntity($entityType, $entityId);
        if (!$entity) {
            Logger::error('Attachment upload: entity not found', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
            $_SESSION['_flash_error'] = 'Ziel nicht gefunden.';
            $this->redirect('home');
            return;
        }

        // Team-based edit access check
        if (!AttachmentService::canEditEntity($userId, $entityType, $entityId)) {
            Authz::deny();
        }

        // Check file is present
        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['_flash_error'] = 'Keine Datei ausgewaehlt.';
            $this->redirectToEntity($entityType, $entity);
            return;
        }

        $file = $_FILES['attachment'];

        // Validate
        $validationError = AttachmentService::validate($file, $entityType, $entityId);
        if ($validationError !== null) {
            $_SESSION['_flash_error'] = $validationError;
            $this->redirectToEntity($entityType, $entity);
            return;
        }

        try {
            $teamId = AttachmentService::getEntityTeamId($entityType, $entityId);

            $attachmentId = AttachmentService::store(
                $file,
                $entityType,
                $entityId,
                $userId,
                $teamId
            );

            $attachment = Attachment::findById($attachmentId);

            // Activity log
            ActivityService::log($entityType, $entityId, 'attachment_uploaded', $userId, [
                'attachment_id' => $attachmentId,
                'original_name' => $attachment['original_name'] ?? $file['name'],
                'file_size'     => (int) $file['size'],
            ]);

            Logger::info('Attachment uploaded', [
                'attachment_id' => $attachmentId,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'original_name' => $file['name'],
                'size'          => $file['size'],
            ]);

            // AP15: Notification event for watchers
            EventService::emit('attachment.uploaded', $entityType, $entityId, $userId, [
                'attachment_id' => $attachmentId,
                'original_name' => $attachment['original_name'] ?? $file['name'],
            ]);

            $_SESSION['_flash_success'] = 'Datei hochgeladen.';
        } catch (Throwable $e) {
            Logger::error('Attachment upload failed', [
                'error'       => $e->getMessage(),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
            $_SESSION['_flash_error'] = 'Datei konnte nicht hochgeladen werden.';
        }

        $this->redirectToEntity($entityType, $entity);
    }

    /**
     * Download a file attachment (secure streaming).
     * GET /?r=attachment_download&id=…
     */
    public function download(): void
    {
        Authz::require(Authz::ATTACHMENT_DOWNLOAD);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $attachment = Attachment::findById($id);
        if (!$attachment) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Team-based view access check
        $userId = (int) $_SESSION['user_id'];
        if (!AttachmentService::canViewEntity($userId, $attachment['entity_type'], (int) $attachment['entity_id'])) {
            Authz::deny();
        }

        // Determine disposition
        $disposition = $_GET['disposition'] ?? 'attachment';
        if (!in_array($disposition, ['attachment', 'inline'], true)) {
            $disposition = 'attachment';
        }

        AttachmentService::stream($attachment, $disposition);
    }

    /**
     * Soft-delete a file attachment.
     * POST /?r=attachment_delete&id=…
     */
    public function delete(): void
    {
        Authz::require(Authz::ATTACHMENT_DELETE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $id     = (int) ($_GET['id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        $attachment = Attachment::findById($id);
        if (!$attachment) {
            http_response_code(404);
            $this->redirect('home');
            return;
        }

        // Team-based edit access check
        if (!AttachmentService::canEditEntity($userId, $attachment['entity_type'], (int) $attachment['entity_id'])) {
            Authz::deny();
        }

        try {
            AttachmentService::softDelete($id, $userId);

            // Activity log
            ActivityService::log(
                $attachment['entity_type'],
                (int) $attachment['entity_id'],
                'attachment_deleted',
                $userId,
                [
                    'attachment_id' => $id,
                    'original_name' => $attachment['original_name'],
                ]
            );

            Logger::info('Attachment deleted', [
                'attachment_id' => $id,
                'entity_type'   => $attachment['entity_type'],
                'entity_id'     => $attachment['entity_id'],
            ]);

            $_SESSION['_flash_success'] = 'Anhang geloescht.';
        } catch (Throwable $e) {
            Logger::error('Attachment delete failed', [
                'error'         => $e->getMessage(),
                'attachment_id' => $id,
            ]);
            $_SESSION['_flash_error'] = 'Anhang konnte nicht geloescht werden.';
        }

        // Redirect back to entity
        $entity = AttachmentService::resolveEntity(
            $attachment['entity_type'],
            (int) $attachment['entity_id']
        );

        if ($entity) {
            $this->redirectToEntity($attachment['entity_type'], $entity);
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
