<?php
/**
 * BoardController - Kanban board for visual task management (AP6 / AP13 / AP21).
 * AP13: Dynamic columns from board_columns table.
 * AP21: Multi-board support, quick-add, done semantics.
 */
class BoardController
{
    /**
     * Display the Kanban board with tasks grouped by dynamic columns.
     * AP21: Now requires a board ID parameter (?r=board_view&id=...).
     * Legacy route ?r=board redirects to last board or boards index.
     */
    public function index(): void
    {
        // AP21: Legacy route - redirect to boards index or last board
        $boardId = !empty($_GET['id']) ? (int) $_GET['id'] : null;

        if ($boardId === null) {
            // Check if there is a last-visited board in session
            $lastBoardId = BoardService::getLastBoardId();
            if ($lastBoardId !== null) {
                $lastBoard = Board::findById($lastBoardId);
                if ($lastBoard) {
                    $this->redirect('board_view&id=' . $lastBoardId);
                    return;
                }
            }
            // No last board - redirect to boards index
            $this->redirect('boards');
            return;
        }

        // Load board and validate access
        $board = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';

        if (!BoardService::canView($userId, $board)) {
            Authz::deny();
        }

        // Remember last viewed board
        BoardService::setLastBoardId($boardId);

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

        // Load board columns
        $boardColumns = BoardColumn::allOrdered();

        // AP21: Load tasks filtered by board_id and team visibility
        $activeTeamId = TeamService::getActiveTeamId();
        $allTasks = Task::allForBoardVisible($filters, $userId, $globalRole, $activeTeamId, $boardId);

        // Group tasks by column_id
        $tasksByColumn = [];
        foreach ($boardColumns as $col) {
            $tasksByColumn[(int) $col['id']] = [];
        }
        foreach ($allTasks as $task) {
            $colId = (int) $task['column_id'];
            if (isset($tasksByColumn[$colId])) {
                $tasksByColumn[$colId][] = $task;
            }
        }

        // Data for filter dropdowns
        $users   = User::allForDropdown();
        $allTags = Task::allTags();

        // AP21: Permission flags for view
        $canEdit     = BoardService::canMoveTask($userId, $board);
        $canQuickAdd = BoardService::canCreateTask($userId, $board);
        $canManage   = BoardService::canManage($userId, $board);

        $pageTitle   = $board['name'];
        $contentView = APP_DIR . '/views/board/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * AP21: Quick-add a task directly from the board.
     * POST only, CSRF protected.
     */
    public function quickAdd(): void
    {
        Authz::require(Authz::BOARD_QUICK_ADD);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $boardId  = (int) ($_POST['board_id'] ?? 0);
        $columnId = (int) ($_POST['column_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $userId   = (int) $_SESSION['user_id'];

        // Validate board
        $board = Board::findById($boardId);
        if (!$board) {
            http_response_code(404);
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Board nicht gefunden.']);
                return;
            }
            require APP_DIR . '/views/404.php';
            return;
        }

        // Permission check
        if (!BoardService::canCreateTask($userId, $board)) {
            if ($this->isAjax()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung.']);
                return;
            }
            Authz::deny();
        }

        // Validate column
        $column = BoardColumn::findById($columnId);
        if (!$column) {
            if ($this->isAjax()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Ungueltige Spalte.']);
                return;
            }
            $_SESSION['_flash_error'] = 'Ungueltige Spalte.';
            $this->redirect('board_view&id=' . $boardId);
            return;
        }

        // Validate title
        if ($title === '') {
            if ($this->isAjax()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Titel ist erforderlich.']);
                return;
            }
            $_SESSION['_flash_error'] = 'Titel ist erforderlich.';
            $this->redirect('board_view&id=' . $boardId);
            return;
        }

        try {
            $taskId = Task::create([
                'title'      => $title,
                'column_id'  => $columnId,
                'board_id'   => $boardId,
                'team_id'    => $board['team_id'] ?? null,
                'owner_id'   => $userId,
                'created_by' => $userId,
            ]);

            // AP18: Set initial flow dates based on column category
            TaskFlowService::onTaskCreated($taskId, $columnId);

            ActivityService::log('task', $taskId, 'task_created', $userId, [
                'title'       => $title,
                'column_id'   => $columnId,
                'column_name' => $column['name'] ?? '',
                'board_id'    => $boardId,
                'board_name'  => $board['name'],
                'quick_add'   => true,
            ]);
            Logger::info('Task quick-added', ['task_id' => $taskId, 'board_id' => $boardId]);

            // AP15: Auto-watch + event
            WatcherService::autoWatchOnCreate('task', $taskId, $userId);
            EventService::emit('task.created', 'task', $taskId, $userId, [
                'title' => $title,
            ]);

            if ($this->isAjax()) {
                $task = Task::findById($taskId);
                header('Content-Type: application/json');
                echo json_encode([
                    'ok'      => true,
                    'task_id' => $taskId,
                    'title'   => $title,
                    'owner_name' => $task['owner_name'] ?? '',
                ]);
                return;
            }

            $_SESSION['_flash_success'] = 'Task "' . $title . '" wurde erstellt.';
        } catch (Throwable $e) {
            Logger::error('Quick-add failed', ['error' => $e->getMessage(), 'board_id' => $boardId]);

            if ($this->isAjax()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Task konnte nicht erstellt werden.']);
                return;
            }

            $_SESSION['_flash_error'] = 'Task konnte nicht erstellt werden.';
        }

        $this->redirect('board_view&id=' . $boardId);
    }

    /**
     * Move a task to a new column (and optionally reposition).
     * POST only, CSRF protected, requires member/admin role.
     */
    public function move(): void
    {
        Authz::require(Authz::BOARD_MOVE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $taskId      = (int) ($_POST['task_id'] ?? 0);
        $newColumnId = (int) ($_POST['new_column_id'] ?? 0);
        $afterId     = !empty($_POST['after_id'])  ? (int) $_POST['after_id']  : null;
        $beforeId    = !empty($_POST['before_id']) ? (int) $_POST['before_id'] : null;
        $boardId     = !empty($_POST['board_id']) ? (int) $_POST['board_id'] : null;

        // Validate task exists
        $task = Task::findById($taskId);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // AP16: Team edit check
        $currentUserId = (int) $_SESSION['user_id'];
        if (!TeamService::canEditTask($currentUserId, $task)) {
            Authz::deny();
        }

        // Validate target column exists
        $targetColumn = BoardColumn::findById($newColumnId);
        if (!$targetColumn) {
            http_response_code(400);
            echo 'Ungueltige Spalte.';
            Logger::error('Board move: invalid column', ['column_id' => $newColumnId]);
            return;
        }

        $userId      = (int) $_SESSION['user_id'];
        $oldColumnId = (int) $task['column_id'];

        try {
            Task::moveToColumn($taskId, $newColumnId, $afterId, $beforeId, $userId);

            // AP18/AP21: Update flow timestamps (done semantics)
            if ($oldColumnId !== $newColumnId) {
                TaskFlowService::onColumnChange($taskId, $oldColumnId, $newColumnId);
            }

            // Log column change if column actually changed
            if ($oldColumnId !== $newColumnId) {
                $oldColumn = BoardColumn::findById($oldColumnId);
                ActivityService::log('task', $taskId, 'task_column_changed', $userId, [
                    'old_column_id'   => $oldColumnId,
                    'new_column_id'   => $newColumnId,
                    'old_column_name' => $oldColumn['name'] ?? '',
                    'new_column_name' => $targetColumn['name'],
                    'board'           => true,
                ]);
                Logger::info('Board move: column changed', [
                    'task_id' => $taskId,
                    'old'     => $oldColumnId,
                    'new'     => $newColumnId,
                ]);

                // AP15: Move event
                EventService::emit('task.moved', 'task', $taskId, $userId, [
                    'old_column_id'   => $oldColumnId,
                    'new_column_id'   => $newColumnId,
                    'new_column_name' => $targetColumn['name'],
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
        $this->redirectWithFilters($boardId);
    }

    /**
     * Reorder tasks within a single column.
     * POST only, CSRF protected, requires member/admin role.
     */
    public function reorder(): void
    {
        Authz::require(Authz::BOARD_REORDER);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $columnId = (int) ($_POST['column_id'] ?? 0);
        $taskIds  = $_POST['task_ids'] ?? [];
        $boardId  = !empty($_POST['board_id']) ? (int) $_POST['board_id'] : null;

        // Validate column
        $column = BoardColumn::findById($columnId);
        if (!$column) {
            http_response_code(400);
            echo 'Ungueltige Spalte.';
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
            Task::reorderColumn($columnId, $taskIds);

            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                return;
            }
        } catch (Throwable $e) {
            Logger::error('Board reorder failed', ['error' => $e->getMessage(), 'column_id' => $columnId]);

            if ($this->isAjax()) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Sortierung fehlgeschlagen.']);
                return;
            }
        }

        $this->redirectWithFilters($boardId);
    }

    // ── Column Management (AP13) ────────────────────────────────────

    /**
     * Show column management page.
     */
    public function columns(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        $columns = BoardColumn::allOrdered();

        // Count tasks per column
        $taskCounts = [];
        foreach ($columns as $col) {
            $taskCounts[(int) $col['id']] = BoardColumn::taskCount((int) $col['id']);
        }

        $pageTitle   = 'Board-Spalten verwalten';
        $contentView = APP_DIR . '/views/board/columns.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new column. POST only.
     */
    public function columnCreate(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $name     = trim($_POST['name'] ?? '');
        $color    = trim($_POST['color'] ?? '');
        $wipLimit = trim($_POST['wip_limit'] ?? '');
        $category = trim($_POST['category'] ?? 'active');

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Name der Spalte ist erforderlich.';
            $this->redirect('board_columns');
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 100) {
            $_SESSION['_flash_error'] = 'Name darf maximal 100 Zeichen lang sein.';
            $this->redirect('board_columns');
            return;
        }

        $userId = (int) $_SESSION['user_id'];

        $id = BoardColumn::create([
            'name'      => $name,
            'color'     => $color,
            'wip_limit' => $wipLimit !== '' ? (int) $wipLimit : null,
            'category'  => $category,
        ]);

        ActivityService::log('board_column', $id, 'column_created', $userId, [
            'column_name' => $name,
        ]);

        $_SESSION['_flash_success'] = 'Spalte "' . $name . '" wurde erstellt.';
        $this->redirect('board_columns');
    }

    /**
     * Update a column (rename, color, WIP limit). POST only.
     */
    public function columnUpdate(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $color    = trim($_POST['color'] ?? '');
        $wipLimit = trim($_POST['wip_limit'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $column = BoardColumn::findById($id);
        if (!$column) {
            $_SESSION['_flash_error'] = 'Spalte nicht gefunden.';
            $this->redirect('board_columns');
            return;
        }

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Name der Spalte ist erforderlich.';
            $this->redirect('board_columns');
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 100) {
            $_SESSION['_flash_error'] = 'Name darf maximal 100 Zeichen lang sein.';
            $this->redirect('board_columns');
            return;
        }

        $userId  = (int) $_SESSION['user_id'];
        $oldName = $column['name'];

        $updateData = [
            'name'      => $name,
            'color'     => $color,
            'wip_limit' => $wipLimit !== '' ? (int) $wipLimit : null,
        ];
        if ($category !== '' && in_array($category, ['backlog', 'active', 'done'], true)) {
            $updateData['category'] = $category;
        }
        BoardColumn::update($id, $updateData);

        ActivityService::log('board_column', $id, 'column_updated', $userId, [
            'old_name'    => $oldName,
            'new_name'    => $name,
            'column_name' => $name,
        ]);

        $_SESSION['_flash_success'] = 'Spalte "' . $name . '" wurde aktualisiert.';
        $this->redirect('board_columns');
    }

    /**
     * Delete a column and move tasks to a target column. POST only.
     */
    public function columnDelete(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $id             = (int) ($_POST['id'] ?? 0);
        $targetColumnId = (int) ($_POST['target_column_id'] ?? 0);

        $column = BoardColumn::findById($id);
        if (!$column) {
            $_SESSION['_flash_error'] = 'Spalte nicht gefunden.';
            $this->redirect('board_columns');
            return;
        }

        // Must have at least 2 columns (can't delete the last one)
        if (BoardColumn::count() <= 1) {
            $_SESSION['_flash_error'] = 'Die letzte Spalte kann nicht geloescht werden.';
            $this->redirect('board_columns');
            return;
        }

        // Validate target column
        if ($targetColumnId === $id) {
            $_SESSION['_flash_error'] = 'Zielspalte darf nicht die zu loeschende Spalte sein.';
            $this->redirect('board_columns');
            return;
        }

        $targetColumn = BoardColumn::findById($targetColumnId);
        if (!$targetColumn) {
            $_SESSION['_flash_error'] = 'Zielspalte nicht gefunden.';
            $this->redirect('board_columns');
            return;
        }

        $userId    = (int) $_SESSION['user_id'];
        $taskCount = BoardColumn::taskCount($id);

        BoardColumn::delete($id, $targetColumnId);

        // Fix positions in target column
        Task::renumberColumn($targetColumnId);

        ActivityService::log('board_column', $id, 'column_deleted', $userId, [
            'column_name'      => $column['name'],
            'target_column_id' => $targetColumnId,
            'target_column'    => $targetColumn['name'],
            'tasks_moved'      => $taskCount,
        ]);

        $msg = 'Spalte "' . $column['name'] . '" wurde geloescht.';
        if ($taskCount > 0) {
            $msg .= ' ' . $taskCount . ' Task(s) wurden nach "' . $targetColumn['name'] . '" verschoben.';
        }
        $_SESSION['_flash_success'] = $msg;
        $this->redirect('board_columns');
    }

    /**
     * Move column up. POST only.
     */
    public function columnMoveUp(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_POST['id'] ?? 0);
        BoardColumn::moveUp($id);

        $this->redirect('board_columns');
    }

    /**
     * Move column down. POST only.
     */
    public function columnMoveDown(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_POST['id'] ?? 0);
        BoardColumn::moveDown($id);

        $this->redirect('board_columns');
    }

    /**
     * Set a column as the default for new tasks. POST only.
     */
    public function columnSetDefault(): void
    {
        Authz::require(Authz::BOARD_COLUMNS_MANAGE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board_columns');
            return;
        }

        Security::csrfGuard();

        $id = (int) ($_POST['id'] ?? 0);
        $column = BoardColumn::findById($id);
        if (!$column) {
            $_SESSION['_flash_error'] = 'Spalte nicht gefunden.';
            $this->redirect('board_columns');
            return;
        }

        BoardColumn::setDefault($id);

        $_SESSION['_flash_success'] = '"' . $column['name'] . '" ist jetzt die Standard-Spalte fuer neue Tasks.';
        $this->redirect('board_columns');
    }

    // ── Helpers ──────────────────────────────────────────────────────

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
     * AP21: Now includes board_id.
     */
    private function redirectWithFilters(?int $boardId = null): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        $params  = ['r' => 'board_view'];

        // Board ID from POST or filter
        if ($boardId === null && !empty($_POST['board_id'])) {
            $boardId = (int) $_POST['board_id'];
        }
        if ($boardId !== null) {
            $params['id'] = $boardId;
        }

        foreach (['owner_id', 'tag', 'due', 'q'] as $key) {
            if (!empty($_POST['_filter_' . $key])) {
                $params[$key] = $_POST['_filter_' . $key];
            }
        }

        header('Location: ' . $baseUrl . '/?' . http_build_query($params));
        exit;
    }
}
