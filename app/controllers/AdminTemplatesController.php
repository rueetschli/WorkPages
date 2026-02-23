<?php
/**
 * AdminTemplatesController - Template management for admins (AP31).
 *
 * Routes:
 *   admin_templates          - List templates with import status
 *   admin_templates_import   - Import a single template (POST)
 *   admin_templates_import_all - Import all templates for a language (POST)
 *   admin_templates_refresh  - Refresh templates from GitHub (POST)
 */
class AdminTemplatesController
{
    /**
     * List all available templates with their import status.
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $templates = TemplateService::getTemplatesWithStatus();

        // Group by category
        $grouped = [];
        foreach ($templates as $tpl) {
            $grouped[$tpl['category']][] = $tpl;
        }

        // Count stats
        $stats = [
            'total'            => count($templates),
            'imported'         => 0,
            'not_imported'     => 0,
            'update_available' => 0,
        ];
        foreach ($templates as $tpl) {
            $stats[$tpl['status']]++;
        }

        $hasZipArchive = class_exists('ZipArchive');

        $pageTitle   = t('templates.admin_title');
        $contentView = APP_DIR . '/views/admin/templates/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Import a single template (POST).
     */
    public function import(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_templates');
            return;
        }

        Security::csrfGuard();

        $templateKey = trim($_POST['template_key'] ?? '');
        if ($templateKey === '') {
            $_SESSION['_flash_error'] = t('templates.no_key');
            $this->redirect('admin_templates');
            return;
        }

        // Find the template in scan results
        $templates = TemplateService::scan();
        $target = null;
        foreach ($templates as $tpl) {
            if ($tpl['key'] === $templateKey) {
                $target = $tpl;
                break;
            }
        }

        if ($target === null) {
            $_SESSION['_flash_error'] = t('templates.not_found');
            $this->redirect('admin_templates');
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            $teamId = TeamService::getActiveTeamId();
            TemplateService::importTemplate($target, $userId, $teamId);

            $_SESSION['_flash_success'] = t('templates.import_success', ['title' => $target['title']]);

            Logger::info('Template imported', [
                'key'     => $templateKey,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Template import failed', [
                'key'   => $templateKey,
                'error' => $e->getMessage(),
            ]);
            $_SESSION['_flash_error'] = t('templates.import_failed');
        }

        $this->redirect('admin_templates');
    }

    /**
     * Import all templates for the current UI language (POST).
     */
    public function importAll(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_templates');
            return;
        }

        Security::csrfGuard();

        $language = trim($_POST['language'] ?? '');
        if ($language === '') {
            $language = I18nService::getCurrentLanguage();
        }

        // Validate language code
        if (!preg_match('/^[a-z]{2}$/', $language)) {
            $_SESSION['_flash_error'] = t('templates.invalid_language');
            $this->redirect('admin_templates');
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            $teamId = TeamService::getActiveTeamId();
            $result = TemplateService::importByLanguage($language, $userId, $teamId);

            if (!empty($result['errors'])) {
                $_SESSION['_flash_error'] = t('templates.import_partial', [
                    'imported' => $result['imported'],
                    'errors'   => count($result['errors']),
                ]);
            } else {
                $_SESSION['_flash_success'] = t('templates.import_all_success', [
                    'imported' => $result['imported'],
                    'skipped'  => $result['skipped'],
                ]);
            }

            Logger::info('Bulk template import', [
                'language' => $language,
                'imported' => $result['imported'],
                'skipped'  => $result['skipped'],
                'errors'   => count($result['errors']),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Bulk template import failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('templates.import_failed');
        }

        $this->redirect('admin_templates');
    }

    /**
     * Refresh templates from GitHub ZIP (POST).
     */
    public function refresh(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_templates');
            return;
        }

        Security::csrfGuard();

        $result = TemplateService::refreshFromGitHub();

        if ($result['success']) {
            $_SESSION['_flash_success'] = $result['message'];
        } else {
            $_SESSION['_flash_error'] = $result['message'];
        }

        $this->redirect('admin_templates');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
