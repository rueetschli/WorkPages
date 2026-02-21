<?php
/**
 * AdminEmailController - Email outbox management (AP15).
 * Admin-only routes for viewing and processing the email queue.
 */
class AdminEmailController
{
    /**
     * Show email queue overview.
     */
    public function queue(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        $entries      = EmailOutbox::listAll(100);
        $pendingCount = EmailOutbox::countPending();
        $failedCount  = EmailOutbox::countFailed();

        $pageTitle   = 'E-Mail Warteschlange';
        $contentView = APP_DIR . '/views/admin/email/queue.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Process pending emails (POST). Sends up to 20 emails.
     */
    public function send(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_email_queue');
            return;
        }

        Security::csrfGuard();

        $result = EmailService::processPending(20);

        $msg = $result['sent'] . ' E-Mail(s) gesendet.';
        if ($result['failed'] > 0) {
            $msg .= ' ' . $result['failed'] . ' fehlgeschlagen.';
        }

        $_SESSION['_flash_success'] = $msg;
        $this->redirect('admin_email_queue');
    }

    /**
     * Retry a failed email (POST).
     */
    public function retry(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_email_queue');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_GET['id'] ?? 0);

        if ($id > 0 && EmailOutbox::retry($id)) {
            $_SESSION['_flash_success'] = 'E-Mail #' . $id . ' zum erneuten Versand vorgemerkt.';
        } else {
            $_SESSION['_flash_error'] = 'E-Mail konnte nicht zurueckgesetzt werden.';
        }

        $this->redirect('admin_email_queue');
    }

    /**
     * Process daily digest (POST).
     */
    public function digestDaily(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_email_queue');
            return;
        }

        Security::csrfGuard();

        $result = DigestService::process('digest_daily');
        $_SESSION['_flash_success'] = 'Tages-Digest: ' . $result['emails_enqueued'] . ' E-Mail(s) erstellt fuer ' . $result['users_processed'] . ' Benutzer.';
        $this->redirect('admin_email_queue');
    }

    /**
     * Process weekly digest (POST).
     */
    public function digestWeekly(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_email_queue');
            return;
        }

        Security::csrfGuard();

        $result = DigestService::process('digest_weekly');
        $_SESSION['_flash_success'] = 'Wochen-Digest: ' . $result['emails_enqueued'] . ' E-Mail(s) erstellt fuer ' . $result['users_processed'] . ' Benutzer.';
        $this->redirect('admin_email_queue');
    }

    /**
     * AP23: Send a test email to the current admin's address (POST).
     */
    public function testEmail(): void
    {
        Authz::require(Authz::ADMIN_SETTINGS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_email_queue');
            return;
        }

        Security::csrfGuard();

        $userId = (int) $_SESSION['user_id'];
        $user = User::findById($userId);

        if (!$user) {
            $_SESSION['_flash_error'] = 'Benutzer nicht gefunden.';
            $this->redirect('admin_email_queue');
            return;
        }

        $toEmail = $user['email'];

        try {
            $appName = SystemSettingsService::companyName();
        } catch (Throwable $e) {
            $appName = $GLOBALS['config']['APP_NAME'] ?? 'WorkPages';
        }

        $subject = '[' . $appName . '] Test-E-Mail';
        $bodyHtml = '<p>Dies ist eine Test-E-Mail von ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>'
                  . '<p>Wenn Sie diese E-Mail erhalten, funktioniert der E-Mail-Versand korrekt.</p>'
                  . '<p>Gesendet: ' . date('d.m.Y H:i:s') . '</p>';
        $bodyText = 'Dies ist eine Test-E-Mail von ' . $appName . ".\n\nWenn Sie diese E-Mail erhalten, funktioniert der E-Mail-Versand korrekt.\n\nGesendet: " . date('d.m.Y H:i:s');

        // Enqueue the test email
        $outboxId = EmailOutbox::enqueue([
            'user_id'         => $userId,
            'notification_id' => null,
            'to_email'        => $toEmail,
            'subject'         => $subject,
            'body_html'       => $bodyHtml,
            'body_text'       => $bodyText,
            'send_after'      => date('Y-m-d H:i:s'),
        ]);

        // Try to send immediately
        $outboxRow = EmailOutbox::findById($outboxId);
        if ($outboxRow) {
            $result = EmailService::send($outboxRow);
            if ($result === true) {
                EmailOutbox::markSent($outboxId);
                $_SESSION['_flash_success'] = 'Test-E-Mail an ' . $toEmail . ' erfolgreich gesendet.';
            } else {
                EmailOutbox::markFailed($outboxId, $result);
                $_SESSION['_flash_error'] = 'Test-E-Mail fehlgeschlagen: ' . $result;
                Logger::error('Test email failed', ['to' => $toEmail, 'error' => $result]);
            }
        } else {
            $_SESSION['_flash_error'] = 'Test-E-Mail konnte nicht erstellt werden.';
        }

        $this->redirect('admin_email_queue');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
