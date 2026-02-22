<?php
/**
 * AdminHealthController - System health dashboard (AP28).
 *
 * Routes:
 *   GET  /?r=admin_health              - Health overview (read-only)
 *   POST /?r=admin_health_mail_send    - Process email queue
 *   POST /?r=admin_health_mail_test    - Send test email to current admin
 *   POST /?r=admin_health_webhook_send - Process webhook queue
 */
class AdminHealthController
{
    /**
     * Display the system health overview.
     */
    public function index(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        require_once APP_DIR . '/services/HealthCheckService.php';

        $checks        = HealthCheckService::runAll();
        $overallStatus = HealthCheckService::worstStatus(array_column($checks, 'status'));

        $pageTitle   = t('health.page_title');
        $contentView = APP_DIR . '/views/admin/health/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Process pending email queue (POST).
     */
    public function mailSend(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_health');
            return;
        }

        Security::csrfGuard();

        try {
            $result = EmailService::processPending(50);
            $sent   = (int) $result['sent'];
            $failed = (int) $result['failed'];

            $msg = t('health.email.action_sent', ['sent' => $sent]);
            if ($failed > 0) {
                $msg .= ' ' . t('health.email.action_failed', ['failed' => $failed]);
            }

            $_SESSION['_flash_success'] = $msg;

            ActivityService::log('system', 0, 'health_email_queue_sent', (int) ($_SESSION['user_id'] ?? 0), [
                'sent'   => $sent,
                'failed' => $failed,
            ]);
        } catch (Throwable $e) {
            Logger::error('Health: email queue processing failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('errors.server_error');
        }

        $this->redirect('admin_health');
    }

    /**
     * Send a test email to the current admin's address (POST).
     */
    public function mailTest(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_health');
            return;
        }

        Security::csrfGuard();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user   = User::findById($userId);

        if (!$user) {
            $_SESSION['_flash_error'] = t('errors.server_error');
            $this->redirect('admin_health');
            return;
        }

        $toEmail = $user['email'];
        $appName = SystemSettingsService::companyName();

        $subject  = '[' . $appName . '] ' . t('health.email.test_subject');
        $bodyHtml = '<p>' . t('health.email.test_body_line1', ['app' => htmlspecialchars($appName, ENT_QUOTES, 'UTF-8')]) . '</p>'
                  . '<p>' . t('health.email.test_body_line2') . '</p>'
                  . '<p>' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>';
        $bodyText = t('health.email.test_body_line1', ['app' => $appName]) . "\n\n"
                  . t('health.email.test_body_line2') . "\n\n"
                  . date('Y-m-d H:i:s');

        try {
            $outboxId = EmailOutbox::enqueue([
                'user_id'         => $userId,
                'notification_id' => null,
                'to_email'        => $toEmail,
                'subject'         => $subject,
                'body_html'       => $bodyHtml,
                'body_text'       => $bodyText,
                'send_after'      => date('Y-m-d H:i:s'),
            ]);

            $outboxRow = EmailOutbox::findById($outboxId);
            if ($outboxRow) {
                $result = EmailService::send($outboxRow);
                if ($result === true) {
                    EmailOutbox::markSent($outboxId);
                    $_SESSION['_flash_success'] = t('health.email.test_success', ['to' => $toEmail]);
                    ActivityService::log('system', 0, 'health_test_mail_sent', $userId, ['to' => $toEmail]);
                } else {
                    EmailOutbox::markFailed($outboxId, $result);
                    Logger::error('Health: test mail failed', ['to' => $toEmail, 'error' => $result]);
                    $_SESSION['_flash_error'] = t('health.email.test_failed');
                }
            } else {
                $_SESSION['_flash_error'] = t('errors.server_error');
            }
        } catch (Throwable $e) {
            Logger::error('Health: test mail exception', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('errors.server_error');
        }

        $this->redirect('admin_health');
    }

    /**
     * Process pending webhook queue (POST).
     */
    public function webhookSend(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_health');
            return;
        }

        Security::csrfGuard();

        try {
            $stats = WebhookDeliveryService::processPending(50);

            $_SESSION['_flash_success'] = t('health.webhooks.action_result', [
                'sent'   => (int) $stats['sent'],
                'failed' => (int) $stats['failed'],
                'dead'   => (int) $stats['dead'],
            ]);

            ActivityService::log('system', 0, 'health_webhook_queue_sent', (int) ($_SESSION['user_id'] ?? 0), [
                'sent'   => (int) $stats['sent'],
                'failed' => (int) $stats['failed'],
                'dead'   => (int) $stats['dead'],
            ]);
        } catch (Throwable $e) {
            Logger::error('Health: webhook queue processing failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('errors.server_error');
        }

        $this->redirect('admin_health');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
