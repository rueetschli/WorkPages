<?php
/**
 * ApiKeysController - API Key management UI for users (AP19).
 *
 * Routes:
 *   /?r=settings_api_keys         List own keys
 *   /?r=settings_api_key_create   Create a new key (GET/POST)
 *   /?r=settings_api_key_revoke   Revoke a key (POST)
 */
class ApiKeysController
{
    /**
     * List the current user's API keys.
     */
    public function index(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $keys = ApiAuthService::listForUser($userId);
        $availableScopes = ApiAuthService::availableScopes();

        // Check for flash of newly created key
        $newKey = $_SESSION['_flash_new_api_key'] ?? null;
        unset($_SESSION['_flash_new_api_key']);

        $pageTitle   = 'API-Schluessel';
        $contentView = APP_DIR . '/views/settings/api_keys/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new API key (GET: form, POST: process).
     */
    public function create(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $availableScopes = ApiAuthService::availableScopes();
        $error = null;
        $formData = ['name' => '', 'scopes' => []];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['name'] = trim($_POST['name'] ?? '');
            $formData['scopes'] = $_POST['scopes'] ?? [];

            // Validate
            if ($formData['name'] === '') {
                $error = 'Name ist erforderlich.';
            } elseif (mb_strlen($formData['name'], 'UTF-8') > 100) {
                $error = 'Name darf maximal 100 Zeichen lang sein.';
            } elseif (empty($formData['scopes'])) {
                $error = 'Mindestens ein Scope muss ausgewaehlt werden.';
            } else {
                // Validate scopes
                $scopesStr = ApiAuthService::validateScopes(implode(',', $formData['scopes']));
                if ($scopesStr === null) {
                    $error = 'Ungueltige Scopes.';
                }
            }

            // Viewer cannot get write scopes
            $userRole = $_SESSION['user_role'] ?? 'viewer';
            if ($error === null && $userRole === 'viewer') {
                $writeScopes = ['tasks:write', 'pages:write', 'comments:write', 'attachments:write', 'webhooks:manage'];
                foreach ($formData['scopes'] as $s) {
                    if (in_array($s, $writeScopes, true)) {
                        $error = 'Viewer-Rolle kann keine Schreib-Scopes erhalten.';
                        break;
                    }
                }
            }

            if ($error === null) {
                try {
                    $result = ApiAuthService::createKey($userId, $formData['name'], $scopesStr);

                    Logger::info('API key created', [
                        'key_id'     => $result['id'],
                        'user_id'    => $userId,
                        'key_prefix' => $result['key_prefix'],
                    ]);

                    $_SESSION['_flash_new_api_key'] = $result['key'];
                    $_SESSION['_flash_success'] = 'API-Schluessel wurde erstellt. Kopieren Sie den Schluessel jetzt - er wird nicht erneut angezeigt.';
                    $this->redirect('settings_api_keys');
                    return;
                } catch (\Throwable $e) {
                    Logger::error('API key creation failed', ['error' => $e->getMessage()]);
                    $error = 'API-Schluessel konnte nicht erstellt werden.';
                }
            }
        }

        $pageTitle   = 'Neuer API-Schluessel';
        $contentView = APP_DIR . '/views/settings/api_keys/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Revoke an API key (POST only).
     */
    public function revoke(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings_api_keys');
            return;
        }

        Security::csrfGuard();

        $keyId = (int) ($_POST['key_id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        if ($keyId <= 0) {
            $_SESSION['_flash_error'] = 'Ungueltiger Schluessel.';
            $this->redirect('settings_api_keys');
            return;
        }

        $revoked = ApiAuthService::revoke($keyId, $userId);
        if ($revoked) {
            Logger::info('API key revoked', ['key_id' => $keyId, 'user_id' => $userId]);
            $_SESSION['_flash_success'] = 'API-Schluessel wurde widerrufen.';
        } else {
            $_SESSION['_flash_error'] = 'Schluessel konnte nicht widerrufen werden.';
        }

        $this->redirect('settings_api_keys');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
