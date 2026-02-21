<?php
/**
 * AdminLanguagesController - Language overview for administrators (AP24).
 *
 * Route: /?r=admin_languages
 * Only accessible by global admin.
 */
class AdminLanguagesController
{
    /**
     * Show available languages with translation completeness stats.
     */
    public static function index(): void
    {
        Authz::requireGlobalAdmin();

        $languages = I18nService::listAvailableLanguages();

        // Build stats per language
        $langStats = [];
        foreach ($languages as $lang) {
            $stats = I18nService::getCompleteness($lang['code']);
            $langStats[] = array_merge($lang, [
                'name'         => I18nService::languageName($lang['code']),
                'total'        => $stats['total'],
                'translated'   => $stats['translated'],
                'missing'      => $stats['missing'],
                'percent'      => $stats['percent'],
                'missing_keys' => $stats['missing_keys'],
            ]);
        }

        // Handle default language update (POST)
        $error   = null;
        $success = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $action = $_POST['action'] ?? '';
            if ($action === 'set_default_language') {
                $newDefault = trim($_POST['default_language'] ?? '');
                $available  = array_column($languages, 'code');

                if ($newDefault !== '' && in_array($newDefault, $available, true)) {
                    try {
                        SystemSettingsService::update(['default_language' => $newDefault]);
                        $success = t('messages.settings_saved');
                    } catch (Throwable $e) {
                        Logger::error('Failed to update default language', ['error' => $e->getMessage()]);
                        $error = $e->getMessage();
                    }
                } else {
                    $error = t('errors.unknown_action');
                }
            }
        }

        $systemDefault = I18nService::getSystemDefault();

        require APP_DIR . '/views/admin/languages.php';
    }
}
