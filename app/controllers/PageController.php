<?php
/**
 * PageController - CRUD operations for pages.
 */
class PageController
{
    /**
     * List all pages (filtered by team visibility).
     */
    public function index(): void
    {
        $userId       = (int) $_SESSION['user_id'];
        $globalRole   = $_SESSION['user_role'] ?? 'viewer';
        $activeTeamId = TeamService::getActiveTeamId();

        $pages = Page::allVisible($userId, $globalRole, $activeTeamId);

        // AP16: Load teams for team badge display
        $teamsById = [];
        try {
            foreach (Team::all() as $t) {
                $teamsById[(int) $t['id']] = $t;
            }
        } catch (Throwable $e) {
            // teams table may not exist yet
        }

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

        // AP16: Team visibility check
        $userId = (int) $_SESSION['user_id'];
        if (!TeamService::canViewPage($userId, $page)) {
            Authz::deny();
        }

        $breadcrumb = Page::getBreadcrumb((int) $page['id']);
        $renderedContent = Markdown::render($page['content_md']);

        // AP5: Load linked tasks with their tags
        $pageTasks = PageTask::getTasks((int) $page['id']);
        $pageTaskTags = [];
        foreach ($pageTasks as $t) {
            $pageTaskTags[(int) $t['id']] = Task::getTags((int) $t['id']);
        }
        $users = User::allForDropdown();
        $boardColumns = BoardColumn::allOrdered();

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
        Authz::require(Authz::PAGE_CREATE);

        $error = null;
        $activeTeamId = TeamService::getActiveTeamId();
        $formData = ['title' => '', 'parent_id' => '', 'content_md' => '', 'team_id' => $activeTeamId !== null ? (string) $activeTeamId : ''];
        $parentPages = Page::allForDropdown();

        // AP16: Load teams for dropdown
        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $availableTeams = TeamService::getTeamsForSwitcher($userId, $globalRole);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']      = trim($_POST['title'] ?? '');
            $formData['parent_id']  = $_POST['parent_id'] ?? '';
            $formData['content_md'] = $_POST['content_md'] ?? '';
            $formData['team_id']    = $_POST['team_id'] ?? '';

            if ($formData['title'] === '') {
                $error = 'Titel darf nicht leer sein.';
            } else {
                try {
                    $userId = (int) $_SESSION['user_id'];

                    // AP14: Strip /commands from content (text only, no execution)
                    $cleanedContent = TextCommands::stripCommands(
                        $formData['content_md'], 'page'
                    );

                    $pageId = Page::create([
                        'title'      => $formData['title'],
                        'parent_id'  => $formData['parent_id'] !== '' ? (int) $formData['parent_id'] : null,
                        'content_md' => $cleanedContent,
                        'created_by' => $userId,
                        'team_id'    => $formData['team_id'] !== '' ? (int) $formData['team_id'] : null,
                    ]);

                    // AP14: Execute commands now that page_id exists
                    $cmdResult = TextCommands::processCommands(
                        $formData['content_md'], 'page', $userId, ['page_id' => $pageId]
                    );

                    // AP14: Sync mentions
                    TextCommands::syncMentions($cleanedContent, 'page', $pageId, $userId);

                    $newPage = Page::findById($pageId);
                    ActivityService::log('page', $pageId, 'page_created', $userId, ['title' => $formData['title']]);
                    Logger::info('Page created', ['page_id' => $pageId, 'title' => $formData['title']]);

                    // AP15: Auto-watch + event
                    WatcherService::autoWatchOnCreate('page', $pageId, $userId);
                    EventService::emit('page.created', 'page', $pageId, $userId, [
                        'title' => $formData['title'],
                    ]);

                    // AP15: Mention events
                    $mentionedIds = TextCommands::extractMentions($cleanedContent);
                    foreach ($mentionedIds as $mentionedId) {
                        EventService::emit('mention.created', 'page', $pageId, $userId, [
                            'mentioned_user_id'  => $mentionedId,
                            'parent_entity_type' => 'page',
                            'parent_entity_id'   => $pageId,
                        ]);
                    }

                    // AP14: Flash command results
                    $this->flashCommandResults($cmdResult['results']);

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
        Authz::require(Authz::PAGE_EDIT);

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

        // AP16: Team edit check
        $userId = (int) $_SESSION['user_id'];
        if (!TeamService::canEditPage($userId, $page)) {
            Authz::deny();
        }

        $error = null;
        $formData = [
            'title'      => $page['title'],
            'parent_id'  => $page['parent_id'] ?? '',
            'content_md' => $page['content_md'],
            'team_id'    => $page['team_id'] ?? '',
        ];
        $parentPages = Page::allForDropdown((int) $page['id']);

        // AP16: Load teams for dropdown
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $availableTeams = TeamService::getTeamsForSwitcher($userId, $globalRole);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']      = trim($_POST['title'] ?? '');
            $formData['parent_id']  = $_POST['parent_id'] ?? '';
            $formData['content_md'] = $_POST['content_md'] ?? '';
            $formData['team_id']    = $_POST['team_id'] ?? '';

            if ($formData['title'] === '') {
                $error = 'Titel darf nicht leer sein.';
            } else {
                try {
                    $pageId = (int) $page['id'];

                    // AP14: Process /commands before saving
                    $cmdResult = TextCommands::processCommands(
                        $formData['content_md'], 'page', $userId, ['page_id' => $pageId]
                    );
                    $cleanedContent = $cmdResult['text'];

                    Page::update($pageId, [
                        'title'      => $formData['title'],
                        'parent_id'  => $formData['parent_id'] !== '' ? (int) $formData['parent_id'] : null,
                        'content_md' => $cleanedContent,
                        'updated_by' => $userId,
                        'team_id'    => $formData['team_id'] !== '' ? (int) $formData['team_id'] : null,
                    ]);

                    // AP14: Sync mentions
                    TextCommands::syncMentions($cleanedContent, 'page', $pageId, $userId);

                    ActivityService::log('page', $pageId, 'page_updated', $userId, ['title' => $formData['title']]);
                    Logger::info('Page updated', ['page_id' => $page['id'], 'title' => $formData['title']]);

                    // AP15: Events
                    EventService::emit('page.updated', 'page', $pageId, $userId, [
                        'title' => $formData['title'],
                    ]);

                    // AP15: Mention events
                    $mentionedIds = TextCommands::extractMentions($cleanedContent);
                    foreach ($mentionedIds as $mentionedId) {
                        EventService::emit('mention.created', 'page', $pageId, $userId, [
                            'mentioned_user_id'  => $mentionedId,
                            'parent_entity_type' => 'page',
                            'parent_entity_id'   => $pageId,
                        ]);
                    }

                    // AP14: Flash command results
                    $this->flashCommandResults($cmdResult['results']);

                    $updatedPage = Page::findById($pageId);
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
        Authz::require(Authz::PAGE_DELETE);

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

        // AP16: Team edit check
        $userId = (int) $_SESSION['user_id'];
        if (!TeamService::canEditPage($userId, $page)) {
            Authz::deny();
        }

        try {
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
        Authz::require(Authz::PAGE_TASK_LINK);

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

                                // AP15: Link event
                                EventService::emit('page_task.linked', 'page', $pageId, $userId, [
                                    'task_id'    => $taskId,
                                    'task_title' => $task['title'],
                                ]);

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
                            $defaultColumnId = BoardColumn::getDefaultId();
                            $defaultColumn   = BoardColumn::findById($defaultColumnId);

                            $taskId = Task::create([
                                'title'          => $title,
                                'description_md' => null,
                                'column_id'      => $defaultColumnId,
                                'owner_id'       => $ownerId !== '' ? (int) $ownerId : null,
                                'due_date'       => $dueDate !== '' ? $dueDate : null,
                                'created_by'     => $userId,
                                'team_id'        => $page['team_id'] ?? null,
                            ]);

                            ActivityService::log('task', $taskId, 'task_created', $userId, [
                                'title'       => $title,
                                'column_name' => $defaultColumn ? $defaultColumn['name'] : '',
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

                            // AP15: Auto-watch + events
                            WatcherService::autoWatchOnCreate('task', $taskId, $userId);
                            EventService::emit('task.created', 'task', $taskId, $userId, [
                                'title' => $title,
                            ]);
                            EventService::emit('page_task.linked', 'page', $pageId, $userId, [
                                'task_id'    => $taskId,
                                'task_title' => $title,
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
        Authz::require(Authz::PAGE_TASK_UNLINK);

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

            // AP15: Unlink event
            EventService::emit('page_task.unlinked', 'page', (int) $page['id'], $userId, [
                'task_id'    => $taskId,
                'task_title' => $task ? $task['title'] : '',
            ]);
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
        Authz::require(Authz::PAGE_TASK_REORDER);

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

    // -- AP30: Page Move --------------------------------------------------

    /**
     * AP30: Show move form (GET) or process move (POST).
     */
    public function move(): void
    {
        Authz::require(Authz::PAGE_EDIT);

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

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $pageId     = (int) $page['id'];

        // AP16: Team edit check
        if (!TeamService::canEditPage($userId, $page)) {
            Authz::deny();
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $targetType    = $_POST['target_type'] ?? 'root';
            $targetParentId = null;

            if ($targetType === 'parent') {
                $targetParentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
            }

            // Validate
            if ($targetParentId !== null) {
                $targetPage = Page::findById($targetParentId);
                if (!$targetPage) {
                    $error = t('ap30.move_error_not_found');
                } elseif ($targetParentId === $pageId) {
                    $error = t('ap30.move_error_self');
                } elseif (Page::wouldCreateCycle($pageId, $targetParentId)) {
                    $error = t('ap30.move_error_cycle');
                } elseif (($page['team_id'] ?? null) !== ($targetPage['team_id'] ?? null)) {
                    $error = t('ap30.move_error_team');
                }
            }

            // Skip if parent doesn't change
            $currentParent = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;

            if ($error === null) {
                try {
                    $oldParentId = $currentParent;
                    Page::moveTo($pageId, $targetParentId, $userId);

                    ActivityService::log('page', $pageId, 'page_moved', $userId, [
                        'title'          => $page['title'],
                        'old_parent_id'  => $oldParentId,
                        'new_parent_id'  => $targetParentId,
                    ]);
                    Logger::info('Page moved', [
                        'page_id'       => $pageId,
                        'old_parent_id' => $oldParentId,
                        'new_parent_id' => $targetParentId,
                    ]);

                    $_SESSION['_flash_info'] = t('ap30.move_success');
                    $this->redirect('page_view&slug=' . urlencode($page['slug']));
                    return;
                } catch (Throwable $e) {
                    Logger::error('Failed to move page', ['error' => $e->getMessage()]);
                    $error = t('ap30.move_error_failed');
                }
            }
        }

        // Load pages for target selection (exclude self and descendants)
        $availableParents = Page::allForMoveTarget($pageId, $userId, $globalRole);

        $pageTitle   = t('ap30.move_page_title');
        $contentView = APP_DIR . '/views/pages/move.php';
        require APP_DIR . '/views/layout.php';
    }

    // -- AP30: Page Copy --------------------------------------------------

    /**
     * AP30: Show copy form (GET) or process copy (POST).
     */
    public function copy(): void
    {
        Authz::require(Authz::PAGE_CREATE);

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

        $userId     = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? 'viewer';
        $pageId     = (int) $page['id'];

        // AP16: Team visibility check
        if (!TeamService::canViewPage($userId, $page)) {
            Authz::deny();
        }

        $error = null;
        $defaultTitle = t('ap30.copy_title_prefix', ['title' => $page['title']]);
        $formData = [
            'title'            => $defaultTitle,
            'target_type'      => 'root',
            'parent_id'        => '',
            'copy_attachments' => false,
            'copy_tasks'       => false,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $formData['title']            = trim($_POST['title'] ?? '');
            $formData['target_type']      = $_POST['target_type'] ?? 'root';
            $formData['parent_id']        = $_POST['parent_id'] ?? '';
            $formData['copy_attachments'] = !empty($_POST['copy_attachments']);
            $formData['copy_tasks']       = !empty($_POST['copy_tasks']);

            if ($formData['title'] === '') {
                $error = t('errors.required', ['field' => t('labels.title')]);
            } else {
                $targetParentId = null;
                if ($formData['target_type'] === 'parent' && $formData['parent_id'] !== '') {
                    $targetParentId = (int) $formData['parent_id'];
                    $targetPage = Page::findById($targetParentId);
                    if (!$targetPage) {
                        $error = t('ap30.move_error_not_found');
                    } elseif (($page['team_id'] ?? null) !== ($targetPage['team_id'] ?? null)) {
                        $error = t('ap30.move_error_team');
                    }
                }

                if ($error === null) {
                    try {
                        // Create new page
                        $newPageId = Page::create([
                            'title'      => $formData['title'],
                            'parent_id'  => $targetParentId,
                            'content_md' => $page['content_md'] ?? '',
                            'created_by' => $userId,
                            'team_id'    => $page['team_id'] ?? null,
                        ]);

                        // Copy attachments if requested
                        if ($formData['copy_attachments']) {
                            $this->copyAttachments('page', $pageId, 'page', $newPageId, $userId, $page['team_id'] ?? null);
                        }

                        // Copy linked tasks if requested
                        if ($formData['copy_tasks']) {
                            $linkedTasks = PageTask::getTasks($pageId);
                            foreach ($linkedTasks as $lt) {
                                PageTask::addTask($newPageId, (int) $lt['id'], $userId);
                            }
                        }

                        ActivityService::log('page', $newPageId, 'page_copied', $userId, [
                            'title'          => $formData['title'],
                            'original_id'    => $pageId,
                            'original_title' => $page['title'],
                        ]);
                        // Also log on the original page
                        ActivityService::log('page', $pageId, 'page_copied_from', $userId, [
                            'new_id'    => $newPageId,
                            'new_title' => $formData['title'],
                        ]);

                        Logger::info('Page copied', [
                            'original_id' => $pageId,
                            'new_id'      => $newPageId,
                        ]);

                        // AP15: Auto-watch
                        WatcherService::autoWatchOnCreate('page', $newPageId, $userId);

                        $newPage = Page::findById($newPageId);
                        $_SESSION['_flash_info'] = t('ap30.copy_page_success');
                        $this->redirect('page_view&slug=' . urlencode($newPage['slug']));
                        return;
                    } catch (Throwable $e) {
                        Logger::error('Failed to copy page', ['error' => $e->getMessage()]);
                        $error = t('ap30.copy_page_error_failed');
                    }
                }
            }
        }

        // Load pages for parent selection
        $parentPages = Page::allForDropdown();

        $pageTitle   = t('ap30.copy_page_title');
        $contentView = APP_DIR . '/views/pages/copy.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * AP30: Copy attachments from one entity to another.
     */
    private function copyAttachments(string $srcType, int $srcId, string $dstType, int $dstId, int $userId, ?int $teamId): void
    {
        $attachments = Attachment::listFor($srcType, $srcId);
        $uploadDir = AttachmentService::getUploadDir();

        foreach ($attachments as $att) {
            $srcPath = $uploadDir . '/' . $att['stored_path'];
            if (!file_exists($srcPath)) {
                continue;
            }

            // Build new storage path
            $year = date('Y');
            $month = date('m');
            $relDir = $dstType . '/' . $year . '/' . $month;
            $absDir = $uploadDir . '/' . $relDir;

            if (!is_dir($absDir)) {
                @mkdir($absDir, 0755, true);
            }

            $random = bin2hex(random_bytes(8));
            $ext = $att['file_ext'] ?? '';
            $storedName = $random . '.' . $ext;
            $storedPath = $relDir . '/' . $storedName;
            $absPath = $absDir . '/' . $storedName;

            if (!@copy($srcPath, $absPath)) {
                Logger::error('Failed to copy attachment file', ['src' => $srcPath, 'dst' => $absPath]);
                continue;
            }
            @chmod($absPath, 0644);

            $checksum = @hash_file('sha256', $absPath);

            $newAttId = Attachment::create([
                'entity_type'     => $dstType,
                'entity_id'       => $dstId,
                'team_id'         => $teamId,
                'original_name'   => $att['original_name'],
                'stored_name'     => $storedName,
                'stored_path'     => $storedPath,
                'mime_type'       => $att['mime_type'],
                'file_ext'        => $ext,
                'file_size'       => (int) $att['file_size'],
                'checksum_sha256' => $checksum ?: null,
                'uploaded_by'     => $userId,
            ]);

            // Rename to include attachment ID
            $finalName = $newAttId . '_' . $random . '.' . $ext;
            $finalPath = $relDir . '/' . $finalName;
            $finalAbs = $absDir . '/' . $finalName;

            if (rename($absPath, $finalAbs)) {
                DB::query(
                    'UPDATE attachments SET stored_name = ?, stored_path = ? WHERE id = ?',
                    [$finalName, $finalPath, $newAttId]
                );
            }
        }
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
