<?php
/**
 * TeamAdminController - Team CRUD, membership management (AP16).
 *
 * Access:
 *   - Global admin: full access to all teams
 *   - team_admin: manage only their own teams
 */
class TeamAdminController
{
    /**
     * List all teams (or user's teams if not global admin).
     */
    public function index(): void
    {
        Authz::require(Authz::TEAM_MANAGE);

        $userId    = (int) $_SESSION['user_id'];
        $globalRole = $_SESSION['user_role'] ?? '';

        if ($globalRole === 'admin') {
            $teams = Team::all();
        } else {
            $teams = Team::getTeamsForUser($userId);
            // Only show teams where user is team_admin
            $teams = array_filter($teams, fn($t) => ($t['team_role'] ?? '') === 'team_admin');
            $teams = array_values($teams);
        }

        // Enrich with counts
        foreach ($teams as &$team) {
            $team['member_count'] = Team::countMembers((int) $team['id']);
            $team['page_count']   = Team::countPages((int) $team['id']);
            $team['task_count']   = Team::countTasks((int) $team['id']);
        }
        unset($team);

        $pageTitle   = 'Teams verwalten';
        $contentView = APP_DIR . '/views/admin/teams/index.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Create a new team (POST).
     */
    public function create(): void
    {
        Authz::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_teams');
            return;
        }

        Security::csrfGuard();

        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $userId      = (int) $_SESSION['user_id'];

        if ($name === '') {
            $_SESSION['_flash_error'] = 'Teamname darf nicht leer sein.';
            $this->redirect('admin_teams');
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 100) {
            $_SESSION['_flash_error'] = 'Teamname darf maximal 100 Zeichen lang sein.';
            $this->redirect('admin_teams');
            return;
        }

        if (Team::nameExists($name)) {
            $_SESSION['_flash_error'] = 'Ein Team mit diesem Namen existiert bereits.';
            $this->redirect('admin_teams');
            return;
        }

        try {
            $teamId = Team::create([
                'name'        => $name,
                'description' => $description !== '' ? $description : null,
                'created_by'  => $userId,
            ]);

            ActivityService::log('team', $teamId, 'team_created', $userId, [
                'name' => $name,
            ]);

            Logger::info('Team created', ['team_id' => $teamId, 'name' => $name]);
            $_SESSION['_flash_success'] = 'Team "' . $name . '" wurde erstellt.';
        } catch (Throwable $e) {
            Logger::error('Failed to create team', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Team konnte nicht erstellt werden.';
        }

        $this->redirect('admin_teams');
    }

    /**
     * Show team edit form with members (GET) or process update (POST).
     */
    public function edit(): void
    {
        Authz::require(Authz::TEAM_MANAGE);

        $teamId = (int) ($_GET['id'] ?? 0);
        if ($teamId <= 0) {
            $this->redirect('admin_teams');
            return;
        }

        $team = Team::findById($teamId);
        if (!$team) {
            http_response_code(404);
            require APP_DIR . '/views/404.php';
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if (!TeamService::canManageTeam($userId, $teamId)) {
            Authz::deny();
        }

        $error   = null;
        $members = TeamUser::getMembers($teamId);
        $allUsers = User::all();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $action = $_POST['action'] ?? 'update';

            if ($action === 'update') {
                $name        = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if ($name === '') {
                    $error = 'Teamname darf nicht leer sein.';
                } elseif (mb_strlen($name, 'UTF-8') > 100) {
                    $error = 'Teamname darf maximal 100 Zeichen lang sein.';
                } elseif (Team::nameExists($name, $teamId)) {
                    $error = 'Ein Team mit diesem Namen existiert bereits.';
                }

                if ($error === null) {
                    try {
                        $oldName = $team['name'];
                        Team::update($teamId, [
                            'name'        => $name,
                            'description' => $description !== '' ? $description : null,
                        ]);

                        ActivityService::log('team', $teamId, 'team_updated', $userId, [
                            'old_name' => $oldName,
                            'new_name' => $name,
                        ]);

                        $_SESSION['_flash_success'] = 'Team wurde aktualisiert.';
                        $this->redirect('admin_team_edit&id=' . $teamId);
                        return;
                    } catch (Throwable $e) {
                        Logger::error('Failed to update team', ['error' => $e->getMessage()]);
                        $error = 'Team konnte nicht aktualisiert werden.';
                    }
                }
                // Reload team data after failed update
                $team = Team::findById($teamId) ?? $team;
            } elseif ($action === 'add_member') {
                $memberUserId = (int) ($_POST['user_id'] ?? 0);
                $memberRole   = $_POST['team_role'] ?? 'team_member';

                if ($memberUserId <= 0) {
                    $error = 'Kein Benutzer ausgewaehlt.';
                } elseif (!in_array($memberRole, TeamUser::ROLES, true)) {
                    $error = 'Ungueltige Team-Rolle.';
                } else {
                    $added = TeamUser::add($teamId, $memberUserId, $memberRole);
                    if ($added) {
                        $memberUser = User::findById($memberUserId);
                        ActivityService::log('team', $teamId, 'team_member_added', $userId, [
                            'member_user_id' => $memberUserId,
                            'member_name'    => $memberUser ? $memberUser['name'] : '',
                            'team_role'      => $memberRole,
                        ]);
                        $_SESSION['_flash_success'] = 'Mitglied wurde hinzugefuegt.';
                    } else {
                        $_SESSION['_flash_error'] = 'Benutzer ist bereits Mitglied dieses Teams.';
                    }
                    $this->redirect('admin_team_edit&id=' . $teamId);
                    return;
                }
            } elseif ($action === 'update_role') {
                $memberUserId = (int) ($_POST['user_id'] ?? 0);
                $memberRole   = $_POST['team_role'] ?? '';

                if ($memberUserId <= 0 || !in_array($memberRole, TeamUser::ROLES, true)) {
                    $error = 'Ungueltige Eingabe.';
                } else {
                    TeamUser::updateRole($teamId, $memberUserId, $memberRole);
                    $memberUser = User::findById($memberUserId);
                    ActivityService::log('team', $teamId, 'team_member_role_changed', $userId, [
                        'member_user_id' => $memberUserId,
                        'member_name'    => $memberUser ? $memberUser['name'] : '',
                        'new_role'       => $memberRole,
                    ]);
                    $_SESSION['_flash_success'] = 'Rolle wurde aktualisiert.';
                    $this->redirect('admin_team_edit&id=' . $teamId);
                    return;
                }
            } elseif ($action === 'remove_member') {
                $memberUserId = (int) ($_POST['user_id'] ?? 0);

                if ($memberUserId <= 0) {
                    $error = 'Kein Benutzer ausgewaehlt.';
                } else {
                    // Remove watchers for this user on team content
                    TeamUser::removeWatchersForTeam($teamId, $memberUserId);
                    TeamUser::remove($teamId, $memberUserId);

                    $memberUser = User::findById($memberUserId);
                    ActivityService::log('team', $teamId, 'team_member_removed', $userId, [
                        'member_user_id' => $memberUserId,
                        'member_name'    => $memberUser ? $memberUser['name'] : '',
                    ]);
                    $_SESSION['_flash_success'] = 'Mitglied wurde entfernt.';
                    $this->redirect('admin_team_edit&id=' . $teamId);
                    return;
                }
            }

            // Refresh members after action
            $members = TeamUser::getMembers($teamId);
        }

        $pageTitle   = 'Team bearbeiten: ' . $team['name'];
        $contentView = APP_DIR . '/views/admin/teams/edit.php';
        require APP_DIR . '/views/layout.php';
    }

    /**
     * Delete a team (POST).
     */
    public function delete(): void
    {
        Authz::requireRole('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin_teams');
            return;
        }

        Security::csrfGuard();

        $teamId = (int) ($_POST['id'] ?? 0);
        $team = Team::findById($teamId);

        if (!$team) {
            $_SESSION['_flash_error'] = 'Team nicht gefunden.';
            $this->redirect('admin_teams');
            return;
        }

        $pageCount = Team::countPages($teamId);
        $taskCount = Team::countTasks($teamId);

        // Nullify team_id on pages and tasks before deleting
        if ($pageCount > 0) {
            DB::query('UPDATE pages SET team_id = NULL WHERE team_id = ?', [$teamId]);
        }
        if ($taskCount > 0) {
            DB::query('UPDATE tasks SET team_id = NULL WHERE team_id = ?', [$teamId]);
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            Team::delete($teamId);

            ActivityService::log('team', $teamId, 'team_deleted', $userId, [
                'name'        => $team['name'],
                'pages_reset' => $pageCount,
                'tasks_reset' => $taskCount,
            ]);

            $msg = 'Team "' . $team['name'] . '" wurde geloescht.';
            if ($pageCount > 0 || $taskCount > 0) {
                $msg .= ' ' . $pageCount . ' Seite(n) und ' . $taskCount . ' Aufgabe(n) sind nun global sichtbar.';
            }
            $_SESSION['_flash_success'] = $msg;
        } catch (Throwable $e) {
            Logger::error('Failed to delete team', ['error' => $e->getMessage()]);
            $_SESSION['_flash_error'] = 'Team konnte nicht geloescht werden.';
        }

        $this->redirect('admin_teams');
    }

    /**
     * Switch the active team in session (POST).
     */
    public function switchTeam(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home');
            return;
        }

        Security::csrfGuard();

        $teamId = $_POST['team_id'] ?? '';

        if ($teamId === '' || $teamId === 'all') {
            TeamService::setActiveTeamId(null);
        } else {
            $teamId = (int) $teamId;
            $userId = (int) $_SESSION['user_id'];
            $globalRole = $_SESSION['user_role'] ?? '';

            // Validate user has access to this team
            if ($globalRole !== 'admin' && !TeamUser::isMember($teamId, $userId)) {
                $_SESSION['_flash_error'] = 'Kein Zugriff auf dieses Team.';
                $this->redirect('home');
                return;
            }

            TeamService::setActiveTeamId($teamId);
        }

        // Redirect back to referring page or home
        $returnTo = $_POST['return_to'] ?? '';
        if ($returnTo !== '' && str_starts_with($returnTo, '?')) {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/' . $returnTo);
            exit;
        }

        $this->redirect('home');
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
