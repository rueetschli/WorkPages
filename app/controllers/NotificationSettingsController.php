<?php
/**
 * NotificationSettingsController - Per-user notification preferences (AP15).
 */
class NotificationSettingsController
{
    /**
     * Show settings form (GET) or save settings (POST).
     */
    public function index(): void
    {
        Security::requireLogin();

        $userId   = (int) $_SESSION['user_id'];
        $settings = NotificationSetting::getForUser($userId);
        $error    = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $data = [
                'email_enabled'          => isset($_POST['email_enabled']) ? 1 : 0,
                'email_mode'             => $_POST['email_mode'] ?? 'immediate',
                'email_address_override' => trim($_POST['email_address_override'] ?? ''),
                'watch_auto_on_create'   => isset($_POST['watch_auto_on_create']) ? 1 : 0,
                'watch_auto_on_comment'  => isset($_POST['watch_auto_on_comment']) ? 1 : 0,
                'notify_on_task_updates' => isset($_POST['notify_on_task_updates']) ? 1 : 0,
                'notify_on_page_updates' => isset($_POST['notify_on_page_updates']) ? 1 : 0,
                'notify_on_comments'     => isset($_POST['notify_on_comments']) ? 1 : 0,
                'notify_on_mentions'     => isset($_POST['notify_on_mentions']) ? 1 : 0,
                'notify_on_assignments'  => isset($_POST['notify_on_assignments']) ? 1 : 0,
                'notify_on_moves'        => isset($_POST['notify_on_moves']) ? 1 : 0,
            ];

            // Validate email mode
            $validModes = ['immediate', 'digest_daily', 'digest_weekly', 'digest_off'];
            if (!in_array($data['email_mode'], $validModes, true)) {
                $data['email_mode'] = 'immediate';
            }

            // Validate email override
            if ($data['email_address_override'] !== '') {
                if (!filter_var($data['email_address_override'], FILTER_VALIDATE_EMAIL)) {
                    $error = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
                }
            } else {
                $data['email_address_override'] = null;
            }

            if ($error === null) {
                NotificationSetting::update($userId, $data);
                $settings = NotificationSetting::getForUser($userId);
                $_SESSION['_flash_success'] = 'Benachrichtigungseinstellungen gespeichert.';
                $this->redirect('settings_notifications');
                return;
            }
        }

        $pageTitle   = 'Benachrichtigungseinstellungen';
        $contentView = APP_DIR . '/views/settings/notifications.php';
        require APP_DIR . '/views/layout.php';
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
