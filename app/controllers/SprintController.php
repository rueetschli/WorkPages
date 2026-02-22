<?php
/**
 * SprintController - Sprint management, burndown, and velocity reports (AP26).
 *
 * Routes:
 *   /?r=sprints&board_id=X          Sprint overview for a board
 *   /?r=sprint_create&board_id=X    Create sprint (GET form / POST)
 *   /?r=sprint_activate             Activate sprint (POST)
 *   /?r=sprint_close                Close sprint (POST)
 *   /?r=sprint_delete               Delete sprint (POST)
 *   /?r=sprint_assign_task          Assign task to sprint (POST)
 *   /?r=sprint_unassign_task        Remove task from sprint (POST)
 *   /?r=sprint_burndown&id=X        Burndown chart
 *   /?r=sprint_velocity&board_id=X  Velocity chart
 */
class SprintController
{
    /**
     * Sprint overview for a board: lists planned, active, closed sprints.
     */
    public function index(): void
    {
        Authz::require(Authz::SPRINT_VIEW);

        $boardId = (int) ($_GET['board_id'] ?? 0);
        $board   = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!BoardService::canView($userId, $board)) {
            Authz::deny();
        }

        $activeSprint  = Sprint::getActiveForBoard($boardId);
        $plannedSprints = Sprint::getPlannedForBoard($boardId);
        $closedSprints  = Sprint::getClosedForBoard($boardId);

        // Task counts
        $sprintTaskCounts = [];
        $allSprints = array_merge(
            $activeSprint ? [$activeSprint] : [],
            $plannedSprints,
            $closedSprints
        );
        foreach ($allSprints as $s) {
            $sid = (int) $s['id'];
            $sprintTaskCounts[$sid] = [
                'total'     => Sprint::taskCount($sid),
                'remaining' => Sprint::remainingTaskCount($sid),
                'completed' => Sprint::completedTaskCount($sid),
            ];
        }

        $canManage    = BoardService::canManage($userId, $board);
        $canAssign    = BoardService::canCreateTask($userId, $board);

        $pageTitle   = t('sprint.title') . ' - ' . $board['name'];
        $contentView = APP_DIR . '/views/sprints/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new sprint (GET form / POST).
     */
    public function create(): void
    {
        Authz::require(Authz::SPRINT_MANAGE);

        $boardId = (int) ($_GET['board_id'] ?? ($_POST['board_id'] ?? 0));
        $board   = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        $error    = null;
        $formData = [
            'name'       => '',
            'start_date' => date('Y-m-d'),
            'end_date'   => date('Y-m-d', strtotime('+14 days')),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['name']       = trim($_POST['name'] ?? '');
            $formData['start_date'] = trim($_POST['start_date'] ?? '');
            $formData['end_date']   = trim($_POST['end_date'] ?? '');

            $error = $this->validateSprint($formData);

            if ($error === null) {
                try {
                    $sprintId = Sprint::create([
                        'board_id'   => $boardId,
                        'name'       => $formData['name'],
                        'start_date' => $formData['start_date'],
                        'end_date'   => $formData['end_date'],
                        'created_by' => $userId,
                    ]);

                    ActivityService::log('sprint', $sprintId, 'sprint_created', $userId, [
                        'sprint_name' => $formData['name'],
                        'board_id'    => $boardId,
                    ]);

                    $_SESSION['_flash_success'] = t('sprint.created', ['name' => $formData['name']]);
                    $this->redirect('sprints&board_id=' . $boardId);
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to create sprint', ['error' => $e->getMessage()]);
                    $error = t('sprint.error.create_failed');
                }
            }
        }

        $pageTitle   = t('sprint.create_title');
        $contentView = APP_DIR . '/views/sprints/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Activate a sprint (POST).
     */
    public function activate(): void
    {
        Authz::require(Authz::SPRINT_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $sprintId = (int) ($_POST['sprint_id'] ?? 0);
        $sprint   = Sprint::findById($sprintId);
        if (!$sprint) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId  = (int) $_SESSION['user_id'];
        $boardId = (int) $sprint['board_id'];
        $board   = Board::findById($boardId);
        if (!$board || !BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        $error = SprintService::activate($sprintId, $userId);
        if ($error !== null) {
            $_SESSION['_flash_error'] = t($error);
        } else {
            $_SESSION['_flash_success'] = t('sprint.activated', ['name' => $sprint['name']]);
        }

        $this->redirect('sprints&board_id=' . $boardId);
    }

    /**
     * Close a sprint (POST).
     */
    public function close(): void
    {
        Authz::require(Authz::SPRINT_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $sprintId = (int) ($_POST['sprint_id'] ?? 0);
        $sprint   = Sprint::findById($sprintId);
        if (!$sprint) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId  = (int) $_SESSION['user_id'];
        $boardId = (int) $sprint['board_id'];
        $board   = Board::findById($boardId);
        if (!$board || !BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        $error = SprintService::close($sprintId, $userId);
        if ($error !== null) {
            $_SESSION['_flash_error'] = t($error);
        } else {
            $_SESSION['_flash_success'] = t('sprint.closed_msg', ['name' => $sprint['name']]);
        }

        $this->redirect('sprints&board_id=' . $boardId);
    }

    /**
     * Delete a planned sprint (POST).
     */
    public function delete(): void
    {
        Authz::require(Authz::SPRINT_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $sprintId = (int) ($_POST['sprint_id'] ?? 0);
        $sprint   = Sprint::findById($sprintId);
        if (!$sprint) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId  = (int) $_SESSION['user_id'];
        $boardId = (int) $sprint['board_id'];
        $board   = Board::findById($boardId);
        if (!$board || !BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        if ($sprint['status'] !== 'planned') {
            $_SESSION['_flash_error'] = t('sprint.error.delete_not_planned');
            $this->redirect('sprints&board_id=' . $boardId);
            return;
        }

        try {
            Sprint::delete($sprintId);
            $_SESSION['_flash_success'] = t('sprint.deleted', ['name' => $sprint['name']]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete sprint', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('sprint.error.delete_failed');
        }

        $this->redirect('sprints&board_id=' . $boardId);
    }

    /**
     * Assign a task to a sprint (POST, AJAX-capable).
     */
    public function assignTask(): void
    {
        Authz::require(Authz::SPRINT_ASSIGN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $taskId   = (int) ($_POST['task_id'] ?? 0);
        $sprintId = (int) ($_POST['sprint_id'] ?? 0);
        $userId   = (int) $_SESSION['user_id'];

        $error = SprintService::assignTask($taskId, $sprintId, $userId);

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            if ($error !== null) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => t($error)]);
            } else {
                echo json_encode(['ok' => true]);
            }
            return;
        }

        if ($error !== null) {
            $_SESSION['_flash_error'] = t($error);
        }

        // Redirect back to referrer or task view
        $returnTo = $_POST['return_to'] ?? '';
        if ($returnTo !== '') {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/' . $returnTo);
            exit;
        }

        $this->redirect('task_view&id=' . $taskId);
    }

    /**
     * Remove a task from its sprint (POST).
     */
    public function unassignTask(): void
    {
        Authz::require(Authz::SPRINT_ASSIGN);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $taskId = (int) ($_POST['task_id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        SprintService::unassignTask($taskId, $userId);

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            return;
        }

        $returnTo = $_POST['return_to'] ?? '';
        if ($returnTo !== '') {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/' . $returnTo);
            exit;
        }

        $this->redirect('task_view&id=' . $taskId);
    }

    /**
     * Burndown chart for a sprint.
     */
    public function burndown(): void
    {
        Authz::require(Authz::SPRINT_VIEW);

        $sprintId = (int) ($_GET['id'] ?? 0);
        $sprint   = Sprint::findById($sprintId);
        if (!$sprint) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $boardId = (int) $sprint['board_id'];
        $board   = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!BoardService::canView($userId, $board)) {
            Authz::deny();
        }

        $burndownData = SprintService::getBurndownData($sprintId);
        $tasks        = Sprint::getTasks($sprintId);

        $pageTitle   = t('sprint.burndown') . ' - ' . $sprint['name'];
        $contentView = APP_DIR . '/views/sprints/burndown.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Velocity chart for a board.
     */
    public function velocity(): void
    {
        Authz::require(Authz::SPRINT_VIEW);

        $boardId = (int) ($_GET['board_id'] ?? 0);
        $board   = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!BoardService::canView($userId, $board)) {
            Authz::deny();
        }

        $velocityData = SprintService::getVelocityData($boardId);

        $pageTitle   = t('sprint.velocity') . ' - ' . $board['name'];
        $contentView = APP_DIR . '/views/sprints/velocity.php';
        require APP_DIR . '/views/layout.php';
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function validateSprint(array $data): ?string
    {
        if ($data['name'] === '') {
            return t('sprint.error.name_required');
        }
        if (mb_strlen($data['name'], 'UTF-8') > 150) {
            return t('sprint.error.name_too_long');
        }

        // Validate dates
        $start = date_create_from_format('Y-m-d', $data['start_date']);
        if (!$start || $start->format('Y-m-d') !== $data['start_date']) {
            return t('sprint.error.invalid_start_date');
        }

        $end = date_create_from_format('Y-m-d', $data['end_date']);
        if (!$end || $end->format('Y-m-d') !== $data['end_date']) {
            return t('sprint.error.invalid_end_date');
        }

        if ($data['end_date'] <= $data['start_date']) {
            return t('sprint.error.end_before_start');
        }

        return null;
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
