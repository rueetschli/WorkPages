<?php
/**
 * SystemSettingsService - Manages system-wide settings (AP20).
 *
 * Handles branding (company name, logo), theme configuration,
 * and maintenance messages. Uses lazy table creation for
 * backwards compatibility with existing installations.
 */
class SystemSettingsService
{
    private static ?array $cache = null;
    private static bool $tableChecked = false;

    /**
     * Get all system settings. Returns defaults if table does not exist yet.
     */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = self::defaults();

        if (!self::ensureTable()) {
            self::$cache = $defaults;
            return self::$cache;
        }

        try {
            $row = DB::fetch("SELECT * FROM system_settings WHERE id = 1");
            if ($row) {
                self::$cache = array_merge($defaults, $row);
            } else {
                self::$cache = $defaults;
            }
        } catch (Throwable $e) {
            Logger::error('Failed to load system settings', ['error' => $e->getMessage()]);
            self::$cache = $defaults;
        }

        return self::$cache;
    }

    /**
     * Get a single setting value.
     */
    public static function value(string $key, mixed $default = ''): mixed
    {
        $settings = self::get();
        return $settings[$key] ?? $default;
    }

    /**
     * Get the effective company name (fallback to WorkPages).
     */
    public static function companyName(): string
    {
        $name = trim(self::value('company_name', ''));
        return $name !== '' ? $name : ($GLOBALS['config']['APP_NAME'] ?? 'WorkPages');
    }

    /**
     * Get the logo URL for display, or empty string if none.
     */
    public static function logoUrl(): string
    {
        $path = trim(self::value('logo_path', ''));
        if ($path === '') {
            return '';
        }

        $fullPath = ROOT_DIR . '/public/assets/branding/' . basename($path);
        if (!file_exists($fullPath)) {
            return '';
        }

        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        return $baseUrl . '/assets/branding/' . rawurlencode(basename($path));
    }

    /**
     * Check if maintenance banner is active.
     */
    public static function isMaintenanceActive(): bool
    {
        return (int) self::value('maintenance_active', 0) === 1;
    }

    /**
     * Update system settings.
     */
    public static function update(array $data): void
    {
        self::ensureTable();

        $allowed = [
            'company_name', 'logo_path', 'theme_mode', 'theme_preset',
            'color_primary', 'color_secondary', 'color_accent',
            'maintenance_message', 'maintenance_level', 'maintenance_active',
            'default_language',
        ];

        $sets = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`{$field}` = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = "`updated_at` = NOW()";
        $params[] = 1; // WHERE id = 1

        $sql = "UPDATE system_settings SET " . implode(', ', $sets) . " WHERE id = ?";
        DB::query($sql, $params);

        self::$cache = null;
    }

    /**
     * Handle logo upload. Returns the saved filename or null on error.
     */
    public static function uploadLogo(array $file): ?string
    {
        $allowedMime = ['image/png', 'image/jpeg', 'image/svg+xml'];
        $allowedExt  = ['png', 'jpg', 'jpeg', 'svg'];
        $maxSize     = 1 * 1024 * 1024; // 1 MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            return null;
        }

        $targetDir = ROOT_DIR . '/public/assets/branding';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Remove old logo
        self::removeLogo();

        $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Logger::error('Logo upload failed', ['target' => $targetPath]);
            return null;
        }

        chmod($targetPath, 0644);
        return $filename;
    }

    /**
     * Remove the current logo file from disk.
     */
    public static function removeLogo(): void
    {
        $current = trim(self::value('logo_path', ''));
        if ($current !== '') {
            $fullPath = ROOT_DIR . '/public/assets/branding/' . basename($current);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    /**
     * Default values when no settings exist yet.
     */
    private static function defaults(): array
    {
        return [
            'id'                   => 1,
            'company_name'         => '',
            'logo_path'            => '',
            'theme_mode'           => 'preset',
            'theme_preset'         => 'blau',
            'color_primary'        => '#2563eb',
            'color_secondary'      => '#1e293b',
            'color_accent'         => '#3b82f6',
            'maintenance_message'  => '',
            'maintenance_level'    => 'info',
            'maintenance_active'   => 0,
            'last_backup_at'       => null,
            'last_backup_note'     => '',
            'default_language'     => 'de',
            'updated_at'           => null,
        ];
    }

    /**
     * Lazy table creation for backwards compatibility.
     * Returns true if the table exists (or was just created).
     */
    private static function ensureTable(): bool
    {
        if (self::$tableChecked) {
            return true;
        }

        try {
            DB::fetch("SELECT 1 FROM system_settings LIMIT 1");
            self::$tableChecked = true;
            return true;
        } catch (Throwable $e) {
            // Table doesn't exist - try to create it
            try {
                $migrationFile = APP_DIR . '/migrations/022_add_system_settings.sql';
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
                self::$tableChecked = true;
                return true;
            } catch (Throwable $e2) {
                Logger::error('Failed to create system_settings table', [
                    'error' => $e2->getMessage(),
                ]);
                return false;
            }
        }
    }

    /**
     * Clear the cached settings (useful after update).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
