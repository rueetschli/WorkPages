<?php
/**
 * UserViewController – AP27: Saved Views CRUD.
 *
 * Routes:
 *  POST ?r=view_save         → save()       – create new view
 *  POST ?r=view_update       → update()     – rename / change default / overwrite params
 *  POST ?r=view_delete       → delete()     – delete a view
 *  POST ?r=view_set_default  → setDefault() – toggle default flag
 */
class UserViewController
{
    /** Maximum number of saved views per user. */
    private const MAX_VIEWS_PER_USER = 50;

    // ── Save (create) ──────────────────────────────────────────

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $userId    = (int) $_SESSION['user_id'];
        $name      = trim($_POST['view_name'] ?? '');
        $viewType  = trim($_POST['view_type'] ?? '');
        $contextId = !empty($_POST['context_id']) ? (int) $_POST['context_id'] : null;
        $isDefault = !empty($_POST['is_default']);
        $returnTo  = trim($_POST['return_to'] ?? '');

        // Validate
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
            $_SESSION['_flash_error'] = t('views.error.name_required');
            $this->redirectBack($returnTo);
            return;
        }

        if (!UserView::isValidType($viewType)) {
            $_SESSION['_flash_error'] = t('views.error.invalid_type');
            $this->redirectBack($returnTo);
            return;
        }

        // Context validation: board/structure need a valid board_id
        if (in_array($viewType, ['board', 'structure'], true) && $contextId !== null) {
            $board = Board::findById($contextId);
            if (!$board || !BoardService::canView($userId, $board)) {
                $_SESSION['_flash_error'] = t('views.error.invalid_context');
                $this->redirectBack($returnTo);
                return;
            }
        }

        // Limit check
        if (UserView::countForUser($userId) >= self::MAX_VIEWS_PER_USER) {
            $_SESSION['_flash_error'] = t('views.error.limit_reached');
            $this->redirectBack($returnTo);
            return;
        }

        // Collect parameters from POST (filter values)
        $parameters = $this->collectParameters($viewType);

        try {
            $viewId = UserView::create([
                'user_id'    => $userId,
                'name'       => $name,
                'view_type'  => $viewType,
                'context_id' => $contextId,
                'parameters' => $parameters,
                'is_default' => $isDefault,
            ]);

            ActivityService::log('user_view', $viewId, 'view_created', $userId, [
                'name'      => $name,
                'view_type' => $viewType,
            ]);

            Logger::info('View created', ['view_id' => $viewId, 'user_id' => $userId]);
            $_SESSION['_flash_success'] = t('views.saved', ['name' => $name]);
        } catch (Throwable $e) {
            Logger::error('View save failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            $_SESSION['_flash_error'] = t('views.error.save_failed');
        }

        $this->redirectBack($returnTo);
    }

    // ── Update ─────────────────────────────────────────────────

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $userId   = (int) $_SESSION['user_id'];
        $viewId   = (int) ($_POST['view_id'] ?? 0);
        $returnTo = trim($_POST['return_to'] ?? '');

        $view = UserView::findByIdForUser($viewId, $userId);
        if (!$view) {
            $_SESSION['_flash_error'] = t('views.error.not_found');
            $this->redirectBack($returnTo);
            return;
        }

        $updateData = [];

        // Name change
        $newName = trim($_POST['view_name'] ?? '');
        if ($newName !== '' && $newName !== $view['name']) {
            if (mb_strlen($newName, 'UTF-8') > 150) {
                $_SESSION['_flash_error'] = t('views.error.name_required');
                $this->redirectBack($returnTo);
                return;
            }
            $updateData['name'] = $newName;
        }

        // Default flag
        if (isset($_POST['is_default'])) {
            $updateData['is_default'] = !empty($_POST['is_default']);
        }

        // Overwrite parameters (if requested)
        if (!empty($_POST['overwrite_params'])) {
            $updateData['parameters'] = $this->collectParameters($view['view_type']);
        }

        if (!empty($updateData)) {
            try {
                UserView::update($viewId, $updateData);

                ActivityService::log('user_view', $viewId, 'view_updated', $userId, [
                    'name'           => $updateData['name'] ?? $view['name'],
                    'changed_fields' => array_keys($updateData),
                ]);

                Logger::info('View updated', ['view_id' => $viewId]);
                $_SESSION['_flash_success'] = t('views.updated');
            } catch (Throwable $e) {
                Logger::error('View update failed', ['error' => $e->getMessage()]);
                $_SESSION['_flash_error'] = t('views.error.save_failed');
            }
        }

        $this->redirectBack($returnTo);
    }

    // ── Delete ─────────────────────────────────────────────────

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $userId   = (int) $_SESSION['user_id'];
        $viewId   = (int) ($_POST['view_id'] ?? 0);
        $returnTo = trim($_POST['return_to'] ?? '');

        $view = UserView::findByIdForUser($viewId, $userId);
        if (!$view) {
            $_SESSION['_flash_error'] = t('views.error.not_found');
            $this->redirectBack($returnTo);
            return;
        }

        try {
            UserView::delete($viewId);

            ActivityService::log('user_view', $viewId, 'view_deleted', $userId, [
                'name' => $view['name'],
            ]);

            Logger::info('View deleted', ['view_id' => $viewId]);
            $_SESSION['_flash_success'] = t('views.deleted', ['name' => $view['name']]);
        } catch (Throwable $e) {
            Logger::error('View delete failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('views.error.delete_failed');
        }

        $this->redirectBack($returnTo);
    }

    // ── Set Default ────────────────────────────────────────────

    public function setDefault(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $userId   = (int) $_SESSION['user_id'];
        $viewId   = (int) ($_POST['view_id'] ?? 0);
        $returnTo = trim($_POST['return_to'] ?? '');

        $view = UserView::findByIdForUser($viewId, $userId);
        if (!$view) {
            $_SESSION['_flash_error'] = t('views.error.not_found');
            $this->redirectBack($returnTo);
            return;
        }

        try {
            // Toggle: if already default, unset; otherwise set
            $newDefault = empty($view['is_default']);
            UserView::update($viewId, ['is_default' => $newDefault]);

            $_SESSION['_flash_success'] = $newDefault
                ? t('views.set_as_default', ['name' => $view['name']])
                : t('views.unset_default', ['name' => $view['name']]);
        } catch (Throwable $e) {
            Logger::error('View set-default failed', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = t('views.error.save_failed');
        }

        $this->redirectBack($returnTo);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Collect filter parameters from POST based on view type.
     * Only whitelisted keys are accepted; values are sanitized.
     */
    private function collectParameters(string $viewType): array
    {
        $params = [];

        switch ($viewType) {
            case 'board':
                foreach (['owner_id', 'tag', 'due', 'q'] as $key) {
                    $val = trim($_POST['param_' . $key] ?? '');
                    if ($val !== '') {
                        $params[$key] = $key === 'owner_id' ? (int) $val : $val;
                    }
                }
                // Validate 'due' value
                if (isset($params['due']) && !in_array($params['due'], ['overdue', 'today', 'week', 'none'], true)) {
                    unset($params['due']);
                }
                break;

            case 'structure':
                foreach (['owner_id', 'tag', 'status'] as $key) {
                    $val = trim($_POST['param_' . $key] ?? '');
                    if ($val !== '') {
                        $params[$key] = $key === 'owner_id' ? (int) $val : $val;
                    }
                }
                break;

            case 'tasks':
                foreach (['column_id', 'owner_id', 'tag', 'board_id', 'sprint_id', 'due'] as $key) {
                    $val = trim($_POST['param_' . $key] ?? '');
                    if ($val !== '') {
                        $params[$key] = in_array($key, ['column_id', 'owner_id', 'board_id', 'sprint_id'], true)
                            ? (int) $val
                            : $val;
                    }
                }
                break;
        }

        return $params;
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }

    /**
     * Redirect back to the originating page.
     * Falls back to home if return_to is empty.
     */
    private function redirectBack(string $returnTo): void
    {
        if ($returnTo !== '') {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            // Ensure return_to starts with ? for safety
            if (str_starts_with($returnTo, '?')) {
                header('Location: ' . $baseUrl . '/' . $returnTo);
            } else {
                header('Location: ' . $baseUrl . '/?r=' . $returnTo);
            }
            exit;
        }
        $this->redirect('home');
    }
}
