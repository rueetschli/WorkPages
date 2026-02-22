<?php
/**
 * StructureController - AP25: Structure View per board.
 *
 * Routes:
 *  GET  ?r=structure&board_id=N          → index()        – render tree view
 *  POST ?r=structure_set_parent          → setParent()    – set/clear parent_task_id
 *  POST ?r=structure_set_type            → setType()      – change task_type
 *  POST ?r=structure_move_up             → moveUp()       – move item up within siblings
 *  POST ?r=structure_move_down           → moveDown()     – move item down within siblings
 *  POST ?r=structure_bulk_action         → bulkAction()   – bulk column/owner/tags changes
 */
class StructureController
{
    // ── Main view ───────────────────────────────────────────────────

    public function index(): void
    {
        $boardId = !empty($_GET['board_id']) ? (int) $_GET['board_id'] : 0;
        if ($boardId <= 0) {
            $this->redirect('boards');
            return;
        }

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

        $canEdit = BoardService::canMoveTask($userId, $board);

        // Load all tasks for this board in one query
        $flatTasks = Task::allForStructure($boardId);

        // Build tree and compute rollups
        $tree = TaskStructureService::buildTree($flatTasks);

        // Data for bulk action dropdowns
        $boardColumns = BoardColumn::allOrdered();
        $users        = User::allForDropdown();

        // Flash messages
        $flashSuccess = $_SESSION['_flash_success'] ?? null;
        $flashError   = $_SESSION['_flash_error']   ?? null;
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

        $pageTitle   = $board['name'] . ' – ' . t('structure.title');
        $contentView = APP_DIR . '/views/structure/index.php';
        require APP_DIR . '/views/layout.php';
    }

    // ── Set parent ──────────────────────────────────────────────────

    public function setParent(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();
        Authz::require(Authz::TASK_EDIT);

        $taskId   = (int) ($_POST['task_id']   ?? 0);
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $boardId  = (int) ($_POST['board_id']  ?? 0);
        $userId   = (int) $_SESSION['user_id'];

        $task = Task::findById($taskId);
        if (!$task) {
            $this->redirectToStructure($boardId, t('structure.error.task_not_found'));
            return;
        }

        $board = Board::findById($boardId);
        if (!$board || !BoardService::canMoveTask($userId, $board)) {
            Authz::deny();
        }

        // Team visibility check
        if (!TeamService::canEditTask($userId, $task)) {
            Authz::deny();
        }

        $parent = null;
        if ($parentId !== null) {
            $parent = Task::findById($parentId);
            if (!$parent) {
                $this->redirectToStructure($boardId, t('structure.error.task_not_found'));
                return;
            }
        }

        $error = TaskStructureService::validateParent($task, $parent);
        if ($error !== null) {
            $this->redirectToStructure($boardId, t($error));
            return;
        }

        $oldParentId = $task['parent_task_id'] ?? null;

        try {
            Task::setParent($taskId, $parentId, $userId);

            // Assign default structure_position among new siblings
            $newPos = Task::maxStructurePosition($boardId, $parentId) + 1000;
            Task::setStructurePosition($taskId, $newPos, $userId);

            ActivityService::log('task', $taskId, 'task_parent_changed', $userId, [
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $parentId,
            ]);
            Logger::info('Task parent changed', ['task_id' => $taskId, 'parent_id' => $parentId]);

            $this->redirectToStructure($boardId);
        } catch (Throwable $e) {
            Logger::error('Failed to set task parent', ['error' => $e->getMessage()]);
            $this->redirectToStructure($boardId, t('structure.error.save_failed'));
        }
    }

    // ── Set type ────────────────────────────────────────────────────

    public function setType(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();
        Authz::require(Authz::TASK_EDIT);

        $taskId  = (int) ($_POST['task_id']  ?? 0);
        $newType = trim($_POST['task_type']  ?? '');
        $boardId = (int) ($_POST['board_id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];

        $task = Task::findById($taskId);
        if (!$task) {
            $this->redirectToStructure($boardId, t('structure.error.task_not_found'));
            return;
        }

        $board = Board::findById($boardId);
        if (!$board || !BoardService::canMoveTask($userId, $board)) {
            Authz::deny();
        }

        if (!TeamService::canEditTask($userId, $task)) {
            Authz::deny();
        }

        $error = TaskStructureService::validateTypeChange($task, $newType);
        if ($error !== null) {
            $this->redirectToStructure($boardId, t($error));
            return;
        }

        $oldType = $task['task_type'] ?? 'task';

        try {
            Task::setType($taskId, $newType, $userId);

            // If new type is epic, clear any parent
            if ($newType === 'epic' && ($task['parent_task_id'] ?? null) !== null) {
                Task::setParent($taskId, null, $userId);
            }

            ActivityService::log('task', $taskId, 'task_type_changed', $userId, [
                'old_task_type' => $oldType,
                'new_task_type' => $newType,
            ]);
            Logger::info('Task type changed', ['task_id' => $taskId, 'type' => $newType]);

            $this->redirectToStructure($boardId);
        } catch (Throwable $e) {
            Logger::error('Failed to set task type', ['error' => $e->getMessage()]);
            $this->redirectToStructure($boardId, t('structure.error.save_failed'));
        }
    }

    // ── Reorder: move up ────────────────────────────────────────────

    public function moveUp(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();
        Authz::require(Authz::TASK_EDIT);

        $taskId  = (int) ($_POST['task_id']  ?? 0);
        $boardId = (int) ($_POST['board_id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];

        $board = Board::findById($boardId);
        if (!$board || !BoardService::canMoveTask($userId, $board)) {
            Authz::deny();
        }

        try {
            TaskStructureService::moveUp($taskId, $boardId, $userId);
            ActivityService::log('task', $taskId, 'task_structure_reordered', $userId, [
                'direction' => 'up',
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to move task up in structure', ['error' => $e->getMessage()]);
        }

        $this->redirectToStructure($boardId);
    }

    // ── Reorder: move down ──────────────────────────────────────────

    public function moveDown(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();
        Authz::require(Authz::TASK_EDIT);

        $taskId  = (int) ($_POST['task_id']  ?? 0);
        $boardId = (int) ($_POST['board_id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];

        $board = Board::findById($boardId);
        if (!$board || !BoardService::canMoveTask($userId, $board)) {
            Authz::deny();
        }

        try {
            TaskStructureService::moveDown($taskId, $boardId, $userId);
            ActivityService::log('task', $taskId, 'task_structure_reordered', $userId, [
                'direction' => 'down',
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to move task down in structure', ['error' => $e->getMessage()]);
        }

        $this->redirectToStructure($boardId);
    }

    // ── Bulk action ─────────────────────────────────────────────────

    public function bulkAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();
        Authz::require(Authz::TASK_EDIT);

        $boardId   = (int) ($_POST['board_id'] ?? 0);
        $action    = trim($_POST['bulk_action'] ?? '');
        $taskIds   = array_map('intval', (array) ($_POST['selected_tasks'] ?? []));
        $userId    = (int) $_SESSION['user_id'];

        $board = Board::findById($boardId);
        if (!$board || !BoardService::canMoveTask($userId, $board)) {
            Authz::deny();
        }

        if (empty($taskIds)) {
            $this->redirectToStructure($boardId, t('structure.bulk.no_selection'));
            return;
        }

        $allowedActions = ['set_column', 'set_owner', 'add_tags', 'remove_tags'];
        if (!in_array($action, $allowedActions, true)) {
            $this->redirectToStructure($boardId, t('structure.bulk.invalid_action'));
            return;
        }

        $processed = 0;
        $errors    = 0;

        foreach ($taskIds as $taskId) {
            try {
                $task = Task::findById($taskId);
                if (!$task) {
                    continue;
                }

                // Enforce: task must belong to this board
                if ((int) $task['board_id'] !== $boardId) {
                    continue;
                }

                // Team permission check
                if (!TeamService::canEditTask($userId, $task)) {
                    continue;
                }

                switch ($action) {
                    case 'set_column':
                        $colId = (int) ($_POST['bulk_column_id'] ?? 0);
                        $col   = BoardColumn::findById($colId);
                        if (!$col) {
                            break;
                        }
                        $oldColId = (int) $task['column_id'];
                        Task::update($taskId, [
                            'column_id'  => $colId,
                            'updated_by' => $userId,
                        ]);
                        if ($oldColId !== $colId) {
                            TaskFlowService::onColumnChange($taskId, $oldColId, $colId);
                            ActivityService::log('task', $taskId, 'task_column_changed', $userId, [
                                'old_column_id'   => $oldColId,
                                'new_column_id'   => $colId,
                                'old_column_name' => $task['column_name'] ?? '',
                                'new_column_name' => $col['name'],
                            ]);
                        }
                        $processed++;
                        break;

                    case 'set_owner':
                        $ownerId = !empty($_POST['bulk_owner_id']) ? (int) $_POST['bulk_owner_id'] : null;
                        Task::update($taskId, [
                            'owner_id'   => $ownerId,
                            'updated_by' => $userId,
                        ]);
                        ActivityService::log('task', $taskId, 'task_updated', $userId, [
                            'changed_fields' => ['owner_id'],
                        ]);
                        $processed++;
                        break;

                    case 'add_tags':
                        $newTagStr  = trim($_POST['bulk_tags'] ?? '');
                        $newTags    = Task::parseTagString($newTagStr);
                        $existing   = array_column(Task::getTags($taskId), 'name');
                        $merged     = array_unique(array_merge($existing, $newTags));
                        Task::setTags($taskId, $merged);
                        ActivityService::log('task', $taskId, 'task_tags_changed', $userId, []);
                        $processed++;
                        break;

                    case 'remove_tags':
                        $remTagStr = trim($_POST['bulk_tags'] ?? '');
                        $remTags   = Task::parseTagString($remTagStr);
                        $existing  = array_column(Task::getTags($taskId), 'name');
                        $kept      = array_values(array_diff($existing, $remTags));
                        Task::setTags($taskId, $kept);
                        ActivityService::log('task', $taskId, 'task_tags_changed', $userId, []);
                        $processed++;
                        break;
                }
            } catch (Throwable $e) {
                Logger::error('Bulk action failed for task', ['task_id' => $taskId, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        if ($errors > 0) {
            $_SESSION['_flash_error'] = t('structure.bulk.partial_error', ['count' => (string) $errors]);
        } else {
            $_SESSION['_flash_success'] = t('structure.bulk.success', ['count' => (string) $processed]);
        }

        $this->redirectToStructure($boardId);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function redirectToStructure(int $boardId, ?string $error = null): void
    {
        if ($error !== null) {
            $_SESSION['_flash_error'] = $error;
        }
        $this->redirect('structure&board_id=' . $boardId);
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
