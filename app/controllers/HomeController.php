<?php
/**
 * HomeController - Personal dashboard "Meine Arbeit" (AP22).
 */
class HomeController
{
    public function index(): void
    {
        $userId       = (int) $_SESSION['user_id'];
        $globalRole   = $_SESSION['user_role'] ?? 'viewer';
        $activeTeamId = TeamService::getActiveTeamId();

        // AP22: Quick actions via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleQuickAction($userId, $globalRole);
            return;
        }

        // AP27: Default-View redirect (only on direct home navigation)
        if (empty($_GET['no_default'])) {
            try {
                $defaultView = UserView::getDefault($userId);
                if ($defaultView) {
                    $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
                    $viewUrl = UserView::buildUrl($defaultView, $baseUrl);
                    header('Location: ' . $viewUrl);
                    exit;
                }
            } catch (Throwable $e) {
                // table may not exist yet – continue to normal home
            }
        }

        require_once APP_DIR . '/services/HomeDashboardService.php';

        $overdue     = HomeDashboardService::overdue($userId, $globalRole, $activeTeamId);
        $dueToday    = HomeDashboardService::dueToday($userId, $globalRole, $activeTeamId);
        $dueWeek     = HomeDashboardService::dueThisWeek($userId, $globalRole, $activeTeamId);
        $assignedToMe = HomeDashboardService::assignedToMe($userId, $globalRole, $activeTeamId);
        $watching    = HomeDashboardService::watching($userId, $globalRole, $activeTeamId);
        $doneColumnId = HomeDashboardService::getDoneColumnId();
        $canEdit     = Authz::can(Authz::TASK_EDIT);
        $users       = User::allForDropdown();

        // AP27: Load saved views for home dashboard
        $savedViews = [];
        try {
            $savedViews = UserView::allForUserGrouped($userId);
        } catch (Throwable $e) {
            // table may not exist yet
        }

        $pageTitle   = 'Home';
        $contentView = APP_DIR . '/views/home.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Handle quick actions from the home dashboard.
     */
    private function handleQuickAction(int $userId, string $globalRole): void
    {
        Security::csrfGuard();

        $action = $_POST['home_action'] ?? '';
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $task   = Task::findById($taskId);

        if (!$task) {
            $this->redirect('home');
            return;
        }

        // Team edit check
        if (!TeamService::canEditTask($userId, $task)) {
            Authz::deny();
        }

        switch ($action) {
            case 'mark_done':
                Authz::require(Authz::TASK_CHANGE_STATUS);
                require_once APP_DIR . '/services/HomeDashboardService.php';
                $doneColId = HomeDashboardService::getDoneColumnId();
                if ($doneColId !== null) {
                    $oldColumnId = (int) $task['column_id'];
                    Task::update($taskId, [
                        'column_id'  => $doneColId,
                        'updated_by' => $userId,
                    ]);
                    TaskFlowService::onColumnChange($taskId, $oldColumnId, $doneColId);
                    $oldColumn = BoardColumn::findById($oldColumnId);
                    $newColumn = BoardColumn::findById($doneColId);
                    ActivityService::log('task', $taskId, 'task_column_changed', $userId, [
                        'old_column_id'   => $oldColumnId,
                        'new_column_id'   => $doneColId,
                        'old_column_name' => $oldColumn['name'] ?? '',
                        'new_column_name' => $newColumn['name'] ?? '',
                    ]);
                    EventService::emit('task.moved', 'task', $taskId, $userId, [
                        'old_column_id'   => $oldColumnId,
                        'new_column_id'   => $doneColId,
                        'new_column_name' => $newColumn['name'] ?? '',
                    ]);
                    $_SESSION['_flash_success'] = 'Task "' . $task['title'] . '" als erledigt markiert.';
                }
                break;

            case 'set_due':
                Authz::require(Authz::TASK_EDIT);
                $newDue = trim($_POST['due_date'] ?? '');
                if ($newDue !== '') {
                    $d = date_create_from_format('Y-m-d', $newDue);
                    if ($d && $d->format('Y-m-d') === $newDue) {
                        Task::update($taskId, [
                            'due_date'   => $newDue,
                            'updated_by' => $userId,
                        ]);
                        ActivityService::log('task', $taskId, 'task_updated', $userId, [
                            'title'          => $task['title'],
                            'changed_fields' => ['due_date'],
                        ]);
                    }
                }
                break;

            case 'set_owner':
                Authz::require(Authz::TASK_EDIT);
                $newOwnerId = (int) ($_POST['owner_id'] ?? 0);
                if ($newOwnerId > 0) {
                    $owner = User::findById($newOwnerId);
                    if ($owner) {
                        $oldOwnerId = (int) ($task['owner_id'] ?? 0);
                        Task::update($taskId, [
                            'owner_id'   => $newOwnerId,
                            'updated_by' => $userId,
                        ]);
                        ActivityService::log('task', $taskId, 'task_updated', $userId, [
                            'title'          => $task['title'],
                            'changed_fields' => ['owner_id'],
                        ]);
                        if ($newOwnerId !== $userId && $newOwnerId !== $oldOwnerId) {
                            WatcherService::autoWatchOnAssignment($taskId, $newOwnerId);
                            EventService::emit('task.assigned', 'task', $taskId, $userId, [
                                'new_owner_id' => $newOwnerId,
                                'task_id'      => $taskId,
                                'task_title'   => $task['title'],
                            ]);
                        }
                    }
                }
                break;
        }

        $this->redirect('home');
    }

    private function redirect(string $route): void
    {
        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=' . $route);
        exit;
    }
}
