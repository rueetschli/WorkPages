<?php
/**
 * WatchController - Toggle watcher subscriptions (AP15).
 */
class WatchController
{
    /**
     * Toggle watch state for an entity (POST).
     */
    public function toggle(): void
    {
        Authz::require(Authz::WATCH_TOGGLE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $entityType = $_POST['entity_type'] ?? '';
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $state      = $_POST['state'] ?? 'on';
        $userId     = (int) $_SESSION['user_id'];

        if (!in_array($entityType, ['page', 'task'], true) || $entityId <= 0) {
            http_response_code(400);
            $this->redirect('home');
            return;
        }

        // Verify entity exists
        $entity = null;
        if ($entityType === 'page') {
            $entity = Page::findById($entityId);
        } else {
            $entity = Task::findById($entityId);
        }

        if (!$entity) {
            http_response_code(404);
            $this->redirect('home');
            return;
        }

        // AP16: Check team access before allowing watch
        if ($entityType === 'page' && !TeamService::canViewPage($userId, $entity)) {
            Authz::deny();
        }
        if ($entityType === 'task' && !TeamService::canViewTask($userId, $entity)) {
            Authz::deny();
        }

        if ($state === 'on') {
            Watcher::watch($entityType, $entityId, $userId);
        } else {
            Watcher::unwatch($entityType, $entityId, $userId);
        }

        // AJAX response
        if ($this->isAjax()) {
            $isWatching = Watcher::isWatching($entityType, $entityId, $userId);
            $count = Watcher::countWatchers($entityType, $entityId);
            header('Content-Type: application/json');
            echo json_encode([
                'ok'       => true,
                'watching' => $isWatching,
                'count'    => $count,
            ]);
            return;
        }

        // Redirect back to entity
        if ($entityType === 'page' && isset($entity['slug'])) {
            $this->redirect('page_view&slug=' . urlencode($entity['slug']));
        } elseif ($entityType === 'task') {
            $this->redirect('task_view&id=' . $entityId);
        } else {
            $this->redirect('home');
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
