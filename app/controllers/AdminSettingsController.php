<?php
/**
 * AdminSettingsController - System-wide branding, theme, and maintenance settings (AP20).
 */
class AdminSettingsController
{
    /**
     * Show settings page (GET) or save settings (POST).
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $error = null;
        $success = null;
        $activeTab = $_GET['tab'] ?? 'general';
        $allowedTabs = ['general', 'design', 'maintenance', 'info'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'general';
        }

        $settings = SystemSettingsService::get();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'save_general':
                    $result = $this->saveGeneral($settings);
                    $error = $result['error'] ?? null;
                    $success = $result['success'] ?? null;
                    $activeTab = 'general';
                    break;

                case 'remove_logo':
                    $this->removeLogo();
                    $success = 'Logo wurde entfernt.';
                    $activeTab = 'general';
                    break;

                case 'save_design':
                    $result = $this->saveDesign();
                    $error = $result['error'] ?? null;
                    $success = $result['success'] ?? null;
                    $activeTab = 'design';
                    break;

                case 'save_maintenance':
                    $result = $this->saveMaintenance();
                    $error = $result['error'] ?? null;
                    $success = $result['success'] ?? null;
                    $activeTab = 'maintenance';
                    break;

                default:
                    $error = 'Unbekannte Aktion.';
            }

            // Reload settings after save
            SystemSettingsService::clearCache();
            $settings = SystemSettingsService::get();
        }

        $presets = ThemeService::getPresets();
        $versionInfo = $this->getVersionInfo();

        $pageTitle   = 'Einstellungen';
        $contentView = APP_DIR . '/views/admin/settings/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Save general settings (company name, logo).
     */
    private function saveGeneral(array $currentSettings): array
    {
        $companyName = trim($_POST['company_name'] ?? '');
        if (mb_strlen($companyName) > 150) {
            return ['error' => 'Firmenname darf maximal 150 Zeichen lang sein.'];
        }

        $updateData = ['company_name' => $companyName];

        // Handle logo upload
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $filename = SystemSettingsService::uploadLogo($_FILES['logo']);
            if ($filename === null) {
                return ['error' => 'Logo-Upload fehlgeschlagen. Erlaubt: PNG, JPG, SVG, max. 1 MB.'];
            }
            $updateData['logo_path'] = $filename;
        }

        try {
            SystemSettingsService::update($updateData);
            Logger::info('System settings updated (general)', [
                'admin_id' => (int) $_SESSION['user_id'],
            ]);
            return ['success' => 'Allgemeine Einstellungen gespeichert.'];
        } catch (Throwable $e) {
            Logger::error('Failed to save general settings', ['error' => $e->getMessage()]);
            return ['error' => 'Einstellungen konnten nicht gespeichert werden.'];
        }
    }

    /**
     * Remove the logo.
     */
    private function removeLogo(): void
    {
        SystemSettingsService::removeLogo();
        SystemSettingsService::update(['logo_path' => '']);
        Logger::info('Logo removed', ['admin_id' => (int) $_SESSION['user_id']]);
    }

    /**
     * Save design/theme settings.
     */
    private function saveDesign(): array
    {
        $themeMode = $_POST['theme_mode'] ?? 'preset';
        if (!in_array($themeMode, ['preset', 'custom'], true)) {
            $themeMode = 'preset';
        }

        $updateData = ['theme_mode' => $themeMode];

        if ($themeMode === 'preset') {
            $preset = $_POST['theme_preset'] ?? 'blau';
            $validPresets = array_keys(ThemeService::getPresets());
            if (!in_array($preset, $validPresets, true)) {
                return ['error' => 'Ungueltiges Farbschema.'];
            }
            $updateData['theme_preset'] = $preset;
        } else {
            $primary   = $_POST['color_primary'] ?? '';
            $secondary = $_POST['color_secondary'] ?? '';
            $accent    = $_POST['color_accent'] ?? '';

            if (!ThemeService::isValidHex($primary)) {
                return ['error' => 'Primaerfarbe: Ungueltiges HEX-Format (z.B. #2563eb).'];
            }
            if (!ThemeService::isValidHex($secondary)) {
                return ['error' => 'Sekundaerfarbe: Ungueltiges HEX-Format.'];
            }
            if (!ThemeService::isValidHex($accent)) {
                return ['error' => 'Akzentfarbe: Ungueltiges HEX-Format.'];
            }

            $updateData['color_primary']   = $primary;
            $updateData['color_secondary'] = $secondary;
            $updateData['color_accent']    = $accent;
        }

        try {
            SystemSettingsService::update($updateData);
            Logger::info('Theme settings updated', [
                'admin_id'   => (int) $_SESSION['user_id'],
                'theme_mode' => $themeMode,
            ]);
            return ['success' => 'Design-Einstellungen gespeichert.'];
        } catch (Throwable $e) {
            Logger::error('Failed to save design settings', ['error' => $e->getMessage()]);
            return ['error' => 'Design-Einstellungen konnten nicht gespeichert werden.'];
        }
    }

    /**
     * Save maintenance message settings.
     */
    private function saveMaintenance(): array
    {
        $active  = isset($_POST['maintenance_active']) ? 1 : 0;
        $message = trim($_POST['maintenance_message'] ?? '');
        $level   = $_POST['maintenance_level'] ?? 'info';

        if (mb_strlen($message) > 255) {
            return ['error' => 'Hinweistext darf maximal 255 Zeichen lang sein.'];
        }

        if (!in_array($level, ['info', 'warning', 'critical'], true)) {
            $level = 'info';
        }

        try {
            SystemSettingsService::update([
                'maintenance_active'  => $active,
                'maintenance_message' => $message,
                'maintenance_level'   => $level,
            ]);
            Logger::info('Maintenance settings updated', [
                'admin_id' => (int) $_SESSION['user_id'],
                'active'   => $active,
            ]);
            return ['success' => 'Systemhinweise gespeichert.'];
        } catch (Throwable $e) {
            Logger::error('Failed to save maintenance settings', ['error' => $e->getMessage()]);
            return ['error' => 'Systemhinweise konnten nicht gespeichert werden.'];
        }
    }

    /**
     * Load version information from config/version.php.
     */
    private function getVersionInfo(): array
    {
        $versionFile = CONFIG_DIR . '/version.php';
        if (file_exists($versionFile)) {
            return require $versionFile;
        }
        return [
            'version' => 'Unbekannt',
            'name'    => 'WorkPages',
            'repo'    => 'https://github.com/rueetschli/WorkPages',
            'license' => 'MIT',
        ];
    }
}
