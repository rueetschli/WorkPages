<?php
/**
 * AdminController - User management for administrators (AP9).
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
        } catch (Throwable $e) {
            Logger::error('Failed to disable user', ['error' => $e->getMessage()]);
        }

        $this->redirect('admin_users');
    }

    /**
     * Validate user form data.
     *
     * @param bool $isCreate  True for create, false for edit
     * @param int|null $excludeId  User ID to exclude from email uniqueness check
     */
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

        // Email uniqueness
        if (User::emailExists($data['email'], $excludeId)) {
            return 'Diese E-Mail-Adresse wird bereits verwendet.';
        }

        // Password validation
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

    /**
     * Check for self-lockout scenarios:
     * - Last active admin cannot be demoted to non-admin
     * - Last active admin cannot be deactivated
     * - Current user cannot deactivate themselves
     */
    private function checkSelfLockout(int $targetId, array $formData, array $currentUserData): ?string
    {
        $currentUserId = (int) $_SESSION['user_id'];

        // Cannot deactivate yourself
        if ($targetId === $currentUserId && (int) $formData['is_active'] === 0) {
            return 'Sie koennen sich nicht selbst deaktivieren.';
        }

        // Cannot demote yourself from admin
        if ($targetId === $currentUserId && $currentUserData['role'] === 'admin' && $formData['role'] !== 'admin') {
            return 'Sie koennen sich nicht selbst die Admin-Rolle entziehen.';
        }

        // Last active admin protection
        if ($currentUserData['role'] === 'admin') {
            $activeAdminCount = User::countActiveAdmins();

            // If target is currently an active admin and would lose admin status or be deactivated
            $wouldLoseAdmin = ($formData['role'] !== 'admin') || ((int) $formData['is_active'] === 0);

            if ($wouldLoseAdmin && $activeAdminCount <= 1) {
                return 'Der letzte aktive Administrator kann nicht herabgestuft oder deaktiviert werden.';
            }
        }

        return null;
    }

    /**
     * Redirect helper.
     */
    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
