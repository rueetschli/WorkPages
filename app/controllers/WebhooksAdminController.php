<?php
/**
 * WebhooksAdminController - Webhook endpoint management UI (AP19).
 *
 * Routes:
 *   /?r=admin_webhooks              List webhooks
 *   /?r=admin_webhook_create        Create (GET/POST)
 *   /?r=admin_webhook_edit          Edit (GET/POST)
 *   /?r=admin_webhook_delete        Delete (POST)
 *   /?r=admin_webhook_regen_secret  Regenerate secret (POST)
 */
class WebhooksAdminController
{
    /**
     * List all webhook endpoints.
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $webhooks = WebhookService::listEndpoints();
        $allEvents = WebhookService::EVENTS;

        $pageTitle   = 'Webhooks';
        $contentView = APP_DIR . '/views/admin/webhooks/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new webhook endpoint.
     */
    public function create(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $allEvents = WebhookService::EVENTS;
        $teams = Team::all();
        $error = null;
        $formData = ['name' => '', 'url' => '', 'team_id' => '', 'events' => []];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['name'] = trim($_POST['name'] ?? '');
            $formData['url'] = trim($_POST['url'] ?? '');
            $formData['team_id'] = $_POST['team_id'] ?? '';
            $formData['events'] = $_POST['events'] ?? [];

            $error = $this->validate($formData, $allEvents);

            if ($error === null) {
                try {
                    $endpointId = WebhookService::createEndpoint([
                        'name'       => $formData['name'],
                        'url'        => $formData['url'],
                        'team_id'    => !empty($formData['team_id']) ? (int) $formData['team_id'] : null,
                        'events'     => implode(',', $formData['events']),
                        'created_by' => (int) $_SESSION['user_id'],
                    ]);

                    Logger::info('Webhook endpoint created', [
                        'endpoint_id' => $endpointId,
                        'name'        => $formData['name'],
                    ]);

                    $_SESSION['_flash_success'] = 'Webhook erstellt.';
                    $this->redirect('admin_webhooks');
                    return;
                } catch (\Throwable $e) {
                    Logger::error('Webhook creation failed', ['error' => $e->getMessage()]);
                    $error = 'Webhook konnte nicht erstellt werden.';
                }
            }
        }

        $pageTitle   = 'Neuer Webhook';
        $contentView = APP_DIR . '/views/admin/webhooks/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Edit a webhook endpoint.
     */
    public function edit(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $id = (int) ($_GET['id'] ?? 0);
        $webhook = WebhookService::findEndpoint($id);
        if (!$webhook) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $allEvents = WebhookService::EVENTS;
        $teams = Team::all();
        $error = null;
        $formData = [
            'name'    => $webhook['name'],
            'url'     => $webhook['url'],
            'team_id' => $webhook['team_id'] ?? '',
            'events'  => array_filter(array_map('trim', explode(',', $webhook['events']))),
            'is_active' => (int) $webhook['is_active'],
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['name'] = trim($_POST['name'] ?? '');
            $formData['url'] = trim($_POST['url'] ?? '');
            $formData['team_id'] = $_POST['team_id'] ?? '';
            $formData['events'] = $_POST['events'] ?? [];
            $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;

            $error = $this->validate($formData, $allEvents);

            if ($error === null) {
                try {
                    WebhookService::updateEndpoint($id, [
                        'name'      => $formData['name'],
                        'url'       => $formData['url'],
                        'events'    => implode(',', $formData['events']),
                        'is_active' => $formData['is_active'],
                    ]);

                    Logger::info('Webhook endpoint updated', ['endpoint_id' => $id]);
                    $_SESSION['_flash_success'] = 'Webhook aktualisiert.';
                    $this->redirect('admin_webhooks');
                    return;
                } catch (\Throwable $e) {
                    Logger::error('Webhook update failed', ['error' => $e->getMessage()]);
                    $error = 'Webhook konnte nicht aktualisiert werden.';
                }
            }
        }

        $pageTitle   = 'Webhook bearbeiten';
        $contentView = APP_DIR . '/views/admin/webhooks/edit.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Delete a webhook endpoint (POST only).
     */
    public function delete(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_webhooks');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_POST['webhook_id'] ?? 0);
        $webhook = WebhookService::findEndpoint($id);
        if (!$webhook) {
            $_SESSION['_flash_error'] = 'Webhook nicht gefunden.';
            $this->redirect('admin_webhooks');
            return;
        }

        try {
            WebhookService::deleteEndpoint($id);
            Logger::info('Webhook endpoint deleted', ['endpoint_id' => $id]);
            $_SESSION['_flash_success'] = 'Webhook geloescht.';
        } catch (\Throwable $e) {
            Logger::error('Webhook delete failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Webhook konnte nicht geloescht werden.';
        }

        $this->redirect('admin_webhooks');
    }

    /**
     * Regenerate webhook secret (POST only).
     */
    public function regenerateSecret(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_webhooks');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_POST['webhook_id'] ?? 0);
        $webhook = WebhookService::findEndpoint($id);
        if (!$webhook) {
            $_SESSION['_flash_error'] = 'Webhook nicht gefunden.';
            $this->redirect('admin_webhooks');
            return;
        }

        try {
            $newSecret = WebhookService::regenerateSecret($id);
            Logger::info('Webhook secret regenerated', ['endpoint_id' => $id]);
            $_SESSION['_flash_success'] = 'Neues Secret: ' . $newSecret . ' (Bitte notieren - wird nicht erneut angezeigt)';
        } catch (\Throwable $e) {
            Logger::error('Webhook secret regen failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Secret konnte nicht erneuert werden.';
        }

        $this->redirect('admin_webhooks');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function validate(array $data, array $allEvents): ?string
    {
        if ($data['name'] === '') {
            return 'Name ist erforderlich.';
        }
        if (mb_strlen($data['name'], 'UTF-8') > 100) {
            return 'Name darf maximal 100 Zeichen lang sein.';
        }
        if ($data['url'] === '') {
            return 'URL ist erforderlich.';
        }
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return 'Ungueltige URL.';
        }
        if (!str_starts_with($data['url'], 'https://') && !str_starts_with($data['url'], 'http://')) {
            return 'URL muss mit https:// oder http:// beginnen.';
        }
        if (empty($data['events'])) {
            return 'Mindestens ein Event muss ausgewaehlt werden.';
        }
        foreach ($data['events'] as $ev) {
            if (!in_array($ev, $allEvents, true)) {
                return 'Ungueltiges Event: ' . $ev;
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
