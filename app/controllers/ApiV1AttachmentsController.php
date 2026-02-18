<?php
/**
 * ApiV1AttachmentsController - REST API v1 for attachments (AP19).
 *
 * Endpoints:
 *   GET    /api/v1/attachments                     List attachments (filtered by entity)
 *   POST   /api/v1/attachments                     Upload attachment (multipart/form-data)
 *   GET    /api/v1/attachments/{id}                Get attachment metadata
 *   GET    /api/v1/attachments/{id}/download       Download file
 *   DELETE /api/v1/attachments/{id}                Soft delete attachment
 */
class ApiV1AttachmentsController
{
    /**
     * GET /api/v1/attachments
     */
    public function index(): void
    {
        ApiScopeService::requireScope('attachments:read');

        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int) ($_GET['entity_id'] ?? 0);

        if (!in_array($entityType, ['page', 'task'], true) || $entityId <= 0) {
            ApiResponse::badRequest('entity_type (page|task) und entity_id sind erforderlich.');
        }

        if (!AttachmentService::canViewEntity(ApiScopeService::getUserId(), $entityType, $entityId)) {
            ApiResponse::forbidden();
        }

        $attachments = Attachment::listFor($entityType, $entityId);

        $data = array_map(fn($a) => $this->formatAttachment($a), $attachments);
        ApiResponse::json(['data' => $data]);
    }

    /**
     * GET /api/v1/attachments/{id}
     */
    public function show(int $id): void
    {
        ApiScopeService::requireScope('attachments:read');

        $attachment = Attachment::findById($id);
        if (!$attachment) {
            ApiResponse::notFound('Anhang nicht gefunden.');
        }

        if (!AttachmentService::canViewEntity(
            ApiScopeService::getUserId(),
            $attachment['entity_type'],
            (int) $attachment['entity_id']
        )) {
            ApiResponse::forbidden();
        }

        ApiResponse::json($this->formatAttachment($attachment));
    }

    /**
     * POST /api/v1/attachments (multipart/form-data)
     */
    public function create(): void
    {
        ApiScopeService::requireScope('attachments:write');
        ApiScopeService::requireWrite();

        $entityType = $_POST['entity_type'] ?? '';
        $entityId = (int) ($_POST['entity_id'] ?? 0);

        if (!in_array($entityType, ['page', 'task'], true) || $entityId <= 0) {
            ApiResponse::unprocessable('entity_type (page|task) und entity_id sind erforderlich.');
        }

        $userId = ApiScopeService::getUserId();

        if (!AttachmentService::canEditEntity($userId, $entityType, $entityId)) {
            ApiResponse::forbidden('Kein Schreibzugriff auf die Ziel-Entitaet.');
        }

        // Check file upload
        if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            ApiResponse::unprocessable('Datei (file) ist erforderlich.');
        }

        $file = $_FILES['file'];

        // Validate
        $validationError = AttachmentService::validate($file, $entityType, $entityId);
        if ($validationError !== null) {
            ApiResponse::unprocessable($validationError);
        }

        try {
            $teamId = AttachmentService::getEntityTeamId($entityType, $entityId);
            $attachmentId = AttachmentService::store($file, $entityType, $entityId, $userId, $teamId);

            ActivityService::log($entityType, $entityId, 'attachment_added', $userId, [
                'attachment_id' => $attachmentId,
                'filename'      => $file['name'],
                'source'        => 'api',
            ]);

            EventService::emit('attachment.added', $entityType, $entityId, $userId, [
                'attachment_id' => $attachmentId,
                'filename'      => $file['name'],
            ]);

            $attachment = Attachment::findById($attachmentId);
            ApiResponse::created($this->formatAttachment($attachment));
        } catch (\Throwable $e) {
            Logger::error('API attachment upload failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Datei konnte nicht hochgeladen werden.');
        }
    }

    /**
     * PATCH /api/v1/attachments/{id} - Not supported.
     */
    public function update(int $id): void
    {
        ApiResponse::error('method_not_allowed', 'Anhaenge koennen nicht bearbeitet werden.', 405);
    }

    /**
     * DELETE /api/v1/attachments/{id}
     */
    public function delete(int $id): void
    {
        ApiScopeService::requireScope('attachments:write');
        ApiScopeService::requireWrite();

        $attachment = Attachment::findById($id);
        if (!$attachment) {
            ApiResponse::notFound('Anhang nicht gefunden.');
        }

        $userId = ApiScopeService::getUserId();
        if (!AttachmentService::canEditEntity($userId, $attachment['entity_type'], (int) $attachment['entity_id'])) {
            ApiResponse::forbidden();
        }

        try {
            AttachmentService::softDelete($id, $userId);
            Logger::info('Attachment soft-deleted via API', ['attachment_id' => $id]);
            ApiResponse::noContent();
        } catch (\Throwable $e) {
            Logger::error('API attachment delete failed', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Anhang konnte nicht geloescht werden.');
        }
    }

    /**
     * GET /api/v1/attachments/{id}/download
     */
    public function download(int $id): void
    {
        ApiScopeService::requireScope('attachments:read');

        $attachment = Attachment::findById($id);
        if (!$attachment) {
            ApiResponse::notFound('Anhang nicht gefunden.');
        }

        if (!AttachmentService::canViewEntity(
            ApiScopeService::getUserId(),
            $attachment['entity_type'],
            (int) $attachment['entity_id']
        )) {
            ApiResponse::forbidden();
        }

        // Stream the file (exits)
        AttachmentService::stream($attachment, 'attachment');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatAttachment(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'entity_type'   => $row['entity_type'],
            'entity_id'     => (int) $row['entity_id'],
            'original_name' => $row['original_name'],
            'mime_type'     => $row['mime_type'],
            'file_size'     => (int) $row['file_size'],
            'uploaded_by'   => (int) $row['uploaded_by'],
            'uploader_name' => $row['uploader_name'] ?? null,
            'created_at'    => $row['created_at'],
        ];
    }
}
