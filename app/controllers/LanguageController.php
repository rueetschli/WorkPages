<?php
/**
 * LanguageController - Handles UI language switching (AP24).
 *
 * POST /?r=language_switch  – switch language (works on login and authenticated pages)
 */
class LanguageController
{
    /**
     * Switch language.
     * Accepts POST with CSRF. Saves to DB (if logged in) or session/cookie.
     * Redirects back to the referring page.
     */
    public static function switchLang(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        Security::csrfGuard();

        $code = trim($_POST['language'] ?? '');
        if ($code === '') {
            self::redirectBack();
            return;
        }

        I18nService::setCurrentLanguage($code);

        self::redirectBack();
    }

    /**
     * Redirect back to the referring page, or to home.
     */
    private static function redirectBack(): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        // Only redirect to same-origin URLs
        if ($referer !== '' && str_starts_with($referer, $baseUrl)) {
            header('Location: ' . $referer);
        } else {
            header('Location: ' . $baseUrl . '/?r=home');
        }
        exit;
    }
}
