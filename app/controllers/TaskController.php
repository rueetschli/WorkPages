<?php
/**
 * TaskController - CRUD operations for tasks.
 * AP13: Uses column_id (board_columns) instead of fixed status ENUM.
 */
class TaskController
{
    /**
     * List all tasks with optional filters.
     */
    public function index(): void
    {
        $filters = [];

        if (!empty($_GET['column_id'])) {
            $filters['column_id'] = (int) $_GET['column_id'];
        }
        if (!empty($_GET['owner_id'])) {
            $filters['owner_id'] = (int) $_GET['owner_id'];
        }
        if (!empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }

        $tasks      = Task::all($filters);
        $users      = User::allForDropdown();
        $allTags    = Task::allTags();
        $tagsByTask = [];
        $boardColumns = BoardColumn::allOrdered();

        foreach ($tasks as $task) {
            $tagsByTask[(int) $task['id']] = Task::getTags((int) $task['id']);
        }

        $pageTitle   = 'Tasks';
        $contentView = APP_DIR . '/views/tasks/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show a single task.
     */
    public function view(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('tasks');
            return;
        }

        $task = Task::findById($id);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $tags             = Task::getTags($id);
        $renderedContent  = ($task['description_md'] !== null && $task['description_md'] !== '')
            ? Markdown::render($task['description_md'])
            : '';
        $users            = User::allForDropdown();
        $linkedPages      = PageTask::getPages($id);
        $boardColumns     = BoardColumn::allOrdered();

        // AP8: Load comments and activity
        $comments   = Comment::listFor('task', $id);
        $activities = ActivityService::listFor('task', $id);
        $flashError = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $pageTitle   = $task['title'];
        $contentView = APP_DIR . '/views/tasks/view.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show create form (GET) or process creation (POST).
     */
    public function create(): void
    {
        Authz::require(Authz::TASK_CREATE);

        $error    = null;
        $boardColumns = BoardColumn::allOrdered();
        $defaultColumnId = BoardColumn::getDefaultId();
        $formData = [
            'title'          => '',
            'description_md' => '',
            'column_id'      => (string) $defaultColumnId,
            'owner_id'       => '',
            'due_date'       => '',
            'tags'           => '',
        ];
        $users = User::allForDropdown();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']          = trim($_POST['title'] ?? '');
            $formData['description_md'] = $_POST['description_md'] ?? '';
            $formData['column_id']      = $_POST['column_id'] ?? (string) $defaultColumnId;
            $formData['owner_id']       = $_POST['owner_id'] ?? '';
            $formData['due_date']       = trim($_POST['due_date'] ?? '');
            $formData['tags']           = trim($_POST['tags'] ?? '');

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $userId = (int) $_SESSION['user_id'];
                    $columnId = (int) $formData['column_id'];
                    $column = BoardColumn::findById($columnId);

                    // AP14: Strip /commands from description (text only, no execution)
                    $cleanedDesc = TextCommands::stripCommands(
                        $formData['description_md'], 'task'
                    );

                    $taskId = Task::create([
                        'title'          => $formData['title'],
                        'description_md' => $cleanedDesc,
                        'column_id'      => $columnId,
                        'owner_id'       => $formData['owner_id'] !== '' ? (int) $formData['owner_id'] : null,
                        'due_date'       => $formData['due_date'] !== '' ? $formData['due_date'] : null,
                        'created_by'     => $userId,
                    ]);

                    $tagNames = Task::parseTagString($formData['tags']);
                    Task::setTags($taskId, $tagNames);

                    // AP14: Execute commands now that task_id exists
                    $cmdResult = TextCommands::processCommands(
                        $formData['description_md'], 'task', $userId, ['task_id' => $taskId]
                    );

                    // AP14: Sync mentions
                    TextCommands::syncMentions($cleanedDesc, 'task', $taskId, $userId);

                    ActivityService::log('task', $taskId, 'task_created', $userId, [
                        'title'       => $formData['title'],
                        'column_id'   => $columnId,
                        'column_name' => $column['name'] ?? '',
                    ]);
                    Logger::info('Task created', ['task_id' => $taskId, 'title' => $formData['title']]);

                    // AP14: Flash command results
                    $this->flashCommandResults($cmdResult['results']);

                    $this->redirect('task_view&id=' . $taskId);
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to create task', ['error' => $e->getMessage()]);
                    $error = 'Aufgabe konnte nicht erstellt werden.';
                }
            }
        }

        $pageTitle   = 'Neue Aufgabe';
        $contentView = APP_DIR . '/views/tasks/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show edit form (GET) or process update (POST).
     */
    public function edit(): void
    {
        Authz::require(Authz::TASK_EDIT);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('tasks');
            return;
        }

        $task = Task::findById($id);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $error    = null;
        $tags     = Task::getTags($id);
        $tagStr   = implode(', ', array_column($tags, 'name'));
        $boardColumns = BoardColumn::allOrdered();
        $formData = [
            'title'          => $task['title'],
            'description_md' => $task['description_md'] ?? '',
            'column_id'      => (string) $task['column_id'],
            'owner_id'       => $task['owner_id'] ?? '',
            'due_date'       => $task['due_date'] ?? '',
            'tags'           => $tagStr,
        ];
        $users = User::allForDropdown();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']          = trim($_POST['title'] ?? '');
            $formData['description_md'] = $_POST['description_md'] ?? '';
            $formData['column_id']      = $_POST['column_id'] ?? (string) $task['column_id'];
            $formData['owner_id']       = $_POST['owner_id'] ?? '';
            $formData['due_date']       = trim($_POST['due_date'] ?? '');
            $formData['tags']           = trim($_POST['tags'] ?? '');

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $userId      = (int) $_SESSION['user_id'];
                    $oldColumnId = (int) $task['column_id'];
                    $newColumnId = (int) $formData['column_id'];

                    // AP14: Process /commands before saving
                    $cmdResult = TextCommands::processCommands(
                        $formData['description_md'], 'task', $userId, ['task_id' => $id]
                    );
                    $cleanedDesc = $cmdResult['text'];

                    Task::update($id, [
                        'title'          => $formData['title'],
                        'description_md' => $cleanedDesc,
                        'column_id'      => $newColumnId,
                        'owner_id'       => $formData['owner_id'] !== '' ? (int) $formData['owner_id'] : null,
                        'due_date'       => $formData['due_date'] !== '' ? $formData['due_date'] : null,
                        'updated_by'     => $userId,
                    ]);

                    $tagNames = Task::parseTagString($formData['tags']);
                    Task::setTags($id, $tagNames);

                    // AP14: Sync mentions
                    TextCommands::syncMentions($cleanedDesc, 'task', $id, $userId);

                    // Log column change separately
                    if ($oldColumnId !== $newColumnId) {
                        $oldColumn = BoardColumn::findById($oldColumnId);
                        $newColumn = BoardColumn::findById($newColumnId);
                        ActivityService::log('task', $id, 'task_column_changed', $userId, [
                            'old_column_id'   => $oldColumnId,
                            'new_column_id'   => $newColumnId,
                            'old_column_name' => $oldColumn['name'] ?? '',
                            'new_column_name' => $newColumn['name'] ?? '',
                        ]);
                    }

                    ActivityService::log('task', $id, 'task_updated', $userId, [
                        'title'          => $formData['title'],
                        'changed_fields' => $this->changedFields($task, $formData),
                    ]);
                    Logger::info('Task updated', ['task_id' => $id, 'title' => $formData['title']]);

                    // AP14: Flash command results
                    $this->flashCommandResults($cmdResult['results']);

                    $this->redirect('task_view&id=' . $id);
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to update task', ['error' => $e->getMessage()]);
                    $error = 'Aufgabe konnte nicht aktualisiert werden.';
                }
            }
        }

        $pageTitle   = 'Aufgabe bearbeiten';
        $contentView = APP_DIR . '/views/tasks/edit.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Delete a task (POST only).
     */
    public function delete(): void
    {
        Authz::require(Authz::TASK_DELETE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('tasks');
            return;
        }

        Security::csrfGuard();

        $id   = (int) ($_GET['id'] ?? 0);
        $task = Task::findById($id);

        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            Task::delete($id);
            ActivityService::log('task', $id, 'task_deleted', $userId, ['title' => $task['title']]);
            Logger::info('Task deleted', ['task_id' => $id, 'title' => $task['title']]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete task', ['error' => $e->getMessage()]);
        }

        $this->redirect('tasks');
    }

    /**
     * Update task column via POST (used from page-view context).
     */
    public function updateStatus(): void
    {
        Authz::require(Authz::TASK_CHANGE_STATUS);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('tasks');
            return;
        }

        Security::csrfGuard();

        $id         = (int) ($_POST['task_id'] ?? 0);
        $columnId   = (int) ($_POST['column_id'] ?? 0);
        $returnSlug = $_POST['return_slug'] ?? '';

        $task = Task::findById($id);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $targetColumn = BoardColumn::findById($columnId);
        if (!$targetColumn) {
            $this->redirect('tasks');
            return;
        }

        $userId      = (int) $_SESSION['user_id'];
        $oldColumnId = (int) $task['column_id'];

        if ($oldColumnId !== $columnId) {
            try {
                Task::update($id, [
                    'column_id'  => $columnId,
                    'updated_by' => $userId,
                ]);

                $oldColumn = BoardColumn::findById($oldColumnId);
                $meta = [
                    'old_column_id'   => $oldColumnId,
                    'new_column_id'   => $columnId,
                    'old_column_name' => $oldColumn['name'] ?? '',
                    'new_column_name' => $targetColumn['name'],
                ];
                if ($returnSlug !== '') {
                    $meta['from_page_slug'] = $returnSlug;
                }

                ActivityService::log('task', $id, 'task_column_changed', $userId, $meta);
                Logger::info('Task column changed', ['task_id' => $id, 'old' => $oldColumnId, 'new' => $columnId]);
            } catch (Throwable $e) {
                Logger::error('Failed to update task column', ['error' => $e->getMessage()]);
            }
        }

        if ($returnSlug !== '') {
            $this->redirect('page_view&slug=' . urlencode($returnSlug));
        } else {
            $this->redirect('task_view&id=' . $id);
        }
    }

    /**
     * Validate form data. Returns error message or null.
     */
    private function validate(array $data): ?string
    {
        if ($data['title'] === '') {
            return 'Titel darf nicht leer sein.';
        }

        // Validate column_id
        if (empty($data['column_id'])) {
            return 'Spalte ist erforderlich.';
        }
        $column = BoardColumn::findById((int) $data['column_id']);
        if (!$column) {
            return 'Ungueltige Spalte ausgewaehlt.';
        }

        if ($data['owner_id'] !== '' && $data['owner_id'] !== null) {
            $owner = User::findById((int) $data['owner_id']);
            if (!$owner) {
                return 'Ausgewaehlter Benutzer existiert nicht.';
            }
        }

        if ($data['due_date'] !== '') {
            $d = date_create_from_format('Y-m-d', $data['due_date']);
            if (!$d || $d->format('Y-m-d') !== $data['due_date']) {
                return 'Ungueltiges Datum (YYYY-MM-DD erwartet).';
            }
        }

        return null;
    }

    /**
     * Determine which fields changed between old task and new form data.
     */
    private function changedFields(array $old, array $new): array
    {
        $changed = [];
        $map = [
            'title'          => 'title',
            'description_md' => 'description_md',
            'column_id'      => 'column_id',
            'owner_id'       => 'owner_id',
            'due_date'       => 'due_date',
        ];

        foreach ($map as $field => $formKey) {
            $oldVal = (string) ($old[$field] ?? '');
            $newVal = (string) ($new[$formKey] ?? '');
            if ($oldVal !== $newVal) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * AP14: Store command execution results as flash messages.
     */
    private function flashCommandResults(array $results): void
    {
        if (empty($results)) {
            return;
        }

        $messages = [];
        foreach ($results as $r) {
            $messages[] = $r['message'] ?? '';
        }
        $combined = implode(' ', array_filter($messages));
        if ($combined !== '') {
            $_SESSION['_flash_info'] = $combined;
        }
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
