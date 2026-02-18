<?php
/**
 * WebhookQueueAdminController - Webhook outbox/queue management UI (AP19).
 *
 * Routes:
 *   /?r=admin_webhook_queue       View queue with status counts
 *   /?r=admin_webhook_queue_send  Process pending deliveries (POST)
 *   /?r=admin_webhook_queue_retry Retry a dead entry (POST)
 */
class WebhookQueueAdminController
{
    /**
     * View the webhook queue.
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $statusFilter = $_GET['status'] ?? null;
        if ($statusFilter !== null && !in_array($statusFilter, ['pending', 'sent', 'failed', 'dead'], true)) {
            $statusFilter = null;
        }

        $counts = WebhookDeliveryService::countByStatus();
        $entries = WebhookDeliveryService::listOutbox($statusFilter, 200);

        $pageTitle   = 'Webhook Queue';
        $contentView = APP_DIR . '/views/admin/webhooks/queue.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Process pending webhook deliveries (POST).
     */
    public function send(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_webhook_queue');
            return;
        }

        Security::csrfGuard();

        try {
            $stats = WebhookDeliveryService::processPending(50);
            Logger::info('Webhook queue processed', $stats);

            $msg = sprintf(
                'Queue verarbeitet: %d gesendet, %d fehlgeschlagen, %d dead.',
                $stats['sent'],
                $stats['failed'],
                $stats['dead']
            );
            $_SESSION['_flash_success'] = $msg;
        } catch (\Throwable $e) {
            Logger::error('Webhook queue processing failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Queue-Verarbeitung fehlgeschlagen.';
        }

        $this->redirect('admin_webhook_queue');
    }

    /**
     * Retry a dead webhook entry (POST).
     */
    public function retry(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_webhook_queue');
            return;
        }

        Security::csrfGuard();

        $entryId = (int) ($_POST['entry_id'] ?? 0);
        if ($entryId <= 0) {
            $_SESSION['_flash_error'] = 'Ungueltiger Eintrag.';
            $this->redirect('admin_webhook_queue');
            return;
        }

        $result = WebhookDeliveryService::retryEntry($entryId);
        if ($result) {
            Logger::info('Webhook dead entry retried', ['entry_id' => $entryId]);
            $_SESSION['_flash_success'] = 'Eintrag wurde zurueck in die Queue gestellt.';
        } else {
            $_SESSION['_flash_error'] = 'Eintrag konnte nicht erneut versucht werden.';
        }

        $this->redirect('admin_webhook_queue&status=dead');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
