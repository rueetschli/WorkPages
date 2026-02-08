<?php
/**
 * AdminController - User management, migrations, system info (AP9 + AP10).
 */
class AdminController
{
    /**
     * List all users.
     */
    public function users(): void
    {
        Authz::require(Authz::ADMIN_USERS_MANAGE);

        $users = User::all();

        $pageTitle   = 'Benutzerverwaltung';
        $contentView = APP_DIR . '/views/admin/users/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show create user form (GET) or process creation (POST).
     */
    public function userCreate(): void
    {
        Authz::require(Authz::ADMIN_USERS_MANAGE);

        $error = null;
        $formData = ['email' => '', 'name' => '', 'role' => 'member', 'is_active' => 1];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['email']     = trim($_POST['email'] ?? '');
            $formData['name']      = trim($_POST['name'] ?? '');
            $formData['role']      = $_POST['role'] ?? 'member';
            $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
            $password              = $_POST['password'] ?? '';

            $error = $this->validateUser($formData, $password, true);

            if ($error === null) {
                try {
                    $newUserId = User::create(
                        $formData['email'],
                        $formData['name'],
                        $password,
                        $formData['role']
                    );

                    // Set is_active if deactivated
                    if ((int) $formData['is_active'] === 0) {
                        User::update($newUserId, ['is_active' => 0]);
                    }

                    $userId = (int) $_SESSION['user_id'];
                    ActivityService::log('user', $newUserId, 'user_created', $userId, [
                        'email'    => $formData['email'],
                        'name'     => $formData['name'],
                        'role'     => $formData['role'],
                    ]);
                    Logger::info('User created by admin', [
                        'new_user_id' => $newUserId,
                        'email'       => $formData['email'],
                    ]);

                    $_SESSION['_flash_success'] = 'Benutzer erfolgreich erstellt.';
                    $this->redirect('admin_users');
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to create user', ['error' => $e->getMessage()]);
                    $error = 'Benutzer konnte nicht erstellt werden.';
                }
            }
        }

        $pageTitle   = 'Neuer Benutzer';
        $contentView = APP_DIR . '/views/admin/users/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show edit user form (GET) or process update (POST).
     */
    public function userEdit(): void
    {
        Authz::require(Authz::ADMIN_USERS_MANAGE);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin_users');
            return;
        }

        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $error = null;
        $formData = [
            'email'     => $user['email'],
            'name'      => $user['name'],
            'role'      => $user['role'],
            'is_active' => (int) ($user['is_active'] ?? 1),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['email']     = trim($_POST['email'] ?? '');
            $formData['name']      = trim($_POST['name'] ?? '');
            $formData['role']      = $_POST['role'] ?? $user['role'];
            $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
            $password              = $_POST['password'] ?? '';

            $error = $this->validateUser($formData, $password, false, $id);

            // Self-lockout protection
            if ($error === null) {
                $error = $this->checkSelfLockout($id, $formData, $user);
            }

            if ($error === null) {
                try {
                    $updateData = [
                        'email'     => $formData['email'],
                        'name'      => $formData['name'],
                        'role'      => $formData['role'],
                        'is_active' => $formData['is_active'],
                    ];

                    if ($password !== '') {
                        $updateData['password'] = $password;
                    }

                    User::update($id, $updateData);

                    $userId = (int) $_SESSION['user_id'];
                    $meta = [
                        'target_user_id' => $id,
                        'email'          => $formData['email'],
                        'name'           => $formData['name'],
                    ];
                    if ($user['role'] !== $formData['role']) {
                        $meta['old_role'] = $user['role'];
                        $meta['new_role'] = $formData['role'];
                    }
                    if ((int) ($user['is_active'] ?? 1) !== $formData['is_active']) {
                        $meta['is_active'] = $formData['is_active'];
                    }

                    ActivityService::log('user', $id, 'user_updated', $userId, $meta);
                    Logger::info('User updated by admin', [
                        'target_user_id' => $id,
                        'email'          => $formData['email'],
                    ]);

                    $_SESSION['_flash_success'] = 'Benutzer erfolgreich aktualisiert.';
                    $this->redirect('admin_users');
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to update user', ['error' => $e->getMessage()]);
                    $error = 'Benutzer konnte nicht aktualisiert werden.';
                }
            }
        }

        $pageTitle   = 'Benutzer bearbeiten';
        $contentView = APP_DIR . '/views/admin/users/edit.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Disable a user (POST only).
     */
    public function userDisable(): void
    {
        Authz::require(Authz::ADMIN_USERS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_users');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_GET['id'] ?? 0);
        $user = User::findById($id);

        if (!$user) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Self-lockout protection: cannot disable self or last admin
        $currentUserId = (int) $_SESSION['user_id'];
        if ($id === $currentUserId) {
            $_SESSION['_flash_error'] = 'Sie koennen sich nicht selbst deaktivieren.';
            $this->redirect('admin_users');
            return;
        }

        if ($user['role'] === 'admin' && User::countActiveAdmins() <= 1) {
            $_SESSION['_flash_error'] = 'Der letzte aktive Administrator kann nicht deaktiviert werden.';
            $this->redirect('admin_users');
            return;
        }

        try {
            User::update($id, ['is_active' => 0]);

            ActivityService::log('user', $id, 'user_disabled', $currentUserId, [
                'target_user_id' => $id,
                'email'          => $user['email'],
            ]);
            Logger::info('User disabled by admin', ['target_user_id' => $id, 'email' => $user['email']]);
            $_SESSION['_flash_success'] = 'Benutzer wurde deaktiviert.';
        } catch (Throwable $e) {
            Logger::error('Failed to disable user', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Benutzer konnte nicht deaktiviert werden.';
        }

        $this->redirect('admin_users');
    }

    // ── Migrations (AP10) ────────────────────────────────────────────

    /**
     * Show pending migrations (GET) or execute them (POST).
     */
    public function migrate(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $error = null;
        $success = null;

        $currentVersion = $this->getSchemaVersion();
        $pendingMigrations = $this->getPendingMigrations($currentVersion);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            if (empty($pendingMigrations)) {
                $error = 'Keine ausstehenden Migrationen.';
            } else {
                $result = $this->executeMigrations($pendingMigrations);
                if ($result === true) {
                    $currentVersion = $this->getSchemaVersion();
                    $pendingMigrations = $this->getPendingMigrations($currentVersion);
                    $success = 'Alle Migrationen wurden erfolgreich ausgefuehrt.';
                    $_SESSION['_flash_success'] = $success;
                } else {
                    $error = 'Migration fehlgeschlagen. Details im Log.';
                }
            }
        }

        $pageTitle   = 'Datenbank-Migrationen';
        $contentView = APP_DIR . '/views/admin/migrate/index.php';
        require APP_DIR . '/views/layout.php';
    }

    private function getSchemaVersion(): int
    {
        try {
            $row = DB::fetch("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
            return $row ? (int) $row['meta_value'] : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function getPendingMigrations(int $currentVersion): array
    {
        $migrationsDir = APP_DIR . '/migrations';
        $pending = [];

        if (!is_dir($migrationsDir)) {
            return $pending;
        }

        $files = scandir($migrationsDir);
        foreach ($files as $file) {
            if (!preg_match('/^(\d{3})_.*\.sql$/', $file, $matches)) {
                continue;
            }
            $version = (int) $matches[1];
            if ($version > $currentVersion) {
                $pending[] = [
                    'file'    => $file,
                    'version' => $version,
                    'path'    => $migrationsDir . '/' . $file,
                ];
            }
        }

        usort($pending, fn($a, $b) => $a['version'] <=> $b['version']);

        return $pending;
    }

    private function executeMigrations(array $migrations): bool
    {
        foreach ($migrations as $migration) {
            try {
                $sql = file_get_contents($migration['path']);
                $statements = $this->splitSql($sql);

                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') {
                        continue;
                    }
                    DB::pdo()->exec($stmt);
                }

                // Update schema version
                DB::query(
                    "INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', ?)
                     ON DUPLICATE KEY UPDATE meta_value = ?",
                    [(string) $migration['version'], (string) $migration['version']]
                );

                // Log activity
                $userId = (int) $_SESSION['user_id'];
                ActivityService::log('system', 0, 'migration_applied', $userId, [
                    'file'        => $migration['file'],
                    'new_version' => $migration['version'],
                ]);

                Logger::info('Migration applied', [
                    'file'        => $migration['file'],
                    'new_version' => $migration['version'],
                ]);

            } catch (Throwable $e) {
                Logger::error('Migration failed', [
                    'file'  => $migration['file'],
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        }

        return true;
    }

    private function splitSql(string $sql): array
    {
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

    // ── System Info (AP10) ───────────────────────────────────────────

    /**
     * Display system information (admin only).
     */
    public function system(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $config = $GLOBALS['config'];

        // App meta from database
        $appVersion = 'Unbekannt';
        $schemaVersion = 'Unbekannt';
        $installedAt = 'Unbekannt';
        try {
            $row = DB::fetch("SELECT meta_value FROM app_meta WHERE meta_key = 'app_version'");
            if ($row) $appVersion = $row['meta_value'];

            $row = DB::fetch("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
            if ($row) $schemaVersion = $row['meta_value'];

            $row = DB::fetch("SELECT meta_value FROM app_meta WHERE meta_key = 'installed_at'");
            if ($row) $installedAt = $row['meta_value'];
        } catch (Throwable $e) {
            Logger::error('Failed to read app_meta', ['error' => $e->getMessage()]);
        }

        // MySQL version
        $mysqlVersion = 'Unbekannt';
        try {
            $row = DB::fetch("SELECT VERSION() AS v");
            if ($row) $mysqlVersion = $row['v'];
        } catch (Throwable $e) {
            // ignore
        }

        // Writable checks
        $writableChecks = [
            '/storage/logs'    => is_writable(ROOT_DIR . '/storage/logs'),
            '/storage/uploads' => is_writable(ROOT_DIR . '/storage/uploads'),
            '/storage'         => is_writable(ROOT_DIR . '/storage'),
            '/config'          => is_writable(CONFIG_DIR),
        ];

        // Extensions
        $extensions = [
            'PDO'       => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring'  => extension_loaded('mbstring'),
            'json'      => extension_loaded('json'),
            'openssl'   => extension_loaded('openssl'),
        ];

        $systemInfo = [
            'app_version'         => $appVersion,
            'schema_version'      => $schemaVersion,
            'installed_at'        => $installedAt,
            'php_version'         => PHP_VERSION,
            'mysql_version'       => $mysqlVersion,
            'memory_limit'        => ini_get('memory_limit') ?: 'Unbekannt',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'Unbekannt',
            'post_max_size'       => ini_get('post_max_size') ?: 'Unbekannt',
            'max_execution_time'  => ini_get('max_execution_time') ?: 'Unbekannt',
            'writable_checks'     => $writableChecks,
            'extensions'          => $extensions,
            'search_mode'         => $config['SEARCH_MODE'] ?? 'like',
            'debug'               => !empty($config['DEBUG']),
            'install_unlock'      => !empty($config['INSTALL_UNLOCK']),
        ];

        $pageTitle   = 'System-Informationen';
        $contentView = APP_DIR . '/views/admin/system/index.php';
        require APP_DIR . '/views/layout.php';
    }

    // ── User Validation ──────────────────────────────────────────────

    private function validateUser(array $data, string $password, bool $isCreate, ?int $excludeId = null): ?string
    {
        if ($data['email'] === '') {
            return 'E-Mail darf nicht leer sein.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Bitte eine gueltige E-Mail-Adresse eingeben.';
        }
        if ($data['name'] === '') {
            return 'Name darf nicht leer sein.';
        }
        if (!in_array($data['role'], User::ROLES, true)) {
            return 'Ungueltige Rolle.';
        }

        if (User::emailExists($data['email'], $excludeId)) {
            return 'Diese E-Mail-Adresse wird bereits verwendet.';
        }

        if ($isCreate) {
            if ($password === '') {
                return 'Passwort ist erforderlich.';
            }
            if (strlen($password) < 10) {
                return 'Passwort muss mindestens 10 Zeichen lang sein.';
            }
        } else {
            if ($password !== '' && strlen($password) < 10) {
                return 'Passwort muss mindestens 10 Zeichen lang sein.';
            }
        }

        return null;
    }

    private function checkSelfLockout(int $targetId, array $formData, array $currentUserData): ?string
    {
        $currentUserId = (int) $_SESSION['user_id'];

        if ($targetId === $currentUserId && (int) $formData['is_active'] === 0) {
            return 'Sie koennen sich nicht selbst deaktivieren.';
        }

        if ($targetId === $currentUserId && $currentUserData['role'] === 'admin' && $formData['role'] !== 'admin') {
            return 'Sie koennen sich nicht selbst die Admin-Rolle entziehen.';
        }

        if ($currentUserData['role'] === 'admin') {
            $activeAdminCount = User::countActiveAdmins();
            $wouldLoseAdmin = ($formData['role'] !== 'admin') || ((int) $formData['is_active'] === 0);

            if ($wouldLoseAdmin && $activeAdminCount <= 1) {
                return 'Der letzte aktive Administrator kann nicht herabgestuft oder deaktiviert werden.';
            }
        }

        return null;
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
