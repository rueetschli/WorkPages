<?php
/**
 * BoardController - Kanban board for visual task management (AP6).
 */
class BoardController
{
    /**
     * Display the Kanban board with tasks grouped by status columns.
     */
    public function index(): void
    {
        // Initialize positions on first board load (migration compat)
        Task::initBoardPositions();

        // Collect filters from query string
        $filters = [];
        if (!empty($_GET['owner_id'])) {
            $filters['owner_id'] = (int) $_GET['owner_id'];
        }
        if (!empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }
        if (!empty($_GET['due']) && in_array($_GET['due'], ['overdue', 'today', 'week', 'none'], true)) {
            $filters['due'] = $_GET['due'];
        }
        if (!empty($_GET['q'])) {
            $filters['q'] = trim($_GET['q']);
        }

        // Load tasks in one query with GROUP_CONCAT tags
        $allTasks = Task::allForBoard($filters);

        // Group tasks by status for column display
        $columns = [];
        foreach (Task::STATUSES as $status) {
            $columns[$status] = [];
        }
        foreach ($allTasks as $task) {
            $status = $task['status'];
            if (isset($columns[$status])) {
                $columns[$status][] = $task;
            }
        }

        // Data for filter dropdowns
        $users   = User::allForDropdown();
        $allTags = Task::allTags();

        $pageTitle   = 'Board';
        $contentView = APP_DIR . '/views/board/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Move a task to a new status (and optionally reposition).
     * POST only, CSRF protected, requires member/admin role.
     */
    public function move(): void
    {
        Authz::require(Authz::BOARD_MOVE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board');
            return;
        }

        Security::csrfGuard();

        $taskId    = (int) ($_POST['task_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $afterId   = !empty($_POST['after_id'])  ? (int) $_POST['after_id']  : null;
        $beforeId  = !empty($_POST['before_id']) ? (int) $_POST['before_id'] : null;

        // Validate task exists
        $task = Task::findById($taskId);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Validate status
        if (!in_array($newStatus, Task::STATUSES, true)) {
            http_response_code(400);
            echo 'Ungueltiger Status.';
            Logger::error('Board move: invalid status', ['status' => $newStatus]);
            return;
        }

        $userId    = (int) $_SESSION['user_id'];
        $oldStatus = $task['status'];

        try {
            Task::moveToStatus($taskId, $newStatus, $afterId, $beforeId, $userId);

            // Log status change if status actually changed
            if ($oldStatus !== $newStatus) {
                ActivityService::log('task', $taskId, 'task_status_changed', $userId, [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'board'      => true,
                ]);
                Logger::info('Board move: status changed', [
                    'task_id' => $taskId,
                    'old'     => $oldStatus,
                    'new'     => $newStatus,
                ]);
            }

            // AJAX request: return JSON
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                return;
            }
        } catch (Throwable $e) {
            Logger::error('Board move failed', ['error' => $e->getMessage(), 'task_id' => $taskId]);

            if ($this->isAjax()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Aktion fehlgeschlagen.']);
                return;
            }
        }

        // Redirect back to board preserving filters
        $this->redirectWithFilters();
    }

    /**
     * Reorder tasks within a single status column.
     * POST only, CSRF protected, requires member/admin role.
     */
    public function reorder(): void
    {
        Authz::require(Authz::BOARD_REORDER);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board');
            return;
        }

        Security::csrfGuard();

        $status  = $_POST['status'] ?? '';
        $taskIds = $_POST['task_ids'] ?? [];

        if (!in_array($status, Task::STATUSES, true)) {
            http_response_code(400);
            echo 'Ungueltiger Status.';
            return;
        }

        if (!is_array($taskIds) || empty($taskIds)) {
            http_response_code(400);
            echo 'Keine Tasks angegeben.';
            return;
        }

        // Sanitize IDs
        $taskIds = array_map('intval', $taskIds);

        try {
            Task::reorderColumn($status, $taskIds);

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                return;
            }
        } catch (Throwable $e) {
            Logger::error('Board reorder failed', ['error' => $e->getMessage(), 'status' => $status]);

            if ($this->isAjax()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Sortierung fehlgeschlagen.']);
                return;
            }
        }

        $this->redirectWithFilters();
    }

    /**
     * Check if the current request is an AJAX/fetch request.
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Redirect to board route.
     */
    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }

    /**
     * Redirect back to board preserving current filter parameters.
     */
    private function redirectWithFilters(): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $params  = ['r' => 'board'];

        foreach (['owner_id', 'tag', 'due', 'q'] as $key) {
            if (!empty($_POST['_filter_' . $key])) {
                $params[$key] = $_POST['_filter_' . $key];
            }
        }

        header('Location: ' . $baseUrl . '/?' . http_build_query($params));
        exit;
    }
}
