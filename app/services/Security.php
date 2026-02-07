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
}
