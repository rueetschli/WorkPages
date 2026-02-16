<?php
/**
 * NotificationsController - In-app notification management (AP15).
 */
class NotificationsController
{
    /**
     * List notifications for the current user.
     */
    public function index(): void
    {
        Security::requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $filter = $_GET['filter'] ?? 'all';

        $unreadOnly = ($filter === 'unread') ? true : null;
        $notifications = Notification::listForUser($userId, $unreadOnly, 50);
        $unreadCount = Notification::countUnread($userId);

        $pageTitle   = 'Benachrichtigungen';
        $contentView = APP_DIR . '/views/notifications/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Mark a single notification as read (POST).
     */
    public function markRead(): void
    {
        Security::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('notifications');
            return;
        }

        Security::csrfGuard();

        $id     = (int) ($_POST['id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        Notification::markRead($id, $userId);

        // If redirect URL provided, go there
        $redirectUrl = $_POST['redirect_url'] ?? '';
        if ($redirectUrl !== '' && str_starts_with($redirectUrl, '/?r=')) {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . $redirectUrl);
            exit;
        }

        $this->redirect('notifications');
    }

    /**
     * Mark all notifications as read (POST).
     */
    public function markAllRead(): void
    {
        Security::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('notifications');
            return;
        }

        Security::csrfGuard();

        $userId = (int) $_SESSION['user_id'];
        Notification::markAllRead($userId);

        $_SESSION['_flash_success'] = 'Alle Benachrichtigungen als gelesen markiert.';
        $this->redirect('notifications');
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
