<?php
/**
 * Security helpers: session hardening, CSRF tokens, output escaping.
 */
class Security
{
    /**
     * Start the session with hardened cookie parameters.
     * Call once, early in the request lifecycle.
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 0,           // browser session
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);

        session_start();
    }

    /**
     * Generate (or return existing) CSRF token for the current session.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Render a hidden input field containing the CSRF token.
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . self::esc($token) . '">';
    }

    /**
     * Validate a submitted CSRF token against the session token.
     * Returns true if valid.
     */
    public static function csrfValidate(?string $token): bool
    {
        if ($token === null || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Shortcut: validate the token from $_POST and abort with 403 on failure.
     */
    public static function csrfGuard(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::csrfValidate($_POST['_csrf_token'] ?? null)) {
                http_response_code(403);
                echo 'Invalid or missing CSRF token.';
                exit;
            }
        }
    }

    /**
     * HTML-escape a string for safe output in templates.
     */
    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ── Authentication helpers ──────────────────────────────────────

    /**
     * Require an authenticated session. Redirects to login if not logged in.
     */
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            $baseUrl = rtrim($GLOBALS['config']['BASE_URL'] ?? '', '/');
            header('Location: ' . $baseUrl . '/?r=login');
            exit;
        }
    }

    /**
     * Check whether the current session belongs to an authenticated user.
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Return the full user row from the database for the currently logged-in user.
     * Returns null if not logged in or user no longer exists.
     * Result is cached per request.
     */
    public static function currentUser(): ?array
    {
        static $cache = null;
        static $cachedId = null;

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $id = (int) $_SESSION['user_id'];

        if ($cache !== null && $cachedId === $id) {
            return $cache;
        }

        $cache = User::findById($id);
        $cachedId = $id;
        return $cache;
    }

    /**
     * Check whether the logged-in user has the given role.
     * Accepts a single role string or an array of roles (any match = true).
     *
     * @param string|string[] $role
     */
    public static function hasRole(string|array $role): bool
    {
        $sessionRole = $_SESSION['user_role'] ?? null;
        if ($sessionRole === null) {
            return false;
        }

        if (is_array($role)) {
            return in_array($sessionRole, $role, true);
        }

        return $sessionRole === $role;
    }

    /**
     * Require a specific role. Sends 403 if the user does not match.
     *
     * @param string|string[] $role
     */
    public static function requireRole(string|array $role): void
    {
        self::requireLogin();
        if (!self::hasRole($role)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }
}
