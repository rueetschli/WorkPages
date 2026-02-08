<?php
/**
 * ShareController - Token-based page sharing for external access (AP9).
 *
 * Handles:
 * - Public share view (no login required)
 * - Creating share links (member/admin)
 * - Revoking share links (member/admin)
 */
class ShareController
{
    /**
     * Display a shared page (public, no login required).
     * Only shows the page content in a minimal layout.
     */
    public function view(): void
    {
        $token = $_GET['page_token'] ?? '';
        if ($token === '') {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $share = PageShare::findByToken($token);
        if (!$share) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Check revoked
        if ($share['revoked_at'] !== null) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Check expired
        if ($share['expires_at'] !== null && strtotime($share['expires_at']) <= time()) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $page = Page::findById((int) $share['page_id']);
        if (!$page || $page['deleted_at'] !== null) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $renderedContent = Markdown::render($page['content_md']);

        // Load linked tasks for read-only display
        $pageTasks = PageTask::getTasks((int) $page['id']);
        $pageTaskTags = [];
        foreach ($pageTasks as $t) {
            $pageTaskTags[(int) $t['id']] = Task::getTags((int) $t['id']);
        }

        $pageTitle = $page['title'];
        require APP_DIR . '/views/share/view.php';
    }

    /**
     * Create a share link for a page (POST only).
     */
    public function create(): void
    {
        Authz::require(Authz::SHARE_CREATE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pages');
            return;
        }

        Security::csrfGuard();

        $pageId    = (int) ($_POST['page_id'] ?? 0);
        $expiresAt = trim($_POST['expires_at'] ?? '');
        $userId    = (int) $_SESSION['user_id'];

        $page = Page::findById($pageId);
        if (!$page) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        // Validate optional expiry date
        $expiresValue = null;
        if ($expiresAt !== '') {
            $d = date_create_from_format('Y-m-d', $expiresAt);
            if (!$d || $d->format('Y-m-d') !== $expiresAt) {
                $_SESSION['_flash_error'] = 'Ungueltiges Ablaufdatum.';
                $this->redirect('page_view&slug=' . urlencode($page['slug']));
                return;
            }
            $expiresValue = $expiresAt . ' 23:59:59';
        }

        try {
            // Revoke existing active shares first
            PageShare::revokeAllForPage($pageId);

            $shareId = PageShare::create($pageId, $userId, $expiresValue);
            $share = PageShare::findById($shareId);

            ActivityService::log('page', $pageId, 'share_created', $userId, [
                'share_id' => $shareId,
                'page_id'  => $pageId,
                'title'    => $page['title'],
            ]);
            Logger::info('Share link created', ['share_id' => $shareId, 'page_id' => $pageId]);
        } catch (Throwable $e) {
            Logger::error('Failed to create share link', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Share-Link konnte nicht erstellt werden.';
        }

        $this->redirect('page_view&slug=' . urlencode($page['slug']));
    }

    /**
     * Revoke a share link (POST only).
     */
    public function revoke(): void
    {
        Authz::require(Authz::SHARE_REVOKE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pages');
            return;
        }

        Security::csrfGuard();

        $shareId = (int) ($_POST['share_id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];

        $share = PageShare::findById($shareId);
        if (!$share) {
            $this->redirect('pages');
            return;
        }

        $page = Page::findById((int) $share['page_id']);

        try {
            PageShare::revoke($shareId);

            ActivityService::log('page', (int) $share['page_id'], 'share_revoked', $userId, [
                'share_id' => $shareId,
                'page_id'  => (int) $share['page_id'],
                'title'    => $page ? $page['title'] : '',
            ]);
            Logger::info('Share link revoked', ['share_id' => $shareId]);
        } catch (Throwable $e) {
            Logger::error('Failed to revoke share link', ['error' => $e->getMessage()]);
        }

        if ($page) {
            $this->redirect('page_view&slug=' . urlencode($page['slug']));
        } else {
            $this->redirect('pages');
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
