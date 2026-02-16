<?php
/**
 * ApiNotificationsController - JSON API for notification polling (AP15).
 */
class ApiNotificationsController
{
    /**
     * Return unread notification count as JSON.
     */
    public function unreadCount(): void
    {
        Security::requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $count  = Notification::countUnread($userId);

        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
    }

    /**
     * Return latest unread notifications as JSON (for dropdown).
     */
    public function latest(): void
    {
        Security::requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $notifications = Notification::listForUser($userId, true, 10);

        $items = [];
        foreach ($notifications as $n) {
            $items[] = [
                'id'         => (int) $n['id'],
                'type'       => $n['type'],
                'title'      => $n['title'],
                'body'       => $n['body'] ?? '',
                'url'        => $n['url'],
                'actor_name' => $n['actor_name'] ?? '',
                'created_at' => $n['created_at'],
                'is_read'    => (int) $n['is_read'],
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'count' => Notification::countUnread($userId),
            'items' => $items,
        ]);
    }
}
