<?php
/**
 * AttachmentService - File upload validation, storage, streaming and deletion (AP17).
 *
 * All file operations go through this service. Files are stored outside
 * the public directory and served via controlled streaming.
 */
class AttachmentService
{
    /**
     * Default config values (used when config keys are missing).
     */
    private const DEFAULT_MAX_MB = 20;
    private const DEFAULT_MAX_PER_ENTITY = 50;

    private const DEFAULT_ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const DEFAULT_ALLOWED_EXT = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'txt', 'csv', 'docx', 'xlsx',
    ];

    /**
     * MIME types allowed for inline display (disposition=inline).
     */
    private const INLINE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    // ── Configuration helpers ────────────────────────────────────────

    public static function getMaxBytes(): int
    {
        $mb = (int) ($GLOBALS['config']['UPLOAD_MAX_MB'] ?? self::DEFAULT_MAX_MB);
        return $mb * 1024 * 1024;
    }

    public static function getMaxMb(): int
    {
        return (int) ($GLOBALS['config']['UPLOAD_MAX_MB'] ?? self::DEFAULT_MAX_MB);
    }

    public static function getMaxPerEntity(): int
    {
        return (int) ($GLOBALS['config']['UPLOAD_MAX_PER_ENTITY'] ?? self::DEFAULT_MAX_PER_ENTITY);
    }

    public static function getAllowedMime(): array
    {
        return $GLOBALS['config']['UPLOAD_ALLOWED_MIME'] ?? self::DEFAULT_ALLOWED_MIME;
    }

    public static function getAllowedExt(): array
    {
        return $GLOBALS['config']['UPLOAD_ALLOWED_EXT'] ?? self::DEFAULT_ALLOWED_EXT;
    }

    public static function getUploadDir(): string
    {
        return $GLOBALS['config']['UPLOAD_DIR'] ?? ROOT_DIR . '/storage/uploads';
    }

    /**
     * Return human-readable list of allowed extensions for display.
     */
    public static function getAllowedExtDisplay(): string
    {
        $exts = self::getAllowedExt();
        return implode(', ', array_map(fn($e) => '.' . $e, $exts));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * Validate an uploaded file. Returns error message or null.
     *
     * @param array  $file        $_FILES['attachment'] entry
     * @param string $entityType  'page' or 'task'
     * @param int    $entityId    ID of the entity
     */
    public static function validate(array $file, string $entityType, int $entityId): ?string
    {
        // Check PHP upload error codes
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return self::uploadErrorMessage($file['error']);
        }

        // Check file actually exists and was uploaded
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'Keine gueltige Upload-Datei.';
        }

        // Check file size (server-side, independent of php.ini)
        $maxBytes = self::getMaxBytes();
        if ($file['size'] > $maxBytes) {
            return 'Datei ist zu gross. Maximum: ' . self::getMaxMb() . ' MB.';
        }

        // Check extension
        $ext = self::getSafeExtension($file['name']);
        if ($ext === '' || !in_array($ext, self::getAllowedExt(), true)) {
            return 'Dateityp nicht erlaubt. Erlaubt: ' . self::getAllowedExtDisplay() . '.';
        }

        // Check MIME type via finfo (not trusting the client-reported type)
        $detectedMime = self::detectMime($file['tmp_name']);
        if ($detectedMime === false || !in_array($detectedMime, self::getAllowedMime(), true)) {
            return 'Dateityp nicht erlaubt (MIME: ' . ($detectedMime ?: 'unbekannt') . ').';
        }

        // Check attachment count per entity
        $currentCount = Attachment::countFor($entityType, $entityId);
        if ($currentCount >= self::getMaxPerEntity()) {
            return 'Maximale Anzahl Anhaenge erreicht (' . self::getMaxPerEntity() . ').';
        }

        return null;
    }

    // ── Storage ──────────────────────────────────────────────────────

    /**
     * Store an uploaded file and create the attachment record.
     *
     * @param array  $file        $_FILES['attachment'] entry
     * @param string $entityType  'page' or 'task'
     * @param int    $entityId    ID of the entity
     * @param int    $userId      ID of the uploading user
     * @param int|null $teamId    Team ID from the entity
     * @return int  Attachment ID
     * @throws \RuntimeException on storage failure
     */
    public static function store(array $file, string $entityType, int $entityId, int $userId, ?int $teamId = null): int
    {
        $ext = self::getSafeExtension($file['name']);
        $detectedMime = self::detectMime($file['tmp_name']);
        $originalName = self::sanitizeFilename($file['name']);

        // Build storage path: /storage/uploads/{entity_type}/{YYYY}/{MM}/
        $year = date('Y');
        $month = date('m');
        $relDir = $entityType . '/' . $year . '/' . $month;
        $absDir = self::getUploadDir() . '/' . $relDir;

        // Ensure directory exists
        if (!is_dir($absDir)) {
            $created = @mkdir($absDir, 0755, true);
            if (!$created) {
                throw new \RuntimeException('Upload-Verzeichnis konnte nicht erstellt werden: ' . $relDir);
            }
        }

        // Generate random component for filename
        $random = bin2hex(random_bytes(8));

        // We need a temporary ID, so create the DB record first with a placeholder path,
        // then update. Alternative: use random-only naming without attachment ID.
        // Using random-only naming for simplicity (no circular dependency).
        $storedName = $random . '.' . $ext;
        $storedPath = $relDir . '/' . $storedName;
        $absPath = $absDir . '/' . $storedName;

        // Calculate checksum before move
        $checksum = @hash_file('sha256', $file['tmp_name']);

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        // Set restrictive permissions
        @chmod($absPath, 0644);

        // Create DB record
        $attachmentId = Attachment::create([
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'team_id'         => $teamId,
            'original_name'   => $originalName,
            'stored_name'     => $storedName,
            'stored_path'     => $storedPath,
            'mime_type'       => $detectedMime ?: 'application/octet-stream',
            'file_ext'        => $ext,
            'file_size'       => (int) $file['size'],
            'checksum_sha256' => $checksum ?: null,
            'uploaded_by'     => $userId,
        ]);

        // Rename file to include attachment ID for traceability
        $finalName = $attachmentId . '_' . $random . '.' . $ext;
        $finalPath = $relDir . '/' . $finalName;
        $finalAbs = $absDir . '/' . $finalName;

        if (rename($absPath, $finalAbs)) {
            // Update stored_name and stored_path in DB
            DB::query(
                'UPDATE attachments SET stored_name = ?, stored_path = ? WHERE id = ?',
                [$finalName, $finalPath, $attachmentId]
            );
        }

        return $attachmentId;
    }

    // ── Streaming ────────────────────────────────────────────────────

    /**
     * Stream a file to the browser with controlled headers.
     *
     * @param array  $attachment  Attachment row from DB
     * @param string $disposition 'attachment' or 'inline'
     */
    public static function stream(array $attachment, string $disposition = 'attachment'): void
    {
        $absPath = self::getUploadDir() . '/' . $attachment['stored_path'];

        if (!file_exists($absPath) || !is_readable($absPath)) {
            http_response_code(404);
            Logger::error('Attachment file not found on disk', [
                'attachment_id' => $attachment['id'],
                'stored_path'   => $attachment['stored_path'],
            ]);
            echo 'Datei nicht gefunden.';
            exit;
        }

        // Validate disposition
        if ($disposition === 'inline') {
            if (!in_array($attachment['mime_type'], self::INLINE_MIME_TYPES, true)) {
                $disposition = 'attachment';
            }
        }

        // Sanitize original name for Content-Disposition header
        $safeFilename = self::sanitizeFilename($attachment['original_name']);
        $encodedFilename = rawurlencode($safeFilename);

        // Clean output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . filesize($absPath));
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . $encodedFilename);
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        // Prevent script execution
        if ($disposition === 'inline') {
            header('Content-Security-Policy: sandbox');
        }

        // Stream in 8KB chunks to avoid memory issues
        $handle = fopen($absPath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            exit;
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }

        fclose($handle);
        exit;
    }

    // ── Deletion ─────────────────────────────────────────────────────

    /**
     * Soft-delete an attachment (DB only, file stays on disk).
     */
    public static function softDelete(int $attachmentId, int $userId): void
    {
        Attachment::softDelete($attachmentId);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Detect MIME type using finfo.
     *
     * @return string|false
     */
    private static function detectMime(string $filePath): string|false
    {
        if (!function_exists('finfo_open')) {
            Logger::error('finfo extension not available for MIME detection');
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // CSV files are often detected as text/plain - accept that
        return $mime;
    }

    /**
     * Get a safe, lowercase file extension.
     */
    private static function getSafeExtension(string $filename): string
    {
        // Remove path components
        $basename = basename($filename);
        $dot = strrpos($basename, '.');
        if ($dot === false) {
            return '';
        }

        $ext = substr($basename, $dot + 1);
        // Only allow alphanumeric extensions
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        return strtolower($ext);
    }

    /**
     * Sanitize a filename for safe display and headers.
     * Removes path traversal, null bytes, and control characters.
     */
    private static function sanitizeFilename(string $filename): string
    {
        // Remove path separators
        $filename = str_replace(['/', '\\', "\0"], '', $filename);

        // Remove control characters
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

        // Trim dots and spaces from edges
        $filename = trim($filename, '. ');

        if ($filename === '') {
            $filename = 'upload';
        }

        // Limit length
        if (mb_strlen($filename, 'UTF-8') > 200) {
            $filename = mb_substr($filename, 0, 200, 'UTF-8');
        }

        return $filename;
    }

    /**
     * Map PHP upload error codes to German messages.
     */
    private static function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'Datei ueberschreitet die maximale Upload-Groesse des Servers.',
            UPLOAD_ERR_FORM_SIZE  => 'Datei ueberschreitet die maximale Upload-Groesse.',
            UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewaehlt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporaeres Verzeichnis fehlt (Serverkonfiguration).',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden (Serverkonfiguration).',
            UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Extension gestoppt.',
            default               => 'Unbekannter Upload-Fehler (Code: ' . $errorCode . ').',
        };
    }

    /**
     * Resolve entity for access checks. Returns entity array or null.
     *
     * @return array|null
     */
    public static function resolveEntity(string $entityType, int $entityId): ?array
    {
        if ($entityType === 'page') {
            return Page::findById($entityId);
        }
        if ($entityType === 'task') {
            return Task::findById($entityId);
        }
        return null;
    }

    /**
     * Check if user can view the entity (team visibility).
     */
    public static function canViewEntity(int $userId, string $entityType, int $entityId): bool
    {
        $entity = self::resolveEntity($entityType, $entityId);
        if (!$entity) {
            return false;
        }

        if ($entityType === 'page') {
            return TeamService::canViewPage($userId, $entity);
        }
        if ($entityType === 'task') {
            return TeamService::canViewTask($userId, $entity);
        }

        return false;
    }

    /**
     * Check if user can edit the entity (team edit rights).
     */
    public static function canEditEntity(int $userId, string $entityType, int $entityId): bool
    {
        $entity = self::resolveEntity($entityType, $entityId);
        if (!$entity) {
            return false;
        }

        if ($entityType === 'page') {
            return TeamService::canEditPage($userId, $entity);
        }
        if ($entityType === 'task') {
            return TeamService::canEditTask($userId, $entity);
        }

        return false;
    }

    /**
     * Get the team_id from an entity.
     */
    public static function getEntityTeamId(string $entityType, int $entityId): ?int
    {
        $entity = self::resolveEntity($entityType, $entityId);
        if (!$entity) {
            return null;
        }

        if ($entityType === 'task') {
            return TeamService::resolveTaskTeamId($entity);
        }

        return !empty($entity['team_id']) ? (int) $entity['team_id'] : null;
    }
}
