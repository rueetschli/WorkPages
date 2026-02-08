<?php
/**
 * TaskController - CRUD operations for tasks.
 */
class TaskController
{
    /**
     * List all tasks with optional filters.
     */
    public function index(): void
    {
        $filters = [];

        if (!empty($_GET['status']) && in_array($_GET['status'], Task::STATUSES, true)) {
            $filters['status'] = $_GET['status'];
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

        $pageTitle   = $task['title'];
        $contentView = APP_DIR . '/views/tasks/view.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show create form (GET) or process creation (POST).
     */
    public function create(): void
    {
        Security::requireRole(['admin', 'member']);

        $error    = null;
        $formData = [
            'title'          => '',
            'description_md' => '',
            'status'         => 'backlog',
            'owner_id'       => '',
            'due_date'       => '',
            'tags'           => '',
        ];
        $users = User::allForDropdown();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']          = trim($_POST['title'] ?? '');
            $formData['description_md'] = $_POST['description_md'] ?? '';
            $formData['status']         = $_POST['status'] ?? 'backlog';
            $formData['owner_id']       = $_POST['owner_id'] ?? '';
            $formData['due_date']       = trim($_POST['due_date'] ?? '');
            $formData['tags']           = trim($_POST['tags'] ?? '');

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $userId = (int) $_SESSION['user_id'];
                    $taskId = Task::create([
                        'title'          => $formData['title'],
                        'description_md' => $formData['description_md'],
                        'status'         => $formData['status'],
                        'owner_id'       => $formData['owner_id'] !== '' ? (int) $formData['owner_id'] : null,
                        'due_date'       => $formData['due_date'] !== '' ? $formData['due_date'] : null,
                        'created_by'     => $userId,
                    ]);

                    $tagNames = Task::parseTagString($formData['tags']);
                    Task::setTags($taskId, $tagNames);

                    Activity::log('task', $taskId, 'created', $userId, [
                        'title'  => $formData['title'],
                        'status' => $formData['status'],
                    ]);
                    Logger::info('Task created', ['task_id' => $taskId, 'title' => $formData['title']]);

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
        Security::requireRole(['admin', 'member']);

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
        $formData = [
            'title'          => $task['title'],
            'description_md' => $task['description_md'] ?? '',
            'status'         => $task['status'],
            'owner_id'       => $task['owner_id'] ?? '',
            'due_date'       => $task['due_date'] ?? '',
            'tags'           => $tagStr,
        ];
        $users = User::allForDropdown();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']          = trim($_POST['title'] ?? '');
            $formData['description_md'] = $_POST['description_md'] ?? '';
            $formData['status']         = $_POST['status'] ?? $task['status'];
            $formData['owner_id']       = $_POST['owner_id'] ?? '';
            $formData['due_date']       = trim($_POST['due_date'] ?? '');
            $formData['tags']           = trim($_POST['tags'] ?? '');

            $error = $this->validate($formData);

            if ($error === null) {
                try {
                    $userId   = (int) $_SESSION['user_id'];
                    $oldStatus = $task['status'];
                    $newStatus = $formData['status'];

                    Task::update($id, [
                        'title'          => $formData['title'],
                        'description_md' => $formData['description_md'],
                        'status'         => $formData['status'],
                        'owner_id'       => $formData['owner_id'] !== '' ? (int) $formData['owner_id'] : null,
                        'due_date'       => $formData['due_date'] !== '' ? $formData['due_date'] : null,
                        'updated_by'     => $userId,
                    ]);

                    $tagNames = Task::parseTagString($formData['tags']);
                    Task::setTags($id, $tagNames);

                    // Log status change separately
                    if ($oldStatus !== $newStatus) {
                        Activity::log('task', $id, 'status_changed', $userId, [
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                        ]);
                    }

                    Activity::log('task', $id, 'updated', $userId, [
                        'title'          => $formData['title'],
                        'changed_fields' => $this->changedFields($task, $formData),
                    ]);
                    Logger::info('Task updated', ['task_id' => $id, 'title' => $formData['title']]);

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
        Security::requireRole(['admin', 'member']);

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
            Activity::log('task', $id, 'deleted', $userId, ['title' => $task['title']]);
            Logger::info('Task deleted', ['task_id' => $id, 'title' => $task['title']]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete task', ['error' => $e->getMessage()]);
        }

        $this->redirect('tasks');
    }

    /**
     * Update task status via POST (used from page-view context).
     */
    public function updateStatus(): void
    {
        Security::requireRole(['admin', 'member']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('tasks');
            return;
        }

        Security::csrfGuard();

        $id     = (int) ($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $returnSlug = $_POST['return_slug'] ?? '';

        $task = Task::findById($id);
        if (!$task) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        if (!in_array($status, Task::STATUSES, true)) {
            $this->redirect('tasks');
            return;
        }

        $userId    = (int) $_SESSION['user_id'];
        $oldStatus = $task['status'];

        if ($oldStatus !== $status) {
            try {
                Task::update($id, [
                    'status'     => $status,
                    'updated_by' => $userId,
                ]);

                $meta = [
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                ];
                if ($returnSlug !== '') {
                    $meta['from_page_slug'] = $returnSlug;
                }

                Activity::log('task', $id, 'status_changed', $userId, $meta);
                Logger::info('Task status changed', ['task_id' => $id, 'old' => $oldStatus, 'new' => $status]);
            } catch (Throwable $e) {
                Logger::error('Failed to update task status', ['error' => $e->getMessage()]);
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

        if (!in_array($data['status'], Task::STATUSES, true)) {
            return 'Ungueltiger Status.';
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
            'status'         => 'status',
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
     * Redirect helper.
     */
    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
