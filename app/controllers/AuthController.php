<?php
/**
 * AuthController - Login / Logout (auth logic comes in AP2).
 */
class AuthController
{
    public function login(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::csrfGuard();
            // Real authentication will be implemented in AP2.
            $error = 'Authentication is not yet available. Coming in AP2.';
        }

        $pageTitle = 'Login';
        // Login uses its own minimal layout (no sidebar navigation)
        require APP_DIR . '/views/login.php';
    }
}
