<?php
/**
 * PageController - CRUD operations for pages.
 */
class PageController
{
    /**
     * List all pages.
     */
    public function index(): void
    {
        $pages = Page::all();
        $pageTitle   = 'Pages';
        $contentView = APP_DIR . '/views/pages/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show a single page by slug, including linked tasks.
     */
    public function view(): void
    {
        $slug = $_GET['slug'] ?? '';
        if ($slug === '') {
            $this->redirect('pages');
            return;
        }

        $page = Page::findBySlug($slug);
        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $breadcrumb = Page::getBreadcrumb((int) $page['id']);
        $renderedContent = Markdown::render($page['content_md']);

        // AP5: Load linked tasks with their tags
        // AP5: Load linked tasks with their tags
        $pageTasks = PageTask::getTasks((int) $page['id']);
        $pageTaskTags = [];
        foreach ($pageTasks as $t) {
            $pageTaskTags[(int) $t['id']] = Task::getTags((int) $t['id']);
        }
        $users = User::allForDropdown();

        // AP8: Load comments and activity
        $comments   = Comment::listFor('page', (int) $page['id']);
        $activities = ActivityService::listFor('page', (int) $page['id']);
        $flashError = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $pageTitle   = $page['title'];
        $contentView = APP_DIR . '/views/pages/view.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show create form (GET) or process creation (POST).
     */
    public function create(): void
    {
        Security::requireRole(['admin', 'member']);

        $error = null;
        $formData = ['title' => '', 'parent_id' => '', 'content_md' => ''];
        $parentPages = Page::allForDropdown();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']      = trim($_POST['title'] ?? '');
            $formData['parent_id']  = $_POST['parent_id'] ?? '';
            $formData['content_md'] = $_POST['content_md'] ?? '';

            if ($formData['title'] === '') {
                $error = 'Titel darf nicht leer sein.';
            } else {
                try {
                    $userId = (int) $_SESSION['user_id'];
                    $pageId = Page::create([
                        'title'      => $formData['title'],
                        'parent_id'  => $formData['parent_id'] !== '' ? (int) $formData['parent_id'] : null,
                        'content_md' => $formData['content_md'],
                        'created_by' => $userId,
                    ]);

                    $newPage = Page::findById($pageId);
                    ActivityService::log('page', $pageId, 'page_created', $userId, ['title' => $formData['title']]);
                    Logger::info('Page created', ['page_id' => $pageId, 'title' => $formData['title']]);

                    $this->redirect('page_view&slug=' . urlencode($newPage['slug']));
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to create page', ['error' => $e->getMessage()]);
                    $error = 'Seite konnte nicht erstellt werden.';
                }
            }
        }

        $pageTitle   = 'Neue Seite';
        $contentView = APP_DIR . '/views/pages/create.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Show edit form (GET) or process update (POST).
     */
    public function edit(): void
    {
        Security::requireRole(['admin', 'member']);

        $slug = $_GET['slug'] ?? '';
        if ($slug === '') {
            $this->redirect('pages');
            return;
        }

        $page = Page::findBySlug($slug);
        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $error = null;
        $formData = [
            'title'      => $page['title'],
            'parent_id'  => $page['parent_id'] ?? '',
            'content_md' => $page['content_md'],
        ];
        $parentPages = Page::allForDropdown((int) $page['id']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']      = trim($_POST['title'] ?? '');
            $formData['parent_id']  = $_POST['parent_id'] ?? '';
            $formData['content_md'] = $_POST['content_md'] ?? '';

            if ($formData['title'] === '') {
                $error = 'Titel darf nicht leer sein.';
            } else {
                try {
                    $userId = (int) $_SESSION['user_id'];
                    Page::update((int) $page['id'], [
                        'title'      => $formData['title'],
                        'parent_id'  => $formData['parent_id'] !== '' ? (int) $formData['parent_id'] : null,
                        'content_md' => $formData['content_md'],
                        'updated_by' => $userId,
                    ]);

                    ActivityService::log('page', (int) $page['id'], 'page_updated', $userId, ['title' => $formData['title']]);
                    Logger::info('Page updated', ['page_id' => $page['id'], 'title' => $formData['title']]);

                    $updatedPage = Page::findById((int) $page['id']);
                    $this->redirect('page_view&slug=' . urlencode($updatedPage['slug']));
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to update page', ['error' => $e->getMessage()]);
                    $error = 'Seite konnte nicht aktualisiert werden.';
                }
            }
        }

        $pageTitle   = 'Seite bearbeiten';
        $contentView = APP_DIR . '/views/pages/edit.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Soft-delete a page (POST only).
     */
    public function delete(): void
    {
        Security::requireRole(['admin', 'member']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pages');
            return;
        }

        Security::csrfGuard();

        $slug = $_GET['slug'] ?? '';
        $page = Page::findBySlug($slug);

        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            Page::softDelete((int) $page['id']);
            ActivityService::log('page', (int) $page['id'], 'page_deleted', $userId, ['title' => $page['title']]);
            Logger::info('Page deleted', ['page_id' => $page['id'], 'title' => $page['title']]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete page', ['error' => $e->getMessage()]);
        }

        $this->redirect('pages');
    }

    /**
     * Show add-task dialog (GET) or link a task to the page (POST).
     * Handles both linking an existing task and creating + linking a new task.
     */
    public function tasksAdd(): void
    {
        Security::requireRole(['admin', 'member']);

        $slug = $_GET['slug'] ?? '';
        $page = Page::findBySlug($slug);
        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $pageId = (int) $page['id'];
        $userId = (int) $_SESSION['user_id'];
        $error  = null;
        $success = null;
        $searchResults = [];
        $searchQuery   = '';

        // POST: either link existing task or create new task
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $action = $_POST['action'] ?? '';

            if ($action === 'link') {
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    $error = 'Keine Aufgabe ausgewaehlt.';
                } else {
                    $task = Task::findById($taskId);
                    if (!$task) {
                        $error = 'Aufgabe existiert nicht.';
                    } else {
                        try {
                            $added = PageTask::addTask($pageId, $taskId, $userId);
                            if ($added) {
                                ActivityService::log('page', $pageId, 'page_task_linked', $userId, [
                                    'task_id'    => $taskId,
                                    'task_title' => $task['title'],
                                    'page_title' => $page['title'],
                                ]);
                                Logger::info('Task linked to page', ['page_id' => $pageId, 'task_id' => $taskId]);
                                $this->redirect('page_view&slug=' . urlencode($page['slug']));
                                return;
                            } else {
                                $error = 'Aufgabe ist bereits mit dieser Seite verknuepft.';
                            }
                        } catch (Throwable $e) {
                            Logger::error('Failed to link task to page', ['error' => $e->getMessage()]);
                            $error = 'Verknuepfung konnte nicht erstellt werden.';
                        }
                    }
                }
            } elseif ($action === 'create') {
                $title   = trim($_POST['title'] ?? '');
                $dueDate = trim($_POST['due_date'] ?? '');
                $ownerId = $_POST['owner_id'] ?? '';

                if ($title === '') {
                    $error = 'Titel darf nicht leer sein.';
                } else {
                    if ($dueDate !== '') {
                        $d = date_create_from_format('Y-m-d', $dueDate);
                        if (!$d || $d->format('Y-m-d') !== $dueDate) {
                            $error = 'Ungueltiges Datum (YYYY-MM-DD erwartet).';
                        }
                    }

                    if ($error === null) {
                        try {
                            $taskId = Task::create([
                                'title'          => $title,
                                'description_md' => null,
                                'status'         => 'backlog',
                                'owner_id'       => $ownerId !== '' ? (int) $ownerId : null,
                                'due_date'       => $dueDate !== '' ? $dueDate : null,
                                'created_by'     => $userId,
                            ]);

                            ActivityService::log('task', $taskId, 'task_created', $userId, [
                                'title'  => $title,
                                'status' => 'backlog',
                            ]);

                            PageTask::addTask($pageId, $taskId, $userId);

                            ActivityService::log('page', $pageId, 'page_task_linked', $userId, [
                                'task_id'    => $taskId,
                                'task_title' => $title,
                                'page_title' => $page['title'],
                            ]);

                            Logger::info('Task created and linked to page', [
                                'page_id' => $pageId,
                                'task_id' => $taskId,
                                'title'   => $title,
                            ]);

                            $this->redirect('page_view&slug=' . urlencode($page['slug']));
                            return;
                        } catch (Throwable $e) {
                            Logger::error('Failed to create and link task', ['error' => $e->getMessage()]);
                            $error = 'Aufgabe konnte nicht erstellt werden.';
                        }
                    }
                }
            }
        }

        // GET: handle search query
        $searchQuery = trim($_GET['q'] ?? '');
        if ($searchQuery !== '') {
            $searchResults = PageTask::searchAvailableTasks($pageId, $searchQuery);
        }

        $users = User::allForDropdown();

        $pageTitle   = 'Task hinzufuegen - ' . $page['title'];
        $contentView = APP_DIR . '/views/pages/tasks_add.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Remove a task-page relation (POST only). The task itself is not deleted.
     */
    public function tasksRemove(): void
    {
        Security::requireRole(['admin', 'member']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pages');
            return;
        }

        Security::csrfGuard();

        $slug   = $_GET['slug'] ?? '';
        $taskId = (int) ($_GET['task_id'] ?? 0);
        $page   = Page::findBySlug($slug);

        if (!$page || $taskId <= 0) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            $task = Task::findById($taskId);
            PageTask::removeTask((int) $page['id'], $taskId);

            ActivityService::log('page', (int) $page['id'], 'page_task_unlinked', $userId, [
                'task_id'    => $taskId,
                'task_title' => $task ? $task['title'] : '(deleted)',
                'page_title' => $page['title'],
            ]);
            Logger::info('Task unlinked from page', ['page_id' => $page['id'], 'task_id' => $taskId]);
        } catch (Throwable $e) {
            Logger::error('Failed to unlink task from page', ['error' => $e->getMessage()]);
        }

        $this->redirect('page_view&slug=' . urlencode($page['slug']));
    }

    /**
     * Reorder a task within a page's task list (POST only).
     */
    public function tasksReorder(): void
    {
        Security::requireRole(['admin', 'member']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pages');
            return;
        }

        Security::csrfGuard();

        $slug   = $_GET['slug'] ?? '';
        $taskId = (int) ($_GET['task_id'] ?? 0);
        $dir    = $_GET['dir'] ?? '';
        $page   = Page::findBySlug($slug);

        if (!$page || $taskId <= 0 || !in_array($dir, ['up', 'down'], true)) {
            $this->redirect('pages');
            return;
        }

        try {
            PageTask::reorderTask((int) $page['id'], $taskId, $dir);
        } catch (Throwable $e) {
            Logger::error('Failed to reorder task on page', ['error' => $e->getMessage()]);
        }

        $this->redirect('page_view&slug=' . urlencode($page['slug']));
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
