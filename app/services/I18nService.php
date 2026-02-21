<?php
/**
 * I18nService - Internationalization for UI system texts (AP24).
 *
 * Handles language file loading, translation lookups with fallback,
 * placeholder replacement, and language switching.
 *
 * Language files:
 *   /app/lang/*.json   - shipped with the application (versioned)
 *   /storage/lang/*.json - operator overrides (update-safe)
 *
 * Files in /storage/lang override or supplement same-named files from /app/lang.
 */
class I18nService
{
    /** @var array<string, array<string, string>> Loaded language data, keyed by locale code */
    private static array $loaded = [];

    /** @var string|null Resolved current language code */
    private static ?string $currentLang = null;

    /** @var array|null Cached list of available languages */
    private static ?array $availableCache = null;

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Scan /app/lang and /storage/lang for available language files.
     *
     * Returns an array of entries:
     *   [
     *     'code'   => 'de',
     *     'source' => 'app' | 'storage' | 'both',
     *     'path'   => '/absolute/path/to/file.json' (effective file)
     *   ]
     */
    public static function listAvailableLanguages(): array
    {
        if (self::$availableCache !== null) {
            return self::$availableCache;
        }

        $appDir     = APP_DIR . '/lang';
        $storageDir = ROOT_DIR . '/storage/lang';
        $languages  = [];

        // Scan app/lang
        $appFiles = self::scanJsonDir($appDir);
        foreach ($appFiles as $code => $path) {
            $languages[$code] = [
                'code'   => $code,
                'source' => 'app',
                'path'   => $path,
            ];
        }

        // Scan storage/lang (overrides app)
        $storageFiles = self::scanJsonDir($storageDir);
        foreach ($storageFiles as $code => $path) {
            if (isset($languages[$code])) {
                $languages[$code]['source'] = 'both';
                $languages[$code]['path']   = $path; // storage takes precedence
            } else {
                $languages[$code] = [
                    'code'   => $code,
                    'source' => 'storage',
                    'path'   => $path,
                ];
            }
        }

        ksort($languages);
        self::$availableCache = array_values($languages);
        return self::$availableCache;
    }

    /**
     * Load a language file and return its key-value pairs.
     * Merges storage overrides on top of app defaults.
     * Cached per request.
     */
    public static function loadLanguage(string $code): array
    {
        if (isset(self::$loaded[$code])) {
            return self::$loaded[$code];
        }

        $code = self::sanitizeCode($code);
        $data = [];

        // Load from app/lang first
        $appFile = APP_DIR . '/lang/' . $code . '.json';
        if (is_file($appFile)) {
            $parsed = self::parseJsonFile($appFile);
            if ($parsed !== null) {
                $data = $parsed;
            }
        }

        // Merge/override from storage/lang
        $storageFile = ROOT_DIR . '/storage/lang/' . $code . '.json';
        if (is_file($storageFile)) {
            $parsed = self::parseJsonFile($storageFile);
            if ($parsed !== null) {
                $data = array_merge($data, $parsed);
            }
        }

        self::$loaded[$code] = $data;
        return $data;
    }

    /**
     * Translate a key with optional placeholder substitution.
     *
     * Fallback chain:
     *   1. Active language
     *   2. German (de) if different from active
     *   3. Return the raw key
     *
     * Placeholders use {name} syntax.
     *
     * @param string      $key    Translation key (e.g. 'actions.save')
     * @param array       $params Placeholder replacements ['field' => 'Name']
     * @param string|null $lang   Override language (null = current)
     */
    public static function t(string $key, array $params = [], ?string $lang = null): string
    {
        $lang = $lang ?? self::getCurrentLanguage();
        $translations = self::loadLanguage($lang);

        // Try active language
        if (isset($translations[$key])) {
            return self::replacePlaceholders($translations[$key], $params);
        }

        // Fallback to German
        if ($lang !== 'de') {
            $deFallback = self::loadLanguage('de');
            if (isset($deFallback[$key])) {
                return self::replacePlaceholders($deFallback[$key], $params);
            }
        }

        // Last resort: return the key itself
        return self::replacePlaceholders($key, $params);
    }

    /**
     * Determine the current UI language.
     *
     * Priority:
     *   1. Logged-in user's language preference (from DB)
     *   2. Session language (for login screen / anonymous)
     *   3. Cookie language
     *   4. System default from system_settings
     *   5. Hardcoded fallback: 'de'
     */
    public static function getCurrentLanguage(): string
    {
        if (self::$currentLang !== null) {
            return self::$currentLang;
        }

        $available = self::getAvailableCodes();

        // 1. User DB preference
        if (!empty($_SESSION['user_id'])) {
            $userLang = $_SESSION['user_language'] ?? null;
            if ($userLang !== null && in_array($userLang, $available, true)) {
                self::$currentLang = $userLang;
                return self::$currentLang;
            }
        }

        // 2. Session
        if (!empty($_SESSION['_ui_language'])) {
            $sessLang = $_SESSION['_ui_language'];
            if (in_array($sessLang, $available, true)) {
                self::$currentLang = $sessLang;
                return self::$currentLang;
            }
        }

        // 3. Cookie
        if (!empty($_COOKIE['wp_language'])) {
            $cookieLang = $_COOKIE['wp_language'];
            if (in_array($cookieLang, $available, true)) {
                self::$currentLang = $cookieLang;
                return self::$currentLang;
            }
        }

        // 4. System default
        $default = self::getSystemDefault();
        if (in_array($default, $available, true)) {
            self::$currentLang = $default;
            return self::$currentLang;
        }

        // 5. Hardcoded fallback
        self::$currentLang = 'de';
        return self::$currentLang;
    }

    /**
     * Set the current language.
     *
     * For logged-in users: saves to DB and session.
     * For anonymous/login: saves to session and cookie.
     *
     * Validates against available languages.
     */
    public static function setCurrentLanguage(string $code): void
    {
        $code = self::sanitizeCode($code);
        $available = self::getAvailableCodes();

        if (!in_array($code, $available, true)) {
            return; // invalid language, ignore
        }

        // Always set session and cookie
        $_SESSION['_ui_language'] = $code;
        setcookie('wp_language', $code, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);

        // If logged in, persist to DB
        if (!empty($_SESSION['user_id'])) {
            try {
                DB::query(
                    'UPDATE users SET language = ? WHERE id = ?',
                    [$code, (int) $_SESSION['user_id']]
                );
                $_SESSION['user_language'] = $code;
            } catch (Throwable $e) {
                Logger::error('Failed to save user language', [
                    'user_id' => $_SESSION['user_id'],
                    'lang'    => $code,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // Reset cached current language
        self::$currentLang = $code;
    }

    /**
     * Get the system default language from system_settings.
     */
    public static function getSystemDefault(): string
    {
        try {
            $val = SystemSettingsService::value('default_language', 'de');
            return is_string($val) && $val !== '' ? $val : 'de';
        } catch (Throwable $e) {
            return 'de';
        }
    }

    /**
     * Get translation completeness stats for a language relative to de.json.
     *
     * Returns:
     *   [
     *     'total'        => int,   // total keys in de.json
     *     'translated'   => int,   // keys present in target
     *     'missing'      => int,   // keys missing in target
     *     'percent'      => float, // 0-100
     *     'missing_keys' => string[] // list of missing key names
     *   ]
     */
    public static function getCompleteness(string $code): array
    {
        $reference = self::loadLanguage('de');
        $total     = count($reference);

        if ($code === 'de') {
            return [
                'total'        => $total,
                'translated'   => $total,
                'missing'      => 0,
                'percent'      => 100.0,
                'missing_keys' => [],
            ];
        }

        $target      = self::loadLanguage($code);
        $missingKeys = [];

        foreach ($reference as $key => $value) {
            if (!isset($target[$key])) {
                $missingKeys[] = $key;
            }
        }

        $translated = $total - count($missingKeys);
        $percent    = $total > 0 ? round(($translated / $total) * 100, 1) : 0;

        return [
            'total'        => $total,
            'translated'   => $translated,
            'missing'      => count($missingKeys),
            'percent'      => $percent,
            'missing_keys' => $missingKeys,
        ];
    }

    /**
     * Get human-readable language name for a locale code.
     */
    public static function languageName(string $code): string
    {
        $names = [
            'de' => 'Deutsch',
            'en' => 'English',
            'fr' => 'Français',
            'it' => 'Italiano',
            'es' => 'Español',
            'pt' => 'Português',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'cs' => 'Čeština',
            'ja' => '日本語',
            'zh' => '中文',
            'ko' => '한국어',
            'ru' => 'Русский',
            'ar' => 'العربية',
            'tr' => 'Türkçe',
        ];

        return $names[$code] ?? strtoupper($code);
    }

    /**
     * Clear all caches (useful after adding new language files).
     */
    public static function clearCache(): void
    {
        self::$loaded = [];
        self::$currentLang = null;
        self::$availableCache = null;
    }

    // ── Internal helpers ────────────────────────────────────────────

    /**
     * Scan a directory for *.json files and return [code => path].
     */
    private static function scanJsonDir(string $dir): array
    {
        $result = [];
        if (!is_dir($dir)) {
            return $result;
        }

        $files = glob($dir . '/*.json');
        if ($files === false) {
            return $result;
        }

        foreach ($files as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $code)) {
                $result[$code] = $file;
            }
        }

        return $result;
    }

    /**
     * Parse a JSON file and return its content as associative array.
     */
    private static function parseJsonFile(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            Logger::error('Failed to read language file', ['path' => $path]);
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            Logger::error('Invalid JSON in language file', ['path' => $path]);
            return null;
        }

        return $data;
    }

    /**
     * Replace {placeholder} tokens in a string.
     */
    private static function replacePlaceholders(string $text, array $params): string
    {
        if (empty($params)) {
            return $text;
        }

        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Sanitize a locale code to prevent directory traversal.
     */
    private static function sanitizeCode(string $code): string
    {
        // Only allow lowercase letters, underscore, and uppercase for region
        return preg_replace('/[^a-zA-Z_]/', '', substr($code, 0, 10));
    }

    /**
     * Get a flat array of available language codes.
     */
    private static function getAvailableCodes(): array
    {
        $languages = self::listAvailableLanguages();
        return array_column($languages, 'code');
    }
}
