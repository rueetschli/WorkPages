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

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
