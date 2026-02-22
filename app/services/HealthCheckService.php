<?php
/**
 * HealthCheckService - System health diagnostics (AP28).
 *
 * All checks are read-only, fast, and local.
 * No external requests, no heavy queries.
 * Each section returns: ['status' => 'ok'|'warning'|'error', 'items' => [...]]
 * Each item: ['label' => i18n_key, 'label_raw' => bool, 'value' => string, 'status' => ..., 'hint' => i18n_key|'']
 */
class HealthCheckService
{
    /** Minimum required PHP version */
    private const MIN_PHP = '8.0.0';

    /** Recommended PHP version */
    private const REC_PHP = '8.1.0';

    /**
     * Run all health checks and return a keyed array of sections.
     */
    public static function runAll(): array
    {
        return [
            'system'     => self::checkSystem(),
            'filesystem' => self::checkFilesystem(),
            'database'   => self::checkDatabase(),
            'email'      => self::checkEmail(),
            'webhooks'   => self::checkWebhooks(),
            'config'     => self::checkConfig(),
        ];
    }

    // ── 1. System & Umgebung ─────────────────────────────────────────

    public static function checkSystem(): array
    {
        $items = [];

        // PHP version
        $phpVersion = PHP_VERSION;
        $phpMinOk   = version_compare($phpVersion, self::MIN_PHP, '>=');
        $phpRecOk   = version_compare($phpVersion, self::REC_PHP, '>=');
        $items[] = [
            'label'  => 'health.system.php_version',
            'value'  => $phpVersion,
            'status' => $phpMinOk ? ($phpRecOk ? 'ok' : 'warning') : 'error',
            'hint'   => $phpMinOk ? ($phpRecOk ? '' : 'health.system.php_rec_hint') : 'health.system.php_min_hint',
        ];

        // MySQL / MariaDB version
        try {
            $row        = DB::fetch('SELECT VERSION() AS v');
            $dbVersion  = $row['v'] ?? 'unknown';
            $items[] = [
                'label'  => 'health.system.db_version',
                'value'  => $dbVersion,
                'status' => 'ok',
                'hint'   => '',
            ];
        } catch (Throwable $e) {
            $items[] = [
                'label'  => 'health.system.db_version',
                'value'  => '-',
                'status' => 'error',
                'hint'   => 'health.system.db_error',
            ];
        }

        // Server timezone
        $tz = ini_get('date.timezone') ?: date_default_timezone_get();
        $items[] = [
            'label'  => 'health.system.timezone',
            'value'  => $tz ?: 'UTC',
            'status' => 'ok',
            'hint'   => '',
        ];

        // App version
        $versionFile = CONFIG_DIR . '/version.php';
        if (file_exists($versionFile)) {
            $vInfo      = require $versionFile;
            $appVersion = $vInfo['version'] ?? 'unknown';
        } else {
            $appVersion = 'unknown';
        }
        $items[] = [
            'label'  => 'health.system.app_version',
            'value'  => $appVersion,
            'status' => 'ok',
            'hint'   => '',
        ];

        // Server time (proof of life)
        $items[] = [
            'label'  => 'health.system.server_time',
            'value'  => date('Y-m-d H:i:s'),
            'status' => 'ok',
            'hint'   => '',
        ];

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── 2. Dateisystem & Storage ─────────────────────────────────────

    public static function checkFilesystem(): array
    {
        $items = [];

        $dirs = [
            '/storage'         => ROOT_DIR . '/storage',
            '/storage/uploads' => ROOT_DIR . '/storage/uploads',
            '/storage/lang'    => ROOT_DIR . '/storage/lang',
        ];

        foreach ($dirs as $displayPath => $absPath) {
            $exists   = is_dir($absPath);
            $writable = $exists && is_writable($absPath);

            if (!$exists) {
                $status = 'error';
                $hint   = 'health.fs.not_exists';
            } elseif (!$writable) {
                $status = 'error';
                $hint   = 'health.fs.not_writable';
            } else {
                $status = 'ok';
                $hint   = '';
            }

            $items[] = [
                'label'     => $displayPath,
                'label_raw' => true,
                'value'     => $writable ? t('health.fs.writable') : t('health.fs.not_writable_short'),
                'status'    => $status,
                'hint'      => $hint,
            ];
        }

        // Optional: disk free space (may not be reliable on all hosts)
        $diskFree = @disk_free_space(ROOT_DIR . '/storage');
        if ($diskFree !== false) {
            $diskFreeGb = round($diskFree / (1024 * 1024 * 1024), 2);
            if ($diskFreeGb < 0.1) {
                $diskStatus = 'error';
                $diskHint   = 'health.fs.disk_low';
            } elseif ($diskFreeGb < 0.5) {
                $diskStatus = 'warning';
                $diskHint   = 'health.fs.disk_low';
            } else {
                $diskStatus = 'ok';
                $diskHint   = '';
            }
            $items[] = [
                'label'  => 'health.fs.disk_free',
                'value'  => $diskFreeGb . ' GB',
                'status' => $diskStatus,
                'hint'   => $diskHint,
            ];
        }

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── 3. Datenbank & Migrationen ───────────────────────────────────

    public static function checkDatabase(): array
    {
        $items = [];

        // Connection check
        try {
            DB::fetch('SELECT 1');
            $items[] = [
                'label'  => 'health.db.connection',
                'value'  => t('health.status.ok'),
                'status' => 'ok',
                'hint'   => '',
            ];
        } catch (Throwable $e) {
            $items[] = [
                'label'  => 'health.db.connection',
                'value'  => t('health.status.error'),
                'status' => 'error',
                'hint'   => 'health.db.connection_failed',
            ];
            // Cannot check migrations without a connection
            return ['status' => 'error', 'items' => $items];
        }

        // Migration status
        try {
            $row            = DB::fetch("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
            $currentVersion = $row ? (int) $row['meta_value'] : 0;

            $migrationDir   = APP_DIR . '/migrations';
            $allFiles       = glob($migrationDir . '/*.sql') ?: [];
            $totalCount     = 0;
            $pendingCount   = 0;

            foreach ($allFiles as $file) {
                $basename = basename($file);
                if (preg_match('/^(\d+)_/', $basename, $m)) {
                    $totalCount++;
                    if ((int) $m[1] > $currentVersion) {
                        $pendingCount++;
                    }
                }
            }

            $appliedCount = $totalCount - $pendingCount;

            $items[] = [
                'label'  => 'health.db.migrations_total',
                'value'  => $totalCount,
                'status' => 'ok',
                'hint'   => '',
            ];
            $items[] = [
                'label'  => 'health.db.migrations_applied',
                'value'  => $appliedCount,
                'status' => 'ok',
                'hint'   => '',
            ];
            $items[] = [
                'label'  => 'health.db.migrations_pending',
                'value'  => $pendingCount,
                'status' => $pendingCount > 0 ? 'warning' : 'ok',
                'hint'   => $pendingCount > 0 ? 'health.db.migrations_pending_hint' : '',
            ];
        } catch (Throwable $e) {
            $items[] = [
                'label'  => 'health.db.migrations_pending',
                'value'  => '?',
                'status' => 'warning',
                'hint'   => 'health.db.meta_error',
            ];
        }

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── 4. E-Mail Versand ────────────────────────────────────────────

    public static function checkEmail(): array
    {
        $items  = [];
        $config = $GLOBALS['config'];

        // Mail mode configured
        $mailMode = trim($config['MAIL_MODE'] ?? '');
        $items[] = [
            'label'  => 'health.email.mail_mode',
            'value'  => $mailMode !== '' ? $mailMode : '-',
            'status' => $mailMode !== '' ? 'ok' : 'warning',
            'hint'   => $mailMode === '' ? 'health.email.not_configured' : '',
        ];

        // Queue counts
        try {
            $pendingCount = EmailOutbox::countPending();
            $failedCount  = EmailOutbox::countFailed();

            $items[] = [
                'label'  => 'health.email.queue_pending',
                'value'  => $pendingCount,
                'status' => $pendingCount > 20 ? 'warning' : 'ok',
                'hint'   => $pendingCount > 20 ? 'health.email.queue_growing' : '',
            ];
            $items[] = [
                'label'  => 'health.email.queue_failed',
                'value'  => $failedCount,
                'status' => $failedCount > 5 ? 'error' : ($failedCount > 0 ? 'warning' : 'ok'),
                'hint'   => $failedCount > 0 ? 'health.email.failed_hint' : '',
            ];
        } catch (Throwable $e) {
            $pendingCount = 0;
            $failedCount  = 0;
        }

        // Last successful send
        try {
            $row      = DB::fetch("SELECT MAX(sent_at) AS last_sent FROM email_outbox WHERE status = 'sent'");
            $lastSent = $row['last_sent'] ?? null;

            if ($lastSent !== null) {
                $ageSeconds = time() - strtotime($lastSent);
                $oldAndGrowing = $pendingCount > 0 && $ageSeconds > 48 * 3600;
                $items[] = [
                    'label'  => 'health.email.last_sent',
                    'value'  => $lastSent,
                    'status' => $oldAndGrowing ? 'warning' : 'ok',
                    'hint'   => $oldAndGrowing ? 'health.email.last_sent_old' : '',
                ];
            } else {
                $items[] = [
                    'label'  => 'health.email.last_sent',
                    'value'  => t('labels.never'),
                    'status' => 'ok',
                    'hint'   => '',
                ];
            }
        } catch (Throwable $e) {
            // email_outbox table may not exist on older installations
            $items[] = [
                'label'  => 'health.email.last_sent',
                'value'  => '-',
                'status' => 'ok',
                'hint'   => '',
            ];
        }

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── 5. Webhooks / Outbox ─────────────────────────────────────────

    public static function checkWebhooks(): array
    {
        $items = [];

        try {
            $counts  = WebhookDeliveryService::countByStatus();
            $pending = $counts['pending'] ?? 0;
            $failed  = $counts['failed'] ?? 0;
            $dead    = $counts['dead'] ?? 0;

            $items[] = [
                'label'  => 'health.webhooks.pending',
                'value'  => $pending,
                'status' => $pending > 10 ? 'warning' : 'ok',
                'hint'   => $pending > 10 ? 'health.webhooks.pending_hint' : '',
            ];
            $items[] = [
                'label'  => 'health.webhooks.failed',
                'value'  => $failed,
                'status' => $failed > 0 ? 'warning' : 'ok',
                'hint'   => $failed > 0 ? 'health.webhooks.failed_hint' : '',
            ];
            $items[] = [
                'label'  => 'health.webhooks.dead',
                'value'  => $dead,
                'status' => $dead > 0 ? 'error' : 'ok',
                'hint'   => $dead > 0 ? 'health.webhooks.dead_hint' : '',
            ];

            // Last successful delivery
            $row      = DB::fetch("SELECT MAX(sent_at) AS last_sent FROM webhook_outbox WHERE status = 'sent'");
            $lastSent = $row['last_sent'] ?? null;
            $items[] = [
                'label'  => 'health.webhooks.last_sent',
                'value'  => $lastSent ?? t('labels.never'),
                'status' => 'ok',
                'hint'   => '',
            ];
        } catch (Throwable $e) {
            // webhook_outbox may not exist on older installations (pre-AP19)
            $items[] = [
                'label'  => 'health.webhooks.not_available',
                'value'  => '-',
                'status' => 'ok',
                'hint'   => 'health.webhooks.table_missing',
            ];
        }

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── 6. Konfiguration & Basis-Checks ─────────────────────────────

    public static function checkConfig(): array
    {
        $items    = [];
        $config   = $GLOBALS['config'];
        $settings = SystemSettingsService::get();

        // BASE_URL
        $baseUrl = trim($config['BASE_URL'] ?? '');
        $items[] = [
            'label'  => 'health.config.base_url',
            'value'  => $baseUrl !== '' ? $baseUrl : '-',
            'status' => $baseUrl !== '' ? 'ok' : 'error',
            'hint'   => $baseUrl === '' ? 'health.config.base_url_missing' : '',
        ];

        // default_language
        $defaultLang = trim($settings['default_language'] ?? '');
        $items[] = [
            'label'  => 'health.config.default_lang',
            'value'  => $defaultLang !== '' ? $defaultLang : '-',
            'status' => $defaultLang !== '' ? 'ok' : 'warning',
            'hint'   => $defaultLang === '' ? 'health.config.default_lang_missing' : '',
        ];

        // Languages available
        $langFiles = glob(APP_DIR . '/lang/*.json') ?: [];
        $langCount = count($langFiles);
        $items[] = [
            'label'  => 'health.config.languages',
            'value'  => $langCount,
            'status' => $langCount > 0 ? 'ok' : 'error',
            'hint'   => $langCount === 0 ? 'health.config.no_languages' : '',
        ];

        // At least one active admin
        try {
            $row        = DB::fetch("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin' AND is_active = 1");
            $adminCount = $row ? (int) $row['cnt'] : 0;
            $items[] = [
                'label'  => 'health.config.admin_count',
                'value'  => $adminCount,
                'status' => $adminCount > 0 ? 'ok' : 'error',
                'hint'   => $adminCount === 0 ? 'health.config.no_admin' : '',
            ];
        } catch (Throwable $e) {
            $items[] = [
                'label'  => 'health.config.admin_count',
                'value'  => '?',
                'status' => 'warning',
                'hint'   => '',
            ];
        }

        // Mail sender (optional but recommended)
        $mailFrom = trim($config['MAIL_FROM'] ?? '');
        $items[] = [
            'label'  => 'health.config.mail_from',
            'value'  => $mailFrom !== '' ? $mailFrom : '-',
            'status' => $mailFrom !== '' ? 'ok' : 'warning',
            'hint'   => $mailFrom === '' ? 'health.config.mail_from_missing' : '',
        ];

        return ['status' => self::worstStatus(array_column($items, 'status')), 'items' => $items];
    }

    // ── Helper ───────────────────────────────────────────────────────

    /**
     * Return the worst status from an array of status strings.
     */
    public static function worstStatus(array $statuses): string
    {
        if (in_array('error', $statuses, true)) {
            return 'error';
        }
        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }
        return 'ok';
    }
}
