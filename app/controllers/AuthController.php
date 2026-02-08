<?php
/**
 * AuthController - Login / Logout.
 */
class AuthController
{
    /**
     * GET: Show login form.
     * POST: Authenticate user and create session.
     */
    public function login(): void
    {
        // Already logged in? Redirect to home.
        if (Security::isLoggedIn()) {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/?r=home');
            exit;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $error = 'Login fehlgeschlagen.';
            } else {
                $user = User::findByEmail($email);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check if user account is active (AP9)
                    if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
                        $error = 'Ihr Konto ist deaktiviert. Bitte kontaktieren Sie den Administrator.';
                        Logger::info('Login attempt for deactivated account', ['email' => $email]);
                    } else {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id']   = (int) $user['id'];
                        $_SESSION['user_role']  = $user['role'];
                        $_SESSION['user_name']  = $user['name'];

                        User::touchLogin((int) $user['id']);

                        Logger::info('User logged in', ['user_id' => $user['id'], 'email' => $user['email']]);

                        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
                        header('Location: ' . $baseUrl . '/?r=home');
                        exit;
                    }
                } else {
                    $error = 'Login fehlgeschlagen.';
                    Logger::info('Failed login attempt', ['email' => $email]);
                }
            }
        }

        $pageTitle = 'Login';
        require APP_DIR . '/views/login.php';
    }

    /**
     * Destroy session and redirect to login.
     */
    public function logout(): void
    {
        Logger::info('User logged out', ['user_id' => $_SESSION['user_id'] ?? null]);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
        header('Location: ' . $baseUrl . '/?r=login');
        exit;
    }

    /**
     * Setup route: create initial admin user if no users exist.
     * Only accessible when the users table is empty.
     */
    public function setup(): void
    {
        $userCount = User::count();

        if ($userCount > 0) {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/?r=login');
            exit;
        }

        $error   = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();

            $email    = trim($_POST['email'] ?? '');
            $name     = trim($_POST['name'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $name === '' || $password === '') {
                $error = 'Alle Felder sind erforderlich.';
            } elseif (strlen($password) < 6) {
                $error = 'Passwort muss mindestens 6 Zeichen lang sein.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
            } else {
                User::create($email, $name, $password, 'admin');
                $success = 'Admin-Benutzer wurde erstellt. Sie koennen sich jetzt anmelden.';
                Logger::info('Initial admin user created via setup', ['email' => $email]);
            }
        }

        $pageTitle = 'Setup';
        require APP_DIR . '/views/setup.php';
    }
}
