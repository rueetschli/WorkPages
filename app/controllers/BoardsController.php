<?php
/**
 * BoardsController - Board listing, creation, editing, deletion (AP21).
 */
class BoardsController
{
    /**
     * List all boards the user can see.
     */
    public function index(): void
    {
        Authz::require(Authz::BOARDS_VIEW);

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';

        $boards = Board::allVisibleTo($userId, $globalRole);

        // Group boards by team
        $grouped = ['global' => []];
        foreach ($boards as $board) {
            $teamId = $board['team_id'] ?? null;
            if ($teamId === null) {
                $grouped['global'][] = $board;
            } else {
                $teamName = $board['team_name'] ?? ('Team #' . $teamId);
                if (!isset($grouped[$teamName])) {
                    $grouped[$teamName] = [];
                }
                $grouped[$teamName][] = $board;
            }
        }

        // Count tasks per board
        $taskCounts = [];
        foreach ($boards as $board) {
            $taskCounts[(int) $board['id']] = Board::taskCount((int) $board['id']);
        }

        // Available teams for board creation
        $availableTeams = TeamService::getTeamsForSwitcher($userId, $globalRole);

        // Can the user create boards?
        $canCreate = Authz::can(Authz::BOARDS_CREATE);

        $pageTitle   = 'Boards';
        $contentView = APP_DIR . '/views/boards/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new board. POST only.
     */
    public function create(): void
    {
        Authz::require(Authz::BOARDS_CREATE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $teamId      = !empty($_POST['team_id']) ? (int) $_POST['team_id'] : null;
        $userId      = (int) $_SESSION['user_id'];

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Board-Name ist erforderlich.';
            $this->redirect('boards');
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 150) {
            $_SESSION['_flash_error'] = 'Board-Name darf maximal 150 Zeichen lang sein.';
            $this->redirect('boards');
            return;
        }

        // Check team permission
        if (!BoardService::canCreateBoard($userId, $teamId)) {
            Authz::deny();
        }

        // Check name uniqueness
        if (!Board::isNameUnique($name, $teamId)) {
            $_SESSION['_flash_error'] = 'Ein Board mit diesem Namen existiert bereits in diesem Team.';
            $this->redirect('boards');
            return;
        }

        try {
            $boardId = Board::create([
                'name'        => $name,
                'description' => $description !== '' ? $description : null,
                'team_id'     => $teamId,
                'created_by'  => $userId,
            ]);

            ActivityService::log('board', $boardId, 'board_created', $userId, [
                'name' => $name,
            ]);

            $_SESSION['_flash_success'] = 'Board "' . $name . '" wurde erstellt.';
            $this->redirect('board_view&id=' . $boardId);
        } catch (Throwable $e) {
            Logger::error('Failed to create board', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Board konnte nicht erstellt werden.';
            $this->redirect('boards');
        }
    }

    /**
     * Edit a board. POST only.
     */
    public function edit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $userId      = (int) $_SESSION['user_id'];

        $board = Board::findById($id);
        if (!$board) {
            $_SESSION['_flash_error'] = 'Board nicht gefunden.';
            $this->redirect('boards');
            return;
        }

        if (!BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Board-Name ist erforderlich.';
            $this->redirect('board_view&id=' . $id);
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 150) {
            $_SESSION['_flash_error'] = 'Board-Name darf maximal 150 Zeichen lang sein.';
            $this->redirect('board_view&id=' . $id);
            return;
        }

        // Check name uniqueness (excluding current board)
        $teamId = $board['team_id'] ?? null;
        if (!Board::isNameUnique($name, $teamId !== null ? (int) $teamId : null, $id)) {
            $_SESSION['_flash_error'] = 'Ein Board mit diesem Namen existiert bereits.';
            $this->redirect('board_view&id=' . $id);
            return;
        }

        try {
            Board::update($id, [
                'name'        => $name,
                'description' => $description !== '' ? $description : null,
            ]);

            ActivityService::log('board', $id, 'board_updated', $userId, [
                'old_name' => $board['name'],
                'new_name' => $name,
            ]);

            $_SESSION['_flash_success'] = 'Board wurde aktualisiert.';
        } catch (Throwable $e) {
            Logger::error('Failed to update board', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Board konnte nicht aktualisiert werden.';
        }

        $this->redirect('board_view&id=' . $id);
    }

    /**
     * Delete a board. POST only.
     */
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('boards');
            return;
        }

        Security::csrfGuard();

        $id     = (int) ($_POST['id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        $board = Board::findById($id);
        if (!$board) {
            $_SESSION['_flash_error'] = 'Board nicht gefunden.';
            $this->redirect('boards');
            return;
        }

        if (!BoardService::canManage($userId, $board)) {
            Authz::deny();
        }

        try {
            $boardName = $board['name'];
            Board::delete($id);

            ActivityService::log('board', $id, 'board_deleted', $userId, [
                'name' => $boardName,
            ]);

            $_SESSION['_flash_success'] = 'Board "' . $boardName . '" wurde geloescht. Tasks wurden vom Board entfernt.';
        } catch (Throwable $e) {
            Logger::error('Failed to delete board', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Board konnte nicht geloescht werden.';
        }

        $this->redirect('boards');
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
