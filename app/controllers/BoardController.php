<?php
/**
 * BoardController - Kanban board for visual task management (AP6 / AP13).
 * AP13: Dynamic columns from board_columns table.
 */
class BoardController
{
    /**
     * Display the Kanban board with tasks grouped by dynamic columns.
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

        // Load board columns
        $boardColumns = BoardColumn::allOrdered();

        // Load tasks in one query with GROUP_CONCAT tags
        $allTasks = Task::allForBoard($filters);

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

        $pageTitle   = 'Board';
        $contentView = APP_DIR . '/views/board/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Move a task to a new column (and optionally reposition).
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

        $taskId      = (int) ($_POST['task_id'] ?? 0);
        $newColumnId = (int) ($_POST['new_column_id'] ?? 0);
        $afterId     = !empty($_POST['after_id'])  ? (int) $_POST['after_id']  : null;
        $beforeId    = !empty($_POST['before_id']) ? (int) $_POST['before_id'] : null;

        // Validate task exists
        $task = Task::findById($taskId);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
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
     * Reorder tasks within a single column.
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

        $columnId = (int) ($_POST['column_id'] ?? 0);
        $taskIds  = $_POST['task_ids'] ?? [];

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

        $this->redirectWithFilters();
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

        BoardColumn::update($id, [
            'name'      => $name,
            'color'     => $color,
            'wip_limit' => $wipLimit !== '' ? (int) $wipLimit : null,
        ]);

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
