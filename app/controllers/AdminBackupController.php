<?php
/**
 * AdminBackupController - Backup & Operations guidance page (AP29).
 *
 * Routes:
 *   GET  /?r=admin_backup             - Backup & Operations checklist
 *   POST /?r=admin_backup_save        - Save last backup date/note
 */
class AdminBackupController
{
    /**
     * Display the backup & operations guidance page.
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $settings     = SystemSettingsService::get();
        $lastBackupAt   = $settings['last_backup_at'] ?? null;
        $lastBackupNote = $settings['last_backup_note'] ?? '';

        $pageTitle   = t('backup.page_title');
        $contentView = APP_DIR . '/views/admin/backup/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Save the last backup date and optional note (POST).
     */
    public function save(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_backup');
            return;
        }

        Security::csrfGuard();

        $backupDate = trim($_POST['last_backup_at'] ?? '');
        $backupNote = trim($_POST['last_backup_note'] ?? '');

        // Validate date format (YYYY-MM-DD or YYYY-MM-DD HH:MM)
        $parsedDate = null;
        if ($backupDate !== '') {
            // Accept YYYY-MM-DD or YYYY-MM-DD HH:MM or YYYY-MM-DD HH:MM:SS
            $dt = date_create($backupDate);
            if ($dt === false) {
                $_SESSION['_flash_error'] = t('backup.invalid_date');
                $this->redirect('admin_backup');
                return;
            }
            $parsedDate = $dt->format('Y-m-d H:i:s');
        }

        // Truncate note to 255 chars
        $backupNote = mb_substr($backupNote, 0, 255, 'UTF-8');

        try {
            $this->ensureBackupColumns();

            if ($parsedDate !== null) {
                DB::query(
                    "UPDATE system_settings SET last_backup_at = ?, last_backup_note = ?, updated_at = NOW() WHERE id = 1",
                    [$parsedDate, $backupNote]
                );
            } else {
                DB::query(
                    "UPDATE system_settings SET last_backup_at = NULL, last_backup_note = ?, updated_at = NOW() WHERE id = 1",
                    [$backupNote]
                );
            }

            SystemSettingsService::clearCache();

            $_SESSION['_flash_success'] = t('backup.save_success');

            ActivityService::log('system', 0, 'backup_date_updated', (int) ($_SESSION['user_id'] ?? 0), [
                'date' => $parsedDate ?? '',
                'note' => $backupNote,
            ]);
        } catch (Throwable $e) {
            Logger::error('AdminBackup: failed to save backup date', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('errors.server_error');
        }

        $this->redirect('admin_backup');
    }

    /**
     * Ensure the backup columns exist in system_settings (lazy migration).
     */
    private function ensureBackupColumns(): void
    {
        try {
            DB::fetch("SELECT last_backup_at FROM system_settings WHERE id = 1");
        } catch (Throwable $e) {
            // Columns missing – run migration
            $migrationFile = APP_DIR . '/migrations/029_add_backup_fields.sql';
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => $s !== '' && !str_starts_with($s, '--')
                );
                foreach ($statements as $stmt) {
                    $clean = trim(preg_replace('/^--.*$/m', '', $stmt));
                    if ($clean !== '') {
                        DB::pdo()->exec($clean);
                    }
                }
            }
        }
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
