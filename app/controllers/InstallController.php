<?php
/**
 * InstallController - Web-based installer wizard (AP10).
 *
 * Steps:
 *   1. Environment check (PHP version, extensions, write permissions)
 *   2. Database configuration (test connection, write config.php)
 *   3. Schema creation (run 001_init.sql)
 *   4. Admin user creation
 *   5. Done (write lock file, link to login)
 */
class InstallController
{
    private string $lockFile;
    private string $configFile;
    private string $configExample;
    private string $migrationsDir;

    public function __construct()
    {
        $this->lockFile      = ROOT_DIR . '/storage/install.lock';
        $this->configFile    = CONFIG_DIR . '/config.php';
        $this->configExample = CONFIG_DIR . '/config.php.example';
        $this->migrationsDir = APP_DIR . '/migrations';
    }

    /**
     * Main installer entry point. Determines current step and delegates.
     */
    public function index(): void
    {
        // Check if installation is locked
        if ($this->isLocked()) {
            $this->renderInstallView('locked');
            return;
        }

        $step = $_GET['step'] ?? $this->detectStep();

        switch ($step) {
            case 'environment':
                $this->stepEnvironment();
                break;
            case 'database':
                $this->stepDatabase();
                break;
            case 'schema':
                $this->stepSchema();
                break;
            case 'admin':
                $this->stepAdmin();
                break;
            case 'done':
                $this->stepDone();
                break;
            default:
                $this->stepEnvironment();
                break;
        }
    }

    /**
     * Detect which step to show based on current state.
     */
    private function detectStep(): string
    {
        if (!file_exists($this->configFile)) {
            return 'environment';
        }

        // Config exists, try connecting
        try {
            $config = require $this->configFile;
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_NAME'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Check if schema exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'app_meta'");
            if ($stmt->rowCount() === 0) {
                return 'schema';
            }

            // Check if admin user exists
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ((int) ($row['cnt'] ?? 0) === 0) {
                return 'admin';
            }

            return 'done';
        } catch (Throwable $e) {
            return 'database';
        }
    }

    // ── Step 1: Environment Check ────────────────────────────────────

    private function stepEnvironment(): void
    {
        $checks = $this->runEnvironmentChecks();
        $allOk = true;
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $allOk = false;
                break;
            }
        }

        $this->renderInstallView('environment', [
            'checks' => $checks,
            'allOk'  => $allOk,
        ]);
    }

    private function runEnvironmentChecks(): array
    {
        $checks = [];

        // PHP version
        $checks[] = [
            'label' => 'PHP Version >= 8.0',
            'value' => PHP_VERSION,
            'ok'    => version_compare(PHP_VERSION, '8.0.0', '>='),
        ];

        // PDO extension
        $checks[] = [
            'label' => 'PHP Extension: PDO',
            'value' => extension_loaded('pdo') ? 'Geladen' : 'Fehlt',
            'ok'    => extension_loaded('pdo'),
        ];

        // pdo_mysql extension
        $checks[] = [
            'label' => 'PHP Extension: pdo_mysql',
            'value' => extension_loaded('pdo_mysql') ? 'Geladen' : 'Fehlt',
            'ok'    => extension_loaded('pdo_mysql'),
        ];

        // mbstring extension
        $checks[] = [
            'label' => 'PHP Extension: mbstring',
            'value' => extension_loaded('mbstring') ? 'Geladen' : 'Fehlt',
            'ok'    => extension_loaded('mbstring'),
        ];

        // json extension
        $checks[] = [
            'label' => 'PHP Extension: json',
            'value' => extension_loaded('json') ? 'Geladen' : 'Fehlt',
            'ok'    => extension_loaded('json'),
        ];

        // Write permissions: storage/logs
        $logsDir = ROOT_DIR . '/storage/logs';
        $checks[] = [
            'label' => 'Schreibrechte: /storage/logs',
            'value' => is_writable($logsDir) ? 'Beschreibbar' : 'Nicht beschreibbar',
            'ok'    => is_writable($logsDir),
        ];

        // Write permissions: storage/uploads
        $uploadsDir = ROOT_DIR . '/storage/uploads';
        $checks[] = [
            'label' => 'Schreibrechte: /storage/uploads',
            'value' => is_writable($uploadsDir) ? 'Beschreibbar' : 'Nicht beschreibbar',
            'ok'    => is_writable($uploadsDir),
        ];

        // Write permissions: config/
        $configDir = CONFIG_DIR;
        $checks[] = [
            'label' => 'Schreibrechte: /config',
            'value' => is_writable($configDir) ? 'Beschreibbar' : 'Nicht beschreibbar',
            'ok'    => is_writable($configDir),
        ];

        // Write permissions: storage/ (for lock file)
        $storageDir = ROOT_DIR . '/storage';
        $checks[] = [
            'label' => 'Schreibrechte: /storage',
            'value' => is_writable($storageDir) ? 'Beschreibbar' : 'Nicht beschreibbar',
            'ok'    => is_writable($storageDir),
        ];

        return $checks;
    }

    // ── Step 2: Database Configuration ───────────────────────────────

    private function stepDatabase(): void
    {
        $error = null;
        $success = null;
        $formData = [
            'DB_HOST'  => 'localhost',
            'DB_NAME'  => '',
            'DB_USER'  => '',
            'DB_PASS'  => '',
            'BASE_URL' => $this->guessBaseUrl(),
        ];

        // Load existing config values if file exists
        if (file_exists($this->configFile)) {
            $existing = require $this->configFile;
            $formData['DB_HOST']  = $existing['DB_HOST'] ?? $formData['DB_HOST'];
            $formData['DB_NAME']  = $existing['DB_NAME'] ?? $formData['DB_NAME'];
            $formData['DB_USER']  = $existing['DB_USER'] ?? $formData['DB_USER'];
            $formData['DB_PASS']  = $existing['DB_PASS'] ?? $formData['DB_PASS'];
            $formData['BASE_URL'] = $existing['BASE_URL'] ?? $formData['BASE_URL'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['DB_HOST']  = trim($_POST['DB_HOST'] ?? '');
            $formData['DB_NAME']  = trim($_POST['DB_NAME'] ?? '');
            $formData['DB_USER']  = trim($_POST['DB_USER'] ?? '');
            $formData['DB_PASS']  = $_POST['DB_PASS'] ?? '';
            $formData['BASE_URL'] = rtrim(trim($_POST['BASE_URL'] ?? ''), '/');

            if ($formData['DB_HOST'] === '' || $formData['DB_NAME'] === '' || $formData['DB_USER'] === '') {
                $error = 'Bitte alle Pflichtfelder ausfuellen (Host, Datenbankname, Benutzer).';
            } elseif ($formData['BASE_URL'] === '') {
                $error = 'Bitte die Base URL angeben.';
            } else {
                // Test connection
                $testResult = $this->testDbConnection(
                    $formData['DB_HOST'],
                    $formData['DB_NAME'],
                    $formData['DB_USER'],
                    $formData['DB_PASS']
                );

                if ($testResult !== true) {
                    $error = 'Datenbankverbindung fehlgeschlagen: ' . $testResult;
                } else {
                    // Write config file
                    $writeResult = $this->writeConfigFile($formData);
                    if ($writeResult !== true) {
                        $error = 'Konfigurationsdatei konnte nicht geschrieben werden: ' . $writeResult;
                    } else {
                        $success = 'Datenbankverbindung erfolgreich. Konfiguration gespeichert.';
                    }
                }
            }
        }

        $this->renderInstallView('database', [
            'error'    => $error,
            'success'  => $success,
            'formData' => $formData,
        ]);
    }

    private function testDbConnection(string $host, string $dbName, string $user, string $pass): string|true
    {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
            new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function writeConfigFile(array $formData): string|true
    {
        try {
            $appKey = bin2hex(random_bytes(32));

            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * WorkPages - Konfiguration\n";
            $content .= " * Erstellt vom Installer am " . date('Y-m-d H:i:s') . "\n";
            $content .= " */\n\n";
            $content .= "return [\n";
            $content .= "    // Datenbank\n";
            $content .= "    'DB_HOST'   => " . var_export($formData['DB_HOST'], true) . ",\n";
            $content .= "    'DB_NAME'   => " . var_export($formData['DB_NAME'], true) . ",\n";
            $content .= "    'DB_USER'   => " . var_export($formData['DB_USER'], true) . ",\n";
            $content .= "    'DB_PASS'   => " . var_export($formData['DB_PASS'], true) . ",\n";
            $content .= "    'DB_CHARSET'=> 'utf8mb4',\n";
            $content .= "\n";
            $content .= "    // Applikation\n";
            $content .= "    'BASE_URL'  => " . var_export($formData['BASE_URL'], true) . ",\n";
            $content .= "    'APP_KEY'   => " . var_export($appKey, true) . ",\n";
            $content .= "    'APP_NAME'  => 'WorkPages',\n";
            $content .= "    'APP_ENV'   => 'production',\n";
            $content .= "    'DEBUG'     => false,\n";
            $content .= "\n";
            $content .= "    // Suche: 'like' (immer), 'fulltext' (FULLTEXT Indexes noetig), 'auto'\n";
            $content .= "    'SEARCH_MODE' => 'like',\n";
            $content .= "\n";
            $content .= "    // Activity Log: 'json' oder 'text'\n";
            $content .= "    'ACTIVITY_META_MODE' => 'text',\n";
            $content .= "\n";
            $content .= "    // Installer\n";
            $content .= "    'INSTALL_UNLOCK' => false,\n";
            $content .= "\n";
            $content .= "    // Pfade (normalerweise keine Aenderung noetig)\n";
            $content .= "    'LOG_FILE'  => __DIR__ . '/../storage/logs/app.log',\n";
            $content .= "    'UPLOAD_DIR'=> __DIR__ . '/../storage/uploads',\n";
            $content .= "];\n";

            $result = file_put_contents($this->configFile, $content);
            if ($result === false) {
                return 'Datei konnte nicht geschrieben werden.';
            }

            // Set restrictive permissions
            @chmod($this->configFile, 0640);

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function guessBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
        return $protocol . '://' . $host . $scriptDir;
    }

    // ── Step 3: Schema Creation ──────────────────────────────────────

    private function stepSchema(): void
    {
        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $sqlFile = $this->migrationsDir . '/001_init.sql';
            if (!file_exists($sqlFile)) {
                $error = 'Migrationsdatei 001_init.sql nicht gefunden.';
            } else {
                $result = $this->executeSqlFile($sqlFile);
                if ($result !== true) {
                    $error = 'Schema konnte nicht erstellt werden. Details im Log.';
                    Logger::error('Schema creation failed', ['error' => $result]);
                } else {
                    $success = 'Datenbank-Schema erfolgreich erstellt.';
                }
            }
        }

        $this->renderInstallView('schema', [
            'error'   => $error,
            'success' => $success,
        ]);
    }

    private function executeSqlFile(string $filePath): string|true
    {
        try {
            $config = require $this->configFile;
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_NAME'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $sql = file_get_contents($filePath);

            // Split by semicolons, but respect multi-line statements
            $statements = $this->splitSql($sql);

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                $pdo->exec($stmt);
            }

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Split SQL string into individual statements.
     */
    private function splitSql(string $sql): array
    {
        // Remove comment-only lines
        $lines = explode("\n", $sql);
        $cleaned = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $cleaned[] = $line;
        }

        $sql = implode("\n", $cleaned);
        $statements = explode(';', $sql);

        return array_filter(array_map('trim', $statements), fn($s) => $s !== '');
    }

    // ── Step 4: Admin User Creation ──────────────────────────────────

    private function stepAdmin(): void
    {
        $error = null;
        $success = null;
        $formData = ['name' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['name']  = trim($_POST['name'] ?? '');
            $formData['email'] = trim($_POST['email'] ?? '');
            $password          = $_POST['password'] ?? '';
            $passwordConfirm   = $_POST['password_confirm'] ?? '';

            if ($formData['name'] === '') {
                $error = 'Name ist erforderlich.';
            } elseif ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
            } elseif (strlen($password) < 10) {
                $error = 'Passwort muss mindestens 10 Zeichen lang sein.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Passwoerter stimmen nicht ueberein.';
            } else {
                $result = $this->createAdminUser($formData['name'], $formData['email'], $password);
                if ($result !== true) {
                    $error = 'Admin-Benutzer konnte nicht erstellt werden. Details im Log.';
                    Logger::error('Admin creation failed during install', ['error' => $result]);
                } else {
                    $success = 'Admin-Benutzer erfolgreich erstellt.';
                }
            }
        }

        $this->renderInstallView('admin', [
            'error'    => $error,
            'success'  => $success,
            'formData' => $formData,
        ]);
    }

    private function createAdminUser(string $name, string $email, string $password): string|true
    {
        try {
            $config = require $this->configFile;
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_NAME'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (email, name, password_hash, role, is_active, created_at)
                 VALUES (?, ?, ?, ?, 1, NOW())'
            );
            $stmt->execute([$email, $name, $hash, 'admin']);

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    // ── Step 5: Done ─────────────────────────────────────────────────

    private function stepDone(): void
    {
        // Write lock file
        $this->writeLockFile();

        $config = file_exists($this->configFile) ? require $this->configFile : [];
        $baseUrl = rtrim($config['BASE_URL'] ?? '', '/');

        $this->renderInstallView('done', [
            'baseUrl' => $baseUrl,
        ]);
    }

    private function writeLockFile(): void
    {
        $content = 'installed=' . date('Y-m-d H:i:s') . "\n";
        @file_put_contents($this->lockFile, $content);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function isLocked(): bool
    {
        // If lock file exists and INSTALL_UNLOCK is not true
        if (!file_exists($this->lockFile)) {
            return false;
        }

        // Check INSTALL_UNLOCK in config
        if (file_exists($this->configFile)) {
            $config = require $this->configFile;
            if (!empty($config['INSTALL_UNLOCK'])) {
                return false;
            }
        }

        // Also check if the installation truly exists (config + app_meta)
        return $this->isInstalled();
    }

    private function isInstalled(): bool
    {
        if (!file_exists($this->configFile)) {
            return false;
        }

        try {
            $config = require $this->configFile;
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_NAME'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row !== false);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function renderInstallView(string $step, array $data = []): void
    {
        extract($data);
        require APP_DIR . '/views/install/layout.php';
    }
}
