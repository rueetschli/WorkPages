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
     * Show a single page by slug.
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
                    Activity::log('page', $pageId, 'created', $userId, ['title' => $formData['title']]);
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

                    Activity::log('page', (int) $page['id'], 'updated', $userId, ['title' => $formData['title']]);
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
            Activity::log('page', (int) $page['id'], 'deleted', $userId, ['title' => $page['title']]);
            Logger::info('Page deleted', ['page_id' => $page['id'], 'title' => $page['title']]);
        } catch (Throwable $e) {
            Logger::error('Failed to delete page', ['error' => $e->getMessage()]);
        }

        $this->redirect('pages');
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
